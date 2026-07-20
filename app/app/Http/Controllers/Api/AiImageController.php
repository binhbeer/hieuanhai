<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AccountDeletedException;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Tag;
use App\Services\ImageCreationService;
use App\Support\AppSettings;
use App\Support\LocalizedRoute;
use App\Support\UserActivityLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AiImageController extends Controller
{
    public function __construct(private UserActivityLock $activityLock) {}

    public function store(Request $request, ImageCreationService $editor): JsonResponse
    {
        return $this->storeImage($request, $editor, publish: false);
    }

    public function storeAndPublish(Request $request, ImageCreationService $editor): JsonResponse
    {
        return $this->storeImage($request, $editor, publish: true);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->active()
                ->englishReady()
                ->ordered()
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->getTranslationWithoutFallback('name', 'en'),
                    'slug' => $category->slug_en,
                ])
                ->values(),
        ]);
    }

    public function search(Request $request, ImageCreationService $editor): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'keyword' => ['sometimes', 'string', 'max:200'],
            'q' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:120'],
            'tag' => ['sometimes', 'string', 'max:120'],
            'source' => ['sometimes', 'string', 'max:120'],
            'user' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $keyword = trim((string) ($request->query('keyword') ?: $request->query('q', '')));
        $category = trim((string) $request->query('category', ''));
        $tag = trim((string) $request->query('tag', ''));
        $source = trim((string) $request->query('source', ''));
        $userId = $request->integer('user');
        $perPage = min(100, max(1, $request->integer('per_page', 24)));

        $images = GeneratedMedia::query()
            ->with(['category', 'tags', 'user'])
            ->publiclyVisible()
            ->englishReady()
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    $query->where('title->en', 'like', '%'.$keyword.'%')
                        ->orWhere('prompt', 'like', '%'.$keyword.'%')
                        ->orWhereHas('category', fn ($query) => $query->where('name->en', 'like', '%'.$keyword.'%'))
                        ->orWhereHas('tags', fn ($query) => $query->where('name->en', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($category !== '', fn ($query) => $query->whereHas('category', fn ($query) => $query->where('slug_en', $category)->where('status', 'active')))
            ->when($tag !== '', fn ($query) => $query->whereHas('tags', fn ($query) => $query->where('slug_en', $tag)))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $images->getCollection()->map(fn (GeneratedMedia $image): array => $this->searchImagePayload($image, $editor))->values()->all(),
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
            ],
        ]);
    }

    private function storeImage(Request $request, ImageCreationService $editor, bool $publish): JsonResponse
    {
        $startedAt = microtime(true);
        $key = $request->attributes->get('ai_api_key');

        if (! $key instanceof ApiKey) {
            return response()->json(['message' => 'The API key is invalid.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'prompt' => $this->promptRules(),
            'source' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'model' => ['sometimes', 'nullable', 'string', 'max:120', Rule::in(AppSettings::enabledImageModels())],
            'images' => ['sometimes', 'array', 'max:'.AppSettings::maxReferencePhotos()],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);

        if ($validator->fails()) {
            $this->logRequest($key, $request, $startedAt, 422, 'validation_failed', false, null, 'The provided data is invalid.', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $image = null;
        $audit = null;

        try {
            return $this->activityLock->runApi($key->user_id, $key->user->api_image_concurrency_limit ?? 1, function () use ($editor, $key, $publish, $request, $startedAt, &$audit, &$image): JsonResponse {
                $audit = $this->reserveQuota($key, $request, $startedAt);

                if (! $audit) {
                    $key->refresh();
                    $this->logRequest($key, $request, $startedAt, 429, 'quota_exceeded', false, null, 'The API key quota has been exhausted.', [
                        'quota' => $this->quotaPayload($key),
                    ]);

                    return response()->json([
                        'message' => 'The API key quota has been exhausted.',
                        'quota' => $this->quotaPayload($key),
                    ], 429);
                }

                $files = $request->file('images', []);
                $photos = is_array($files) ? array_values($files) : [$files];
                $requestedModel = $request->filled('model') ? (string) $request->string('model') : null;
                $image = $editor->create($request, $photos, (string) $request->string('prompt'), $requestedModel);

                if ($publish) {
                    $image = $editor->publish($image, $request, requireOwner: false);
                }

                $key = $this->chargeAndFinalize($key, $audit, $image, $request, $startedAt, $publish);

                return response()->json($this->responsePayload($image, $editor, $key, $publish), 201);
            });
        } catch (AccountDeletedException) {
            return response()->json(['message' => 'The API key is invalid.'], 401);
        } catch (LockTimeoutException) {
            $message = 'Another image request is already in progress. Please try again later.';
            $this->logRequest($key, $request, $startedAt, 409, 'conflict', false, null, $message, [
                'error_code' => 'IMAGE_CREATION_IN_PROGRESS',
            ]);

            return response()->json([
                'message' => $message,
                'error_code' => 'IMAGE_CREATION_IN_PROGRESS',
            ], 409);
        } catch (\InvalidArgumentException $e) {
            $errorCode = match ($e->getCode()) {
                ImageCreationService::ERROR_IMAGE_REVIEW_SEXUAL => 'IMAGE_REVIEW_BLOCKED_SEXUAL',
                ImageCreationService::ERROR_IMAGE_REVIEW_POLITICAL => 'IMAGE_REVIEW_BLOCKED_POLITICAL',
                ImageCreationService::ERROR_IMAGE_REVIEW_UNAVAILABLE => 'IMAGE_REVIEW_UNAVAILABLE',
                default => null,
            };
            $statusCode = $errorCode === 'IMAGE_REVIEW_UNAVAILABLE' ? 503 : 422;
            $status = $statusCode === 503 ? 'failed' : 'validation_failed';
            $responseMeta = $errorCode ? ['error_code' => $errorCode] : [];
            $this->finalizeRequest($audit, $request, $startedAt, $statusCode, $status, $image?->id, $e->getMessage(), $responseMeta);

            $message = match ($errorCode) {
                'IMAGE_REVIEW_BLOCKED_SEXUAL', 'IMAGE_REVIEW_BLOCKED_POLITICAL' => 'The prompt is not eligible for image generation or publication.',
                'IMAGE_REVIEW_UNAVAILABLE' => 'Image review is temporarily unavailable. Please try again later.',
                default => 'The request could not be processed.',
            };

            return response()->json(array_filter([
                'message' => $message,
                'error_code' => $errorCode,
            ]), $statusCode);
        } catch (Throwable $e) {
            report($e);
            $this->finalizeRequest($audit, $request, $startedAt, 500, 'failed', $image?->id, $e->getMessage());

            return response()->json(['message' => 'Could not create an image right now. Please try again later.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function searchImagePayload(GeneratedMedia $image, ImageCreationService $editor): array
    {
        return [
            'id' => $image->id,
            'title' => $image->getTranslationWithoutFallback('title', 'en'),
            'description' => $image->getTranslationWithoutFallback('description', 'en'),
            'prompt_preview' => Str::limit($image->prompt, 160),
            'url' => $editor->resultUrl($image),
            'public_url' => $image->englishReady()
                ? LocalizedRoute::url('images.show', $image, 'en')
                : LocalizedRoute::url('images.show', $image, 'vi'),
            'source' => $image->source,
            'category' => $image->category?->englishReady() ? [
                'id' => $image->category->id,
                'name' => $image->category->getTranslationWithoutFallback('name', 'en'),
                'slug' => $image->category->slug_en,
            ] : null,
            'tags' => $image->tags
                ->filter(fn (Tag $tag): bool => $tag->englishReady())
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->getTranslationWithoutFallback('name', 'en'),
                    'slug' => $tag->slug_en,
                ])->values()->all(),
            'user' => $image->user ? [
                'id' => $image->user->id,
                'name' => $image->user->name,
            ] : null,
            'created_at' => $image->created_at?->toISOString(),
            'published_at' => $image->published_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function promptRules(): array
    {
        return AppSettings::promptRules();
    }

    /**
     * @return array{limit: int, used: int, remaining: int}
     */
    private function quotaPayload(ApiKey $key): array
    {
        return [
            'limit' => $key->quota_limit,
            'used' => $key->quota_used,
            'remaining' => $key->quotaRemaining(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(GeneratedMedia $image, ImageCreationService $editor, ApiKey $key, bool $publish): array
    {
        $payload = [
            'id' => $image->id,
            'url' => $editor->resultUrl($image),
            'download_name' => $image->downloadName(),
            'status' => $image->status,
            'model' => $image->model,
            'source' => $image->source,
            'created_at' => $image->created_at?->toISOString(),
            'quota' => $this->quotaPayload($key),
        ];

        if (! $publish) {
            return $payload;
        }

        $image->loadMissing(['category', 'tags']);

        return [
            ...$payload,
            'title' => $image->getTranslationWithoutFallback('title', 'en'),
            'description' => $image->getTranslationWithoutFallback('description', 'en'),
            'public_url' => $image->englishReady()
                ? LocalizedRoute::url('images.show', $image, 'en')
                : LocalizedRoute::url('images.show', $image, 'vi'),
            'published' => $image->is_published,
            'category' => $image->category?->englishReady() ? [
                'id' => $image->category->id,
                'name' => $image->category->getTranslationWithoutFallback('name', 'en'),
                'slug' => $image->category->slug_en,
            ] : null,
            'tags' => $image->tags
                ->filter(fn (Tag $tag): bool => $tag->englishReady())
                ->map(fn (Tag $tag): string => (string) $tag->getTranslationWithoutFallback('name', 'en'))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseMeta(GeneratedMedia $image, bool $publish): array
    {
        $meta = [
            'image_id' => $image->id,
            'result_path' => $image->result_path,
            'model' => $image->model,
            'source' => $image->source,
        ];

        if (! $publish) {
            return $meta;
        }

        $image->loadMissing(['category', 'tags']);

        return [
            ...$meta,
            'title' => $image->getTranslationWithoutFallback('title', 'en'),
            'description' => $image->getTranslationWithoutFallback('description', 'en'),
            'published' => $image->is_published,
            'public_url' => $image->englishReady()
                ? LocalizedRoute::url('images.show', $image, 'en')
                : LocalizedRoute::url('images.show', $image, 'vi'),
            'category' => $image->category?->englishReady() ? $image->category->slug_en : null,
            'tags' => $image->tags
                ->filter(fn (Tag $tag): bool => $tag->englishReady())
                ->map(fn (Tag $tag): string => (string) $tag->getTranslationWithoutFallback('name', 'en'))
                ->values()
                ->all(),
        ];
    }

    private function reserveQuota(ApiKey $key, Request $request, float $startedAt): ?ApiRequest
    {
        return DB::transaction(function () use ($key, $request, $startedAt): ?ApiRequest {
            $lockedKey = (new ApiKey)
                ->disableModelCaching()
                ->newQuery()
                ->lockForUpdate()
                ->find($key->id);

            if (! $lockedKey) {
                throw new AccountDeletedException;
            }

            $processing = ApiRequest::query()
                ->where('api_key_id', $lockedKey->id)
                ->where('status', 'processing')
                ->count();

            if ($lockedKey->quota_used + $processing >= $lockedKey->quota_limit) {
                return null;
            }

            return ApiRequest::create([
                'api_key_id' => $lockedKey->id,
                'user_id' => $lockedKey->user_id,
                'media_id' => null,
                'ip_address' => $request->ip(),
                'status_code' => 102,
                'status' => 'processing',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'quota_charged' => false,
                'error' => null,
                'request_meta' => $this->requestMeta($request),
                'response_meta' => [],
            ]);
        });
    }

    private function chargeAndFinalize(ApiKey $key, ApiRequest $audit, GeneratedMedia $image, Request $request, float $startedAt, bool $publish): ApiKey
    {
        DB::transaction(function () use ($audit, $image, $request, $startedAt, $publish): void {
            $lockedAudit = ApiRequest::query()->lockForUpdate()->findOrFail($audit->id);

            if ($lockedAudit->status !== 'processing' || $lockedAudit->quota_charged) {
                return;
            }

            $lockedKey = (new ApiKey)
                ->disableModelCaching()
                ->newQuery()
                ->lockForUpdate()
                ->findOrFail($lockedAudit->api_key_id);
            $lockedKey->increment('quota_used');
            $lockedAudit->update([
                'media_id' => $image->id,
                'status_code' => 201,
                'status' => 'succeeded',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'quota_charged' => true,
                'error' => null,
                'request_meta' => $this->requestMeta($request),
                'response_meta' => $this->responseMeta($image, $publish),
            ]);
        });

        return (new ApiKey)->disableModelCaching()->newQuery()->findOrFail($key->id);
    }

    /** @param array<string, mixed> $responseMeta */
    private function finalizeRequest(?ApiRequest $audit, Request $request, float $startedAt, int $statusCode, string $status, ?int $imageId, ?string $error, array $responseMeta = []): void
    {
        if (! $audit || ! $audit->exists) {
            return;
        }

        ApiRequest::query()
            ->whereKey($audit->id)
            ->where('status', 'processing')
            ->update([
                'media_id' => $imageId,
                'status_code' => $statusCode,
                'status' => $status,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'quota_charged' => false,
                'error' => $error ? Str::limit($error, 2000, '') : null,
                'request_meta' => json_encode($this->requestMeta($request), JSON_THROW_ON_ERROR),
                'response_meta' => json_encode($responseMeta, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function uploadCount(Request $request): int
    {
        return count((array) $request->file('images', []));
    }

    /** @return array<string, mixed> */
    private function requestMeta(Request $request): array
    {
        return [
            'upload_count' => $this->uploadCount($request),
            'model' => $request->filled('model') ? (string) $request->string('model') : AppSettings::defaultImageModel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $responseMeta
     */
    private function logRequest(
        ApiKey $key,
        Request $request,
        float $startedAt,
        int $statusCode,
        string $status,
        bool $quotaCharged,
        ?int $imageId,
        ?string $error,
        array $responseMeta = [],
    ): void {
        if (! $key->exists || ! ApiKey::query()->whereKey($key->id)->exists()) {
            return;
        }

        ApiRequest::create([
            'api_key_id' => $key->id,
            'user_id' => $key->user_id,
            'media_id' => $imageId,
            'ip_address' => $request->ip(),
            'status_code' => $statusCode,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'quota_charged' => $quotaCharged,
            'error' => $error ? Str::limit($error, 2000, '') : null,
            'request_meta' => $this->requestMeta($request),
            'response_meta' => $responseMeta,
        ]);
    }
}
