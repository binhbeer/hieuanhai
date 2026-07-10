<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\AiImage;
use App\Models\AiTag;
use App\Models\Category;
use App\Services\AiImageEditor;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AiImageController extends Controller
{
    public function store(Request $request, AiImageEditor $editor): JsonResponse
    {
        return $this->storeImage($request, $editor, publish: false);
    }

    public function storeAndPublish(Request $request, AiImageEditor $editor): JsonResponse
    {
        return $this->storeImage($request, $editor, publish: true);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function search(Request $request, AiImageEditor $editor): JsonResponse
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
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $keyword = trim((string) ($request->query('keyword') ?: $request->query('q', '')));
        $category = trim((string) $request->query('category', ''));
        $tag = trim((string) $request->query('tag', ''));
        $source = trim((string) $request->query('source', ''));
        $userId = $request->integer('user');
        $perPage = min(100, max(1, $request->integer('per_page', 24)));

        $images = AiImage::query()
            ->with(['category', 'tags', 'user'])
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    $query->where('title', 'like', '%'.$keyword.'%')
                        ->orWhere('prompt', 'like', '%'.$keyword.'%')
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'))
                        ->orWhereHas('tags', fn ($query) => $query->where('name', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($category !== '', fn ($query) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)->where('status', 'active')))
            ->when($tag !== '', fn ($query) => $query->whereHas('tags', fn ($query) => $query->where('slug', $tag)))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $images->getCollection()->map(fn (AiImage $image): array => $this->searchImagePayload($image, $editor))->values()->all(),
            'meta' => [
                'current_page' => $images->currentPage(),
                'last_page' => $images->lastPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
            ],
        ]);
    }

    private function storeImage(Request $request, AiImageEditor $editor, bool $publish): JsonResponse
    {
        $startedAt = microtime(true);
        $key = $request->attributes->get('ai_api_key');

        if (! $key instanceof AiApiKey) {
            return response()->json(['message' => 'API key không hợp lệ.'], 401);
        }

        if (! $key->hasQuota()) {
            $this->logRequest($key, $request, $startedAt, 429, 'quota_exceeded', false, null, 'API key đã hết quota.', [
                'quota' => $this->quotaPayload($key),
            ]);

            return response()->json([
                'message' => 'API key đã hết quota.',
                'quota' => $this->quotaPayload($key),
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'prompt' => $this->promptRules(),
            'source' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'images' => ['sometimes', 'array', 'max:'.min(3, max(1, AppSettings::int('ai.image_max_reference_photos', (int) config('ai.image_max_reference_photos', 1))))],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::int('ai.image_upload_max_kb', (int) config('ai.image_upload_max_kb', 32768))],
        ]);

        if ($validator->fails()) {
            $this->logRequest($key, $request, $startedAt, 422, 'validation_failed', false, null, 'Dữ liệu không hợp lệ.', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $image = null;

        try {
            $files = $request->file('images', []);
            $photos = is_array($files) ? array_values($files) : [$files];
            $image = $editor->create($request, $photos, (string) $request->string('prompt'));

            if ($publish) {
                $image = $editor->publish($image, $request, requireOwner: false);
            }

            $key->increment('quota_used');
            $key->refresh();

            $this->logRequest($key, $request, $startedAt, 201, 'succeeded', true, $image->id, null, $this->responseMeta($image, $publish));

            return response()->json($this->responsePayload($image, $editor, $key, $publish), 201);
        } catch (\InvalidArgumentException $e) {
            $this->logRequest($key, $request, $startedAt, 422, 'validation_failed', false, $image?->id, $e->getMessage());

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            $this->logRequest($key, $request, $startedAt, 500, 'failed', false, $image?->id, $e->getMessage());

            return response()->json(['message' => 'Không tạo được ảnh lúc này. Vui lòng thử lại sau.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function searchImagePayload(AiImage $image, AiImageEditor $editor): array
    {
        return [
            'id' => $image->id,
            'title' => $image->title,
            'prompt_preview' => Str::limit($image->prompt, 160),
            'url' => $editor->resultUrl($image),
            'public_url' => route('images.show', $image),
            'source' => $image->source,
            'category' => $image->category ? [
                'id' => $image->category->id,
                'name' => $image->category->name,
                'slug' => $image->category->slug,
            ] : null,
            'tags' => $image->tags->map(fn (AiTag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
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
        return [
            'required',
            'string',
            'max:12000',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match_all('/[\p{L}\p{N}]+/u', (string) $value) > 1200) {
                    $fail('Prompt không được vượt quá 1200 từ.');
                }
            },
        ];
    }

    /**
     * @return array{limit: int, used: int, remaining: int}
     */
    private function quotaPayload(AiApiKey $key): array
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
    private function responsePayload(AiImage $image, AiImageEditor $editor, AiApiKey $key, bool $publish): array
    {
        $payload = [
            'id' => $image->id,
            'url' => $editor->resultUrl($image),
            'download_name' => $image->downloadName(),
            'status' => $image->status,
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
            'title' => $image->title,
            'public_url' => route('images.show', $image),
            'published' => $image->is_published,
            'category' => $image->category ? [
                'id' => $image->category->id,
                'name' => $image->category->name,
                'slug' => $image->category->slug,
            ] : null,
            'tags' => $image->tags->pluck('name')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseMeta(AiImage $image, bool $publish): array
    {
        $meta = [
            'image_id' => $image->id,
            'result_path' => $image->result_path,
            'source' => $image->source,
        ];

        if (! $publish) {
            return $meta;
        }

        $image->loadMissing(['category', 'tags']);

        return [
            ...$meta,
            'title' => $image->title,
            'published' => $image->is_published,
            'public_url' => route('images.show', $image),
            'category' => $image->category?->slug,
            'tags' => $image->tags->pluck('name')->values()->all(),
        ];
    }

    private function uploadCount(Request $request): int
    {
        return count((array) $request->file('images', []));
    }

    /**
     * @param  array<string, mixed>  $responseMeta
     */
    private function logRequest(
        AiApiKey $key,
        Request $request,
        float $startedAt,
        int $statusCode,
        string $status,
        bool $quotaCharged,
        ?int $imageId,
        ?string $error,
        array $responseMeta = [],
    ): void {
        AiApiRequest::create([
            'ai_api_key_id' => $key->id,
            'user_id' => $key->user_id,
            'ai_image_id' => $imageId,
            'ip_address' => $request->ip(),
            'status_code' => $statusCode,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'quota_charged' => $quotaCharged,
            'error' => $error ? Str::limit($error, 2000, '') : null,
            'request_meta' => [
                'upload_count' => $this->uploadCount($request),
            ],
            'response_meta' => $responseMeta,
        ]);
    }
}
