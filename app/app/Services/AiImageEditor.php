<?php

namespace App\Services;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Ai\ImageToPromptAgent;
use App\Ai\PromptRewriteAgent;
use App\Ai\PromptTranslationAgent;
use App\Events\AiImageCompleted;
use App\Jobs\GenerateTagDescription;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Tag;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\GptImageOptions;
use GdImage;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Files\Base64Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class AiImageEditor
{
    public const ERROR_IMAGE_REVIEW_SEXUAL = 1001;

    public const ERROR_IMAGE_REVIEW_POLITICAL = 1002;

    public const ERROR_IMAGE_REVIEW_UNAVAILABLE = 1003;

    private const REFERENCE_MAX_WIDTH = 1024;

    private const REFERENCE_JPEG_QUALITY = 88;

    /**
     * @var array<string, int|array{0: int, 1: int}|null>
     */
    private const IMAGE_SIZES = [
        'original' => null,
        'xs' => 320,
        'sm' => 640,
        'md' => 1024,
        'lg' => 1200,
        'og' => [1200, 630],
    ];

    /**
     * @param  array<int, mixed>  $photos
     */
    public function create(Request $request, array $photos, string $prompt): GeneratedMedia
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt là bắt buộc.');
        }

        $photos = array_values(array_filter($photos, fn($item) => $item instanceof UploadedFile));
        $this->reviewForCreation($prompt, $photos);
        $photo = $photos[0] ?? null;
        $visitorKey = $this->visitorKey($request);
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default_for_images', 'openai'));
        $model = AppSettings::string('ai.image_model', (string) config('ai.image_model', 'cx/gpt-5.5-image'));
        $finalPrompt = trim(implode("\n\n", array_filter([
            $photos === [] ? null : 'Use the provided reference image as the source. Edit that image according to the instructions. Preserve the original subjects, identities, composition, pose, and count unless explicitly asked to change them. Do not create an unrelated new image.',
            $prompt,
        ])));

        $image = GeneratedMedia::create([
            'user_id' => Auth::id(),
            'visitor_key' => $visitorKey,
            'ip_address' => $request->ip(),
            'prompt' => $prompt,
            'source' => $this->source($request),
            'provider' => $provider,
            'model' => $model,
            'status' => 'pending',
            'request_meta' => [
                'upload_name' => $photo?->getClientOriginalName(),
                'upload_mime' => $photo?->getClientMimeType(),
                'upload_size' => $photo?->getSize(),
                'upload_count' => count($photos),
            ],
        ]);

        try {
            $result = $this->generateImage($image, $photos, $finalPrompt, $provider, $model);
            $content = base64_decode($result['base64'], true);

            if ($content === false) {
                throw new \RuntimeException('API trả về ảnh base64 không hợp lệ.');
            }

            $media = $this->storeGeneratedImage($image, $content, $result);

            $image->update([
                'result_path' => $media->getPathRelativeToRoot(),
                'status' => 'succeeded',
                'response_meta' => [
                    'provider' => $provider,
                    'model' => $model,
                    'usage' => $result['usage'],
                    'source_paths' => $result['source_paths'],
                ],
            ]);
        } catch (Throwable $e) {
            $image->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 2000, ''),
            ]);

            throw $e;
        }

        return $image->refresh();
    }

    /**
     * @param  array<int, mixed>  $photos
     * @param  array<int, int>  $referenceImageIds
     * @param  array<int, int>  $parentReferenceIndexes
     */
    public function createPending(
        Request $request,
        array $photos,
        string $prompt,
        array $referenceImageIds = [],
        ?int $parentId = null,
        array $parentReferenceIndexes = [],
        ?string $size = null,
        ?string $imageDetail = null,
        ?string $aspectRatio = null,
        ?string $resolution = null,
    ): GeneratedMedia {

        if (!Auth::check()) {
            throw new \InvalidArgumentException('Vui lòng đăng nhập để tạo ảnh.');
        }

        if ($this->requiresEmailVerificationForImageCreation()) {
            throw new \InvalidArgumentException('Vui lòng xác minh email để tiếp tục nhận lượt tạo ảnh hằng ngày sau ngày đăng ký đầu tiên.');
        }

        if ($this->isLimitExceeded($request)) {
            throw new \InvalidArgumentException('Bạn đã dùng hết lượt tạo ảnh hôm nay.');
        }

        $userId = (int) Auth::id();
        $size = is_string($size) && GptImageOptions::isValidSize($size) ? $size : null;
        $imageDetail = is_string($imageDetail) && GptImageOptions::isValidImageDetail($imageDetail) ? $imageDetail : null;
        $aspectRatio = is_string($aspectRatio) && in_array($aspectRatio, GptImageOptions::ASPECT_RATIOS, true) ? $aspectRatio : null;
        $resolution = is_string($resolution) && in_array($resolution, GptImageOptions::RESOLUTIONS, true) ? $resolution : null;

        if ($aspectRatio !== null && $resolution !== null) {
            $size = GptImageOptions::size($aspectRatio, $resolution);
        }

        return DB::transaction(function () use ($request, $photos, $prompt, $referenceImageIds, $parentId, $parentReferenceIndexes, $userId, $size, $imageDetail, $aspectRatio, $resolution) {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (GeneratedMedia::query()->where('user_id', $userId)->where('status', 'pending')->exists()) {
                throw new \InvalidArgumentException('Bạn đang có ảnh đang tạo. Vui lòng chờ ảnh hiện tại hoàn tất.');
            }

            $prompt = trim($prompt);

            if ($prompt === '') {
                throw new \InvalidArgumentException('Prompt là bắt buộc.');
            }

            $parent = $parentId
                ? GeneratedMedia::query()->where('user_id', $userId)->find($parentId)
                : null;

            if ($parentId && !$parent) {
                throw new \InvalidArgumentException('Không tìm thấy ảnh gốc để chỉnh sửa.');
            }

            $parentReferenceUploads = $parent
                ? $this->storeParentReferenceUploads($parent, $parentReferenceIndexes)
                : [];
            $referenceImageIds = array_slice(array_values(array_unique(array_map('intval', $referenceImageIds))), 0, max(0, AppSettings::maxReferencePhotos() - count($parentReferenceUploads)));
            $referenceUploads = $this->storeReferenceImageUploads($referenceImageIds);
            $photos = array_slice(array_values(array_filter($photos, fn($item) => $item instanceof UploadedFile)), 0, max(0, AppSettings::maxReferencePhotos() - count($parentReferenceUploads) - count($referenceUploads)));
            $pendingUploads = [...$parentReferenceUploads, ...$referenceUploads, ...$this->storePendingUploads($photos)];
            $photo = $photos[0] ?? null;

            return GeneratedMedia::create([
                'user_id' => $userId,
                'parent_id' => $parent?->id,
                'visitor_key' => $this->visitorKey($request),
                'ip_address' => $request->ip(),
                'prompt' => $prompt,
                'provider' => AppSettings::string('ai.image_provider', (string) config('ai.default_for_images', 'openai')),
                'model' => AppSettings::string('ai.image_model', (string) config('ai.image_model', 'cx/gpt-5.5-image')),
                'status' => 'pending',
                'request_meta' => array_filter([
                    'upload_name' => $photo?->getClientOriginalName(),
                    'upload_mime' => $photo?->getClientMimeType(),
                    'upload_size' => $photo?->getSize(),
                    'upload_count' => count($pendingUploads),
                    'reference_image_ids' => array_values(array_filter(array_map(fn(array $upload) => $upload['image_id'] ?? null, $referenceUploads))),
                    'parent_prompt' => $parent?->prompt,
                    'pending_uploads' => $pendingUploads,
                    'aspect_ratio' => $aspectRatio,
                    'resolution' => $resolution,
                    'size' => $size,
                    'image_detail' => $imageDetail,
                    'progress' => 'queued',
                ], fn(mixed $value): bool => $value !== null),
            ]);
        });
    }

    public function cancelPending(GeneratedMedia $image): bool
    {
        if ($image->status !== 'pending') {
            return false;
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $pendingUploads = $requestMeta['pending_uploads'] ?? [];
        unset($requestMeta['parent_prompt'], $requestMeta['pending_uploads']);
        $requestMeta['progress'] = 'cancelled';

        $updated = GeneratedMedia::query()
            ->whereKey($image->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'error' => 'Đã hủy tạo ảnh.',
                'request_meta' => $requestMeta,
            ]);

        if ($updated === 0) {
            return false;
        }

        $this->deletePendingUploads($pendingUploads);
        $image->refresh();

        return true;
    }

    public function retryFailed(GeneratedMedia $image, Request $request): GeneratedMedia
    {
        if ($image->status !== 'failed') {
            return $image->refresh();
        }

        $userId = (int) $image->user_id;

        return DB::transaction(function () use ($image, $request, $userId) {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (GeneratedMedia::query()->where('user_id', $userId)->where('status', 'pending')->exists()) {
                throw new \InvalidArgumentException('Bạn đang có ảnh đang tạo. Vui lòng chờ ảnh hiện tại hoàn tất.');
            }

            if ($this->isLimitExceeded($request)) {
                throw new \InvalidArgumentException('Bạn đã dùng hết lượt tạo ảnh hôm nay.');
            }

            $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
            $requestMeta['pending_uploads'] = $this->storeParentReferenceUploads($image, array_keys($this->referenceSourcePaths($image)));
            $requestMeta['progress'] = 'queued';
            $requestMeta['upload_count'] = count($requestMeta['pending_uploads']);

            if ($image->parent_id) {
                $requestMeta['parent_prompt'] = $image->parent?->prompt;
            } else {
                unset($requestMeta['parent_prompt']);
            }

            $image->update([
                'status' => 'pending',
                'error' => null,
                'request_meta' => $requestMeta,
            ]);

            return $image->refresh();
        });
    }

    public function completePending(GeneratedMedia $image): GeneratedMedia
    {
        if ($image->status !== 'pending') {
            return $image->refresh();
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $pendingUploads = $requestMeta['pending_uploads'] ?? [];
        $sourcePaths = [];

        try {
            $photos = $this->pendingUploadedFiles($pendingUploads);
            $parentPrompt = $requestMeta['parent_prompt'] ?? null;
            $finalPrompt = trim(implode("\n\n", array_filter([
                $photos === [] ? null : $this->referencePrompt($requestMeta),
                is_string($parentPrompt) ? 'Original prompt: ' . $parentPrompt : null,
                $image->parent_id ? 'Edit instructions: ' . $image->prompt : $image->prompt,
            ])));

            if (!$this->updateImageProgress($image, $requestMeta, 'reviewing')) {
                return $image->refresh();
            }

            $this->reviewForCreation($finalPrompt, array_values($photos));

            if (!$this->updateImageProgress($image, $requestMeta, 'generating')) {
                return $image->refresh();
            }

            $size = is_string($requestMeta['size'] ?? null) ? $requestMeta['size'] : null;
            $imageDetail = is_string($requestMeta['image_detail'] ?? null) ? $requestMeta['image_detail'] : null;
            $result = $this->generateImage($image, $photos, $finalPrompt, $image->provider, $image->model, $sourcePaths, $size, $imageDetail);

            if ($image->fresh()->status !== 'pending') {
                if ($sourcePaths !== []) {
                    $image->update([
                        'response_meta' => [
                            'provider' => $image->provider,
                            'model' => $image->model,
                            'source_paths' => $sourcePaths,
                        ],
                    ]);
                }

                return $image->refresh();
            }

            if (!$this->updateImageProgress($image, $requestMeta, 'saving')) {
                if ($sourcePaths !== []) {
                    $image->update([
                        'response_meta' => [
                            'provider' => $image->provider,
                            'model' => $image->model,
                            'source_paths' => $sourcePaths,
                        ],
                    ]);
                }

                return $image->refresh();
            }

            $content = base64_decode($result['base64'], true);

            if ($content === false) {
                throw new \RuntimeException('API trả về ảnh base64 không hợp lệ.');
            }

            $media = $this->storeGeneratedImage($image, $content, $result);
            $storedPath = $media->getPathRelativeToRoot();

            unset($requestMeta['parent_prompt'], $requestMeta['pending_uploads'], $requestMeta['progress']);

            $updated = GeneratedMedia::query()
                ->whereKey($image->id)
                ->where('status', 'pending')
                ->update([
                    'result_path' => $storedPath,
                    'status' => 'succeeded',
                    'request_meta' => $requestMeta,
                    'response_meta' => array_filter([
                        'provider' => $image->provider,
                        'model' => $image->model,
                        'usage' => $result['usage'],
                        'source_paths' => $result['source_paths'],
                        'dimensions' => $result['dimensions'] ?? null,
                    ], fn(mixed $value): bool => $value !== null),
                ]);

            if ($updated === 0) {
                $media->delete();

                if ($sourcePaths !== []) {
                    $image->update([
                        'response_meta' => [
                            'provider' => $image->provider,
                            'model' => $image->model,
                            'source_paths' => $sourcePaths,
                        ],
                    ]);
                }
            }
        } catch (Throwable $e) {
            if ($image->fresh()->status !== 'pending') {
                if ($sourcePaths !== []) {
                    $image->update([
                        'response_meta' => [
                            'provider' => $image->provider,
                            'model' => $image->model,
                            'source_paths' => $sourcePaths,
                        ],
                    ]);
                }

                return $image->refresh();
            }

            if (!$e instanceof \InvalidArgumentException) {
                report($e);
            }

            unset($requestMeta['parent_prompt'], $requestMeta['pending_uploads']);
            $requestMeta['progress'] = 'failed';

            GeneratedMedia::query()
                ->whereKey($image->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error' => Str::limit($e->getMessage(), 2000, ''),
                    'request_meta' => $requestMeta,
                    'response_meta' => $sourcePaths === [] ? $image->response_meta : [
                        'provider' => $image->provider,
                        'model' => $image->model,
                        'source_paths' => $sourcePaths,
                    ],
                ]);
        } finally {
            $this->deletePendingUploads($pendingUploads);
        }

        return $image->refresh();
    }

    /**
     * @param  array{mime: string, dimensions?: array<string, mixed>}  $result
     */
    private function storeGeneratedImage(GeneratedMedia $image, string $content, array $result): Media
    {
        $properties = array_filter([
            'generated' => true,
            'provider' => $image->provider,
            'model' => $image->model,
            'prompt' => $image->prompt,
            'source' => $image->source,
            'parent_id' => $image->parent_id,
            'mime_type' => $result['mime'],
            'width' => data_get($result, 'dimensions.width'),
            'height' => data_get($result, 'dimensions.height'),
            'requested_size' => data_get($result, 'dimensions.requested_size'),
            'original_size' => strlen($content),
        ], fn (mixed $value): bool => $value !== null);

        $media = $image->addMediaFromString($content)
            ->usingFileName(Str::uuid().$this->extensionFor($result['mime']))
            ->withProperties(['mime_type' => $result['mime']])
            ->withCustomProperties($properties)
            ->toMediaCollection('result');

        try {
            $path = $media->getPath();
            OptimizerChainFactory::create((array) config('media-library.image_optimizers'))
                ->throws()
                ->optimize($path);

            clearstatcache(true, $path);
            $optimizedSize = filesize($path);

            if ($optimizedSize === false) {
                throw new \RuntimeException('Không đọc được dung lượng ảnh đã optimize.');
            }

            $media->size = $optimizedSize;
            $media->setCustomProperty('optimized', true);
            $media->setCustomProperty('optimized_size', $media->size);
            $media->setCustomProperty('optimized_at', now()->toISOString());
            $media->save();
        } catch (Throwable $e) {
            $media->delete();

            throw $e;
        }

        return $media;
    }

    /**
     * @param  array<string, mixed>  $requestMeta
     */
    private function updateImageProgress(GeneratedMedia $image, array &$requestMeta, string $progress): bool
    {
        $requestMeta['progress'] = $progress;

        $updated = GeneratedMedia::query()
            ->whereKey($image->id)
            ->where('status', 'pending')
            ->update(['request_meta' => $requestMeta]);

        if ($updated === 0) {
            return false;
        }

        AiImageCompleted::dispatch($image->refresh());

        return true;
    }

    public function publish(GeneratedMedia $image, Request $request, bool $requireOwner = true): GeneratedMedia
    {
        if ($requireOwner && !$this->ownsImage($image, $request)) {
            throw new \InvalidArgumentException('Không tìm thấy ảnh để publish.');
        }

        if ($image->status !== 'succeeded' || !$image->result_path) {
            throw new \InvalidArgumentException('Chỉ publish được ảnh đã tạo xong.');
        }

        $responseMeta = is_array($image->response_meta) ? $image->response_meta : [];

        if (is_string($responseMeta['publish_error'] ?? null) && !Auth::user()?->isAdmin()) {
            throw new \InvalidArgumentException($responseMeta['publish_error']);
        }

        try {
            $review = $this->reviewForPublish($image);
        } catch (\InvalidArgumentException $e) {
            if (in_array($e->getCode(), [self::ERROR_IMAGE_REVIEW_SEXUAL, self::ERROR_IMAGE_REVIEW_POLITICAL], true)) {
                $image->update(['response_meta' => [...$responseMeta, 'publish_error' => $e->getMessage()]]);
            }

            throw $e;
        }

        DB::transaction(function () use ($image, $review): void {
            $image->update([
                'category_id' => $this->classifyCategory($review['category'])->id,
                'title' => $review['title'],
                'description' => $review['description'],
                'is_published' => true,
                'published_at' => $image->published_at ?? now(),
                'response_meta' => collect($image->response_meta ?? [])->except('publish_error')->all(),
            ]);

            $this->syncTags($image, $review['tags']);
        });

        return $image->refresh()->load('tags');
    }

    public function backfillMetadata(GeneratedMedia $image): GeneratedMedia
    {
        $metadata = $this->imageMetadata($image);
        $prompt = (string) $image->prompt;
        $category = is_string($metadata['category'] ?? null) ? $metadata['category'] : 'other';
        $title = $this->imageTitle($metadata['title'] ?? null, $prompt);

        DB::transaction(function () use ($image, $metadata, $prompt, $category, $title): void {
            $image->update([
                'category_id' => $this->classifyCategory($category)->id,
                'title' => $title,
                'description' => $this->imageDescription($metadata['description'] ?? null, $title, $prompt),
            ]);

            $this->syncTags($image, $this->tagNames($metadata['tags'] ?? []));
        });

        return $image->refresh()->load('category', 'tags');
    }

    /**
     * @return Collection<int, GeneratedMedia>
     */
    public function publishedGallery(?Category $category = null, int $limit = 80, string $search = '', string $sort = 'featured', ?Tag $tag = null): Collection
    {
        $search = trim($search);
        $sort = in_array($sort, ['featured', 'new', 'popular'], true) ? $sort : 'featured';

        $query = GeneratedMedia::query()
            ->with(['category', 'user'])
            ->publiclyVisible()
            ->when($category, fn($query) => $query->where('category_id', $category->id))
            ->when($tag, fn($query) => $query->whereHas('tags', fn($query) => $query->whereKey($tag->id)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('prompt', 'like', '%' . $search . '%')
                        ->orWhereHas('category', fn($query) => $query->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('tags', fn($query) => $query->where('name', 'like', '%' . $search . '%'));
                });
            });

        match ($sort) {
            'new' => $query->latest(),
            'popular' => $query->orderByDesc('favorites_count')->latest('published_at'),
            default => $query->orderByDesc('is_featured')->latest('published_at'),
        };

        return $query->limit($limit)->get();
    }

    /**
     * @return Collection<int, GeneratedMedia>
     */
    public function relatedPublished(GeneratedMedia $image, int $limit = 8): Collection
    {
        $tagIds = $image->tags()->pluck('tags.id')->all();

        $query = GeneratedMedia::query()
            ->with(['category', 'user', 'tags'])
            ->publiclyVisible()
            ->whereKeyNot($image->id);

        if ($tagIds !== []) {
            return $query
                ->whereHas('tags', fn($query) => $query->whereIn('tags.id', $tagIds))
                ->withCount(['tags as matching_tags_count' => fn($query) => $query->whereIn('tags.id', $tagIds)])
                ->orderByDesc('matching_tags_count')
                ->latest('published_at')
                ->limit($limit)
                ->get();
        }

        return $query
            ->when($image->category_id, fn($query) => $query->where('category_id', $image->category_id))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function source(Request $request): ?string
    {
        $source = trim((string) $request->input('source', ''));

        if ($source === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9._:-]+$/', $source)) {
            throw new \InvalidArgumentException('Source không hợp lệ.');
        }

        return Str::limit($source, 120, '');
    }

    public function visitorKey(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        return hash('sha256', ($sessionId ?: 'stateless') . '|' . $request->ip());
    }

    public function remainingToday(Request $request): ?int
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return 0;
        }

        if ($user->isAdmin()) {
            return null;
        }

        if ($this->requiresEmailVerificationForImageCreation()) {
            return 0;
        }

        return max(0, $this->dailyLimit() - $this->countToday($request));
    }

    public function isLimitExceeded(Request $request): bool
    {
        return $this->remainingToday($request) === 0;
    }

    public function requiresEmailVerificationForImageCreation(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && !$user->isAdmin()
            && !$user->hasVerifiedEmail()
            && !$user->created_at?->isToday();
    }

    public function countToday(Request $request): int
    {
        $query = GeneratedMedia::query()->disableModelCaching();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        return $query
            ->whereIn('status', ['pending', 'succeeded'])
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function generatedLastDay(): int
    {
        return GeneratedMedia::query()
            ->where('status', 'succeeded')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();
    }

    public function guestImageCount(Request $request): int
    {
        $query = GeneratedMedia::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        return $query
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->count();
    }

    /**
     * @return Collection<int, GeneratedMedia>
     */
    public function guestHistory(Request $request, int $limit = 12): Collection
    {
        $query = GeneratedMedia::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        return $query
            ->with('category')
            ->whereIn('status', ['pending', 'succeeded', 'failed'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function deleteGuestImage(Request $request, int $id): void
    {
        $query = GeneratedMedia::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        $image = $query->find($id);

        if (!$image) {
            return;
        }

        $image->delete();
    }

    public function imageUrl(GeneratedMedia $image, string $size = 'original', ?int $width = null, ?int $height = null): ?string
    {
        if (!$image->result_path) {
            return null;
        }

        if ($width !== null) {
            return $this->thumbUrl($image, $width, $height);
        }

        $configuredSize = self::IMAGE_SIZES[$size] ?? null;

        if ($configuredSize === null) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            return $disk->url($image->result_path);
        }

        if (is_int($configuredSize)) {
            return $this->thumbUrl($image, $configuredSize);
        }

        return $this->thumbUrl($image, $configuredSize[0], $configuredSize[1]);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function imageSize(GeneratedMedia $image, string $size = 'original', ?int $width = null, ?int $height = null): ?array
    {
        if (!$image->result_path) {
            return null;
        }

        if ($width !== null && $height !== null) {
            return ['width' => $width, 'height' => $height];
        }

        $configuredSize = self::IMAGE_SIZES[$size] ?? null;

        if (is_array($configuredSize)) {
            return ['width' => $configuredSize[0], 'height' => $configuredSize[1]];
        }

        $originalSize = $this->originalImageSize($image);

        if (!$originalSize) {
            return null;
        }

        $targetWidth = $width ?? (is_int($configuredSize) ? $configuredSize : $originalSize['width']);

        return [
            'width' => $targetWidth,
            'height' => max(1, (int) round($originalSize['height'] * ($targetWidth / $originalSize['width']))),
        ];
    }

    public function resultUrl(GeneratedMedia $image): ?string
    {
        return $this->imageUrl($image);
    }

    private function thumbUrl(GeneratedMedia $image, int $width, ?int $height = null): string
    {
        $size = $height === null ? "{$width}x" : "{$width}x{$height}";

        return '/thumb_x' . $size . '/storage/' . ltrim($image->result_path ?? '', '/');
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function originalImageSize(GeneratedMedia $image): ?array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $path = $disk->path($image->result_path ?? '');
        $size = @getimagesize($path);

        return $size ? ['width' => $size[0], 'height' => $size[1]] : null;
    }

    /**
     * @return array<int, string>
     */
    /**
     * @param  array<string, mixed>  $requestMeta
     */
    private function referencePrompt(array $requestMeta): string
    {
        $roles = is_array($requestMeta['reference_roles'] ?? null) ? $requestMeta['reference_roles'] : [];

        if (($requestMeta['prompt_contract'] ?? null) !== 'product-detail-v2' || $roles === []) {
            return 'Use the provided reference image as the source. Edit that image according to the instructions. Preserve the original subjects, identities, composition, pose, and count unless explicitly asked to change them. Do not create an unrelated new image.';
        }

        $descriptions = [
            'product' => 'PRIMARY_PRODUCT: authoritative source for the sole product identity and SKU. Preserve its exact shape, construction, proportions, colors, materials, labels, hardware, and distinctive details.',
            'logo' => 'BRAND_LOGO: show this logo in the output. Preserve its text, colors, proportions, and mark as closely as possible. Never use its background or canvas as a product or scene reference.',
            'model' => 'MODEL_IDENTITY: show this same person in the output. Preserve recognizable face, skin tone, hair, body features, and identity. Never transfer human features to the product.',
            'additional_product' => 'SUPPLEMENTAL_PRODUCT_VIEW: another view of the same SKU. Use only to verify geometry, construction, material, texture, and hidden details; never create another product or a hybrid.',
        ];
        $lines = ['REFERENCE IMAGE ROLE CONTRACT', 'Images are ordered exactly as listed. Never merge, swap, or transfer traits between roles.'];

        foreach ($roles as $index => $role) {
            if (is_string($role) && isset($descriptions[$role])) {
                $lines[] = 'Image ' . ($index + 1) . ' — ' . $descriptions[$role];
            }
        }

        $lines[] = 'Generate one coherent product identity. Logo and model must appear when provided. Scene, camera, pose, lighting, layout, and background come from output instructions, not reference backgrounds. Do not create a collage, extra product units, extra people, or variants unless requested. If references conflict, PRIMARY_PRODUCT wins for product identity, BRAND_LOGO only for branding, and MODEL_IDENTITY only for person identity.';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    public function referenceSourcePaths(GeneratedMedia $image): array
    {
        $paths = is_array($image->response_meta) ? ($image->response_meta['source_paths'] ?? []) : [];

        if (!is_array($paths)) {
            return [];
        }

        return array_slice(array_values(array_filter($paths, fn(mixed $path): bool => is_string($path) && $path !== '')), 0, AppSettings::maxReferencePhotos(), true);
    }

    /**
     * @param  array<int, int>  $indexes
     * @return array<int, array{path: string, name: string|null, mime: string|null}>
     */
    private function storeParentReferenceUploads(GeneratedMedia $parent, array $indexes): array
    {
        $sourcePaths = $this->referenceSourcePaths($parent);
        $indexes = array_slice(array_values(array_unique(array_map('intval', $indexes))), 0, AppSettings::maxReferencePhotos());
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $uploads = [];

        foreach ($indexes as $index) {
            $sourcePath = $sourcePaths[$index] ?? null;

            if (!is_string($sourcePath) || !$disk->exists($sourcePath)) {
                continue;
            }

            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
            $path = $this->datedPath('ai-image-pending', Str::uuid() . '.' . $extension);

            if (!$disk->put($path, $disk->get($sourcePath), ['visibility' => 'private'])) {
                throw new \RuntimeException('Không lưu được ảnh tham chiếu.');
            }

            $uploads[] = [
                'path' => $path,
                'name' => basename($sourcePath),
                'mime' => $this->mimeForExtension($extension),
            ];
        }

        return $uploads;
    }

    /**
     * @param  array<int, int>  $imageIds
     * @return array<int, array{path: string, name: string|null, mime: string|null, image_id?: int}>
     */
    private function storeReferenceImageUploads(array $imageIds): array
    {
        if ($imageIds === []) {
            return [];
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $uploads = [];

        $images = GeneratedMedia::query()
            ->whereIn('id', $imageIds)
            ->publiclyVisible()
            ->get()
            ->keyBy('id');

        foreach ($imageIds as $imageId) {
            $image = $images->get($imageId);

            if (!$image || !$image->result_path || !$disk->exists($image->result_path)) {
                continue;
            }

            $extension = pathinfo($image->result_path, PATHINFO_EXTENSION) ?: 'png';
            $path = $this->datedPath('ai-image-pending', Str::uuid() . '.' . $extension);

            if (!$disk->put($path, $disk->get($image->result_path), ['visibility' => 'private'])) {
                throw new \RuntimeException('Không lưu được ảnh tham chiếu.');
            }

            $uploads[] = [
                'path' => $path,
                'name' => basename($image->result_path),
                'mime' => $this->mimeForExtension($extension),
                'image_id' => $image->id,
            ];
        }

        return $uploads;
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, array{path: string, name: string|null, mime: string|null}>
     */
    private function storePendingUploads(array $photos): array
    {
        $uploads = [];
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        foreach ($photos as $photo) {
            $sourcePath = $photo->getRealPath();

            if (!is_string($sourcePath)) {
                throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
            }

            $content = file_get_contents($sourcePath);

            if ($content === false) {
                throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
            }

            $path = $this->datedPath('ai-image-pending', Str::uuid() . '.' . $photo->extension());

            if (!$disk->put($path, $content, ['visibility' => 'private'])) {
                throw new \RuntimeException('Không lưu được ảnh tải lên.');
            }

            $uploads[] = [
                'path' => $path,
                'name' => $photo->getClientOriginalName(),
                'mime' => $photo->getClientMimeType(),
            ];
        }

        return $uploads;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function pendingUploadedFiles(mixed $uploads): array
    {
        if (!is_array($uploads)) {
            return [];
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $files = [];

        foreach ($uploads as $upload) {
            if (!is_array($upload) || !is_string($upload['path'] ?? null)) {
                continue;
            }

            $path = $upload['path'];
            $absolutePath = $disk->path($path);

            if (!is_file($absolutePath)) {
                throw new \RuntimeException('Không tìm thấy ảnh tải lên.');
            }

            $files[] = new UploadedFile(
                $absolutePath,
                is_string($upload['name'] ?? null) ? $upload['name'] : basename($path),
                is_string($upload['mime'] ?? null) ? $upload['mime'] : null,
                test: true,
            );
        }

        return $files;
    }

    private function deletePendingUploads(mixed $uploads): void
    {
        if (!is_array($uploads)) {
            return;
        }

        Storage::disk('public')->delete(array_values(array_filter(array_map(
            fn($upload) => is_array($upload) && is_string($upload['path'] ?? null) ? $upload['path'] : null,
            $uploads,
        ))));
    }

    /**
     * @param  array<int, mixed>  $photos
     * @param  array<int, string>  $sourcePaths
     * @return array{base64: string, mime: string, source_paths: array<int, string>, usage: array<string, mixed>}
     */
    private function generateImage(
        GeneratedMedia $image,
        array $photos,
        string $prompt,
        string $provider,
        string $model,
        array &$sourcePaths = [],
        ?string $size = null,
        ?string $imageDetail = null,
    ): array {
        $sourcePaths = [];
        $referenceImages = [];

        foreach (array_slice($photos, 0, AppSettings::maxReferencePhotos()) as $photo) {
            if (!$photo instanceof UploadedFile) {
                continue;
            }

            $sourceContent = $this->referenceImageContent($photo);
            $sourceMedia = $image->addMediaFromString($sourceContent)
                ->usingFileName(Str::uuid() . '.jpg')
                ->withProperties(['mime_type' => 'image/jpeg'])
                ->toMediaCollection('sources');

            $sourcePaths[] = $sourceMedia->getPathRelativeToRoot();
            $referenceImages[] = 'data:image/jpeg;base64,' . base64_encode($sourceContent);
        }

        $providerConfig = config("ai.providers.$provider");

        if (!is_array($providerConfig)) {
            throw new \RuntimeException("Provider AI [$provider] chưa được cấu hình.");
        }

        $url = rtrim(AppSettings::string('ai.' . $provider . '_url'), '/');
        $key = AppSettings::string('ai.' . $provider . '_api_key');

        if ($url === '' || $key === '') {
            throw new \RuntimeException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        $resolvedSize = is_string($size) && ($size === 'auto' || GptImageOptions::isValidSize($size))
            ? $size
            : AppSettings::string('ai.image_size', (string) config('ai.image_size', 'auto'));
        $resolvedDetail = is_string($imageDetail) && GptImageOptions::isValidImageDetail($imageDetail)
            ? $imageDetail
            : AppSettings::string('ai.image_detail', (string) config('ai.image_detail', 'high'));
        $sizeHint = $this->providerSizePromptHint($resolvedSize);
        $providerPrompt = $sizeHint === '' ? $prompt : rtrim($prompt) . "\n\n" . $sizeHint;

        $payload = [
            'model' => $model,
            'prompt' => $providerPrompt,
            'n' => 1,
            'size' => $resolvedSize,
            'quality' => AppSettings::string('ai.image_quality', (string) config('ai.image_quality', 'auto')),
            'background' => 'auto',
            'image_detail' => $resolvedDetail,
            'output_format' => 'png',
        ];

        if (count($referenceImages) === 1) {
            $payload[AppSettings::string('ai.image_reference_field')] = $referenceImages[0];
        } elseif ($referenceImages !== []) {
            $payload['images'] = $referenceImages;
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)))
            ->post($url . '/images/generations', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('API tạo ảnh lỗi ' . $response->status() . ': ' . Str::limit($response->body(), 1000, ''));
        }

        $data = $response->json();

        $base64 = data_get($data, 'data.0.b64_json');
        $usage = data_get($data, 'usage', []);

        if (!is_string($base64) || $base64 === '') {
            throw new \RuntimeException('API không trả về ảnh base64.');
        }

        $decoded = base64_decode(Str::after($base64, ','), true);

        if ($decoded === false || $decoded === '') {
            throw new \RuntimeException('API trả về ảnh base64 không hợp lệ.');
        }

        $requestedSize = is_string($payload['size'] ?? null) ? $payload['size'] : null;
        $dimensions = $this->measureGeneratedImage($decoded, $requestedSize);

        if ($dimensions['target_width'] !== null && $dimensions['target_height'] !== null && !$dimensions['meets_width_or_height']) {
            throw new \RuntimeException(sprintf(
                'API trả về ảnh %sx%s nhỏ hơn cấu hình %s (cần width >= %s hoặc height >= %s).',
                $dimensions['width'] ?? '?',
                $dimensions['height'] ?? '?',
                $requestedSize,
                $dimensions['target_width'],
                $dimensions['target_height'],
            ));
        }

        return [
            'base64' => Str::after($base64, ','),
            'mime' => is_string($dimensions['mime']) && $dimensions['mime'] !== '' ? $dimensions['mime'] : 'image/png',
            'source_paths' => $sourcePaths,
            'usage' => is_array($usage) ? $usage : [],
            'dimensions' => [
                'requested_size' => $requestedSize,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'target_width' => $dimensions['target_width'],
                'target_height' => $dimensions['target_height'],
                'meets_width_or_height' => $dimensions['meets_width_or_height'],
                'resized' => false,
            ],
        ];
    }

    public function dailyLimit(): int
    {
        return max(0, AppSettings::int('auth.verified_daily_image_limit', 5));
    }

    public function rewritePrompt(string $prompt, string $instruction = ''): string
    {
        $prompt = trim($prompt);
        $instruction = trim($instruction);

        if ($prompt === '' && $instruction === '') {
            throw new \InvalidArgumentException('Prompt hoặc chỉ dẫn viết lại là bắt buộc.');
        }

        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = $this->textModel('ai.prompt_rewrite_model', (string) config('ai.prompt_rewrite_model', 'gpt-5.5'));

        $this->configureReviewProvider($provider);

        try {
            $response = PromptRewriteAgent::make()->prompt(
                trim("Viết lại prompt tạo ảnh sau để dùng được ngay.\n\nPrompt:\n{$prompt}\n\nChỉ dẫn thêm:\n{$instruction}"),
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không viết lại được prompt. Vui lòng thử lại sau.');
        }

        $data = $response instanceof Arrayable ? $response->toArray() : [];
        $rewritten = trim(is_string($data['prompt'] ?? null) ? $data['prompt'] : $response->text);

        if ($rewritten === '') {
            throw new \InvalidArgumentException('Không viết lại được prompt. Vui lòng thử lại sau.');
        }

        return Str::limit($rewritten, 2000, '');
    }

    public function translatePrompt(string $prompt): string
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt là bắt buộc.');
        }

        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = $this->textModel('ai.prompt_translation_model', (string) config('ai.prompt_translation_model', 'gpt-5.5'));

        $this->configureReviewProvider($provider);

        try {
            $response = PromptTranslationAgent::make()->prompt(
                "Dịch prompt tạo ảnh sau sang tiếng Việt.\n\nPrompt:\n{$prompt}",
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không dịch được prompt. Vui lòng thử lại sau.');
        }

        $data = $response instanceof Arrayable ? $response->toArray() : [];
        $translated = trim(is_string($data['prompt'] ?? null) ? $data['prompt'] : $response->text);

        if ($translated === '') {
            throw new \InvalidArgumentException('Không dịch được prompt. Vui lòng thử lại sau.');
        }

        return Str::limit($translated, 2000, '');
    }

    public function promptFromImage(UploadedFile $photo): string
    {
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = $this->textModel('ai.image_to_prompt_model', (string) config('ai.image_to_prompt_model', 'gpt-5.5'));
        $attachment = new Base64Image(base64_encode($this->referenceImageContent($photo)), 'image/jpeg');

        $this->configureReviewProvider($provider);

        try {
            $response = ImageToPromptAgent::make()->prompt(
                'Phân tích ảnh đính kèm và tạo prompt tạo ảnh dùng được ngay.',
                attachments: [$attachment],
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không tạo được prompt từ ảnh. Vui lòng thử lại sau.');
        }

        $data = $response instanceof Arrayable ? $response->toArray() : [];
        $prompt = trim(is_string($data['prompt'] ?? null) ? $data['prompt'] : $response->text);

        if ($prompt === '') {
            throw new \InvalidArgumentException('Không tạo được prompt từ ảnh. Vui lòng thử lại sau.');
        }

        return $prompt;
    }

    private function classifyCategory(string $slug): Category
    {
        $categoryNames = $this->categoryNames();
        $slug = array_key_exists($slug, $categoryNames) ? $slug : $this->fallbackCategorySlug($categoryNames);

        return Category::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return array{allowed: bool, reason: string}
     */
    private function reviewForCreation(string $prompt, array $photos = []): array
    {
        $review = $this->reviewPrompt($prompt, publish: false, attachments: $this->reviewUploadedFileAttachments($photos));
        $reason = is_string($review['reason'] ?? null) ? $review['reason'] : '';

        return ['allowed' => true, 'reason' => Str::limit($reason, 500, '')];
    }

    /**
     * @return array{allowed: bool, title: string, description: string, category: string, tags: list<string>, reason: string}
     */
    private function reviewForPublish(GeneratedMedia $image): array
    {
        $prompt = (string) $image->prompt;
        $review = $this->reviewPrompt($prompt, publish: true, image: $image);
        $metadata = $this->imageMetadata($image);
        $category = is_string($metadata['category'] ?? null) ? $metadata['category'] : 'other';
        $categoryNames = $this->categoryNames();
        $title = $this->imageTitle($metadata['title'] ?? null, $prompt);

        return [
            'allowed' => true,
            'title' => $title,
            'description' => $this->imageDescription($metadata['description'] ?? null, $title, $prompt),
            'category' => array_key_exists($category, $categoryNames) ? $category : $this->fallbackCategorySlug($categoryNames),
            'tags' => $this->tagNames($metadata['tags'] ?? []),
            'reason' => Str::limit(is_string($review['reason'] ?? null) ? $review['reason'] : '', 500, ''),
        ];
    }

    /** @return array<string, mixed> */
    private function imageMetadata(GeneratedMedia $image): array
    {
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = $this->textModel('ai.tag_model', (string) config('ai.tag_model', config('ai.image_review_model', 'gpt-5.5')));
        $this->configureReviewProvider($provider);

        try {
            $response = ImageMetadataAgent::make()->prompt(
                "Tạo metadata để publish ảnh sau.\n\nPrompt:\n" . (string) $image->prompt,
                attachments: $this->reviewImageAttachments($image),
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không tạo được metadata ảnh. Vui lòng thử lại sau.');
        }

        return $response instanceof Arrayable ? $response->toArray() : [];
    }

    /**
     * @param  list<Base64Image>  $attachments
     * @return array<string, mixed>
     */
    private function reviewPrompt(string $prompt, bool $publish, ?GeneratedMedia $image = null, array $attachments = []): array
    {
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = $this->textModel('ai.image_review_model', (string) config('ai.image_review_model', 'gpt-5.5'));

        $this->configureReviewProvider($provider);

        $attachments = $image ? $this->reviewImageAttachments($image) : $attachments;
        $prefix = $publish
            ? ($attachments === []
                ? "Duyệt prompt để publish ảnh, tạo title, chọn danh mục và tags phù hợp.\n\nPrompt:\n"
                : "Duyệt ảnh kèm để publish, tạo title, chọn danh mục và tags phù hợp.\n\nPrompt:\n")
            : ($attachments === []
                ? "Duyệt prompt tạo ảnh sau.\n\nPrompt:\n"
                : "Duyệt ảnh tham chiếu và prompt trước khi tạo ảnh.\n\nPrompt:\n");

        try {
            $response = ImageReviewAgent::make()->prompt(
                $prefix . $prompt,
                attachments: $attachments,
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không duyệt được prompt ảnh. Vui lòng thử lại sau.', self::ERROR_IMAGE_REVIEW_UNAVAILABLE);
        }

        $review = $response instanceof Arrayable ? $response->toArray() : [];
        $blockedPolicy = is_string($review['blocked_policy'] ?? null) ? $review['blocked_policy'] : 'none';
        $errorCode = match ($blockedPolicy) {
            'sexual' => self::ERROR_IMAGE_REVIEW_SEXUAL,
            'political' => self::ERROR_IMAGE_REVIEW_POLITICAL,
            default => null,
        };

        if ($errorCode !== null) {
            throw new \InvalidArgumentException('Prompt không phù hợp để tạo hoặc publish ảnh.', $errorCode);
        }

        $review['allowed'] = true;

        return $review;
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<Base64Image>
     */
    private function reviewUploadedFileAttachments(array $photos): array
    {
        return array_map(
            fn(UploadedFile $photo): Base64Image => new Base64Image(base64_encode($this->referenceImageContent($photo)), 'image/jpeg'),
            $photos,
        );
    }

    /**
     * @return list<Base64Image>
     */
    private function reviewImageAttachments(GeneratedMedia $image): array
    {
        if (!is_string($image->result_path) || $image->result_path === '') {
            return [];
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if (!$disk->exists($image->result_path)) {
            return [];
        }

        return $this->reviewUploadedFileAttachments([
            new UploadedFile(
                $disk->path($image->result_path),
                basename($image->result_path),
            )
        ]);
    }

    private function textModel(string $overrideKey, string $fallback): string
    {
        $override = AppSettings::string($overrideKey);

        if ($override !== '') {
            return $override;
        }

        return AppSettings::string('ai.text_model', (string) config('ai.text_model', $fallback)) ?: $fallback;
    }

    private function configureReviewProvider(string $provider): void
    {
        $url = rtrim(AppSettings::string('ai.' . $provider . '_url'), '/');
        $key = AppSettings::string('ai.' . $provider . '_api_key');

        if ($url === '' || $key === '') {
            throw new \InvalidArgumentException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        config([
            "ai.providers.$provider.driver" => 'openrouter',
            "ai.providers.$provider.key" => $key,
            "ai.providers.$provider.url" => $url,
        ]);
        Ai::forgetInstance($provider);
    }

    private function ownsImage(GeneratedMedia $image, Request $request): bool
    {
        return Auth::check()
            ? $image->user_id === Auth::id()
            : $image->visitor_key === $this->visitorKey($request);
    }

    private function datedPath(string $directory, string $filename): string
    {
        return $directory . '/' . now()->format('Ym/d') . '/' . $filename;
    }

    private function imageTitle(mixed $title, string $prompt): string
    {
        $title = is_string($title) ? $title : '';
        $title = $this->readableTitle($title) ?? $this->readableTitle($prompt) ?? '';
        $title = Str::of($title)->squish()->limit(80, '')->toString();

        return $title !== '' ? $title : 'Ảnh AI';
    }

    private function imageDescription(mixed $description, string $title, string $prompt): string
    {
        $description = is_string($description) ? Str::of($description)->squish()->limit(300, '')->toString() : '';

        if ($description !== '') {
            return $description;
        }

        $fallback = $title !== '' && $title !== 'Ảnh AI'
            ? $title
            : ($this->readableTitle($prompt) ?? $prompt);

        return Str::of($fallback)->squish()->limit(160, '')->toString();
    }

    private function readableTitle(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $this->readableTitleFromArray($decoded);
        }

        if (preg_match('/["\']?(?:title|render_goal|goal|description|prompt)["\']?\s*[:=]\s*["\']([^"\']{3,160})/u', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $this->looksLikeStructuredPrompt($text) ? null : $text;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function readableTitleFromArray(array $data): ?string
    {
        foreach (['title', 'render_goal', 'goal', 'description', 'prompt'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $title = $this->readableTitleFromArray($value);

                if ($title !== null) {
                    return $title;
                }
            }

            if (is_string($value) && trim($value) !== '' && !$this->looksLikeStructuredPrompt($value)) {
                return trim($value);
            }
        }

        return null;
    }

    private function looksLikeStructuredPrompt(string $text): bool
    {
        $text = trim($text);

        return $text !== '' && (
            Str::startsWith($text, ['{', '[', '```', '<?', '<script'])
            || preg_match('/(?:^|\n)\s*(?:function|class|const|let|var|def)\s/u', $text) === 1
            || preg_match('/["\']?[A-Za-z_][\w-]*["\']?\s*[:=]\s*[\[{"\']/u', $text) === 1
        );
    }

    /**
     * @return array<string, string>
     */
    private function categoryNames(): array
    {
        return Category::query()
            ->active()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * @param  array<string, string>  $categoryNames
     */
    private function fallbackCategorySlug(array $categoryNames): string
    {
        return array_key_exists('other', $categoryNames) ? 'other' : (string) array_key_first($categoryNames);
    }

    /**
     * @return list<string>
     */
    private function tagNames(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $names = [];

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            $name = Str::of($tag)->lower()->squish()->limit(40, '')->toString();
            $slug = $this->tagSlug($name);

            if ($name !== '' && $slug !== '') {
                $names[$slug] = $name;
            }
        }

        return array_slice(array_values($names), 0, 7);
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(GeneratedMedia $image, array $tags): void
    {
        $tagNames = collect($tags)->mapWithKeys(fn(string $name): array => [$this->tagSlug($name) => $name]);
        $existing = Tag::query()
            ->whereIn('slug', $tagNames->keys())
            ->pluck('id', 'slug');

        $ids = $tagNames
            ->map(function (string $name, string $slug) use ($existing): int {
                if (isset($existing[$slug])) {
                    return (int) $existing[$slug];
                }

                $tag = Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

                if ($tag->wasRecentlyCreated) {
                    GenerateTagDescription::dispatch($tag->id)->afterCommit();
                }

                return (int) $tag->id;
            })
            ->values()
            ->all();

        $image->tags()->sync($ids);
    }

    private function tagSlug(string $name): string
    {
        return Str::slug($name, '-', 'vi');
    }

    private function referenceImageContent(UploadedFile $photo): string
    {
        $path = $photo->getRealPath();

        if (!is_string($path)) {
            throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
        }

        $info = @getimagesize($path);
        $mime = is_array($info) ? $info['mime'] : (string) $photo->getMimeType();
        $image = $this->imageFromPath($path, $mime);

        if (!$image) {
            throw new \InvalidArgumentException('Định dạng ảnh chưa hỗ trợ. Hãy dùng JPG, PNG, WEBP hoặc AVIF.');
        }

        $image = $this->orientImage($image, $path, $mime);
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = max(1, min($width, self::REFERENCE_MAX_WIDTH));
        $targetHeight = max(1, (int) round($height * $targetWidth / $width));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$canvas) {
            imagedestroy($image);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white === false || !imagefill($canvas, 0, 0, $white)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        if (!imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        imagedestroy($image);

        ob_start();
        $encoded = imagejpeg($canvas, null, self::REFERENCE_JPEG_QUALITY);
        $content = ob_get_clean();
        imagedestroy($canvas);

        if (!$encoded) {
            throw new \RuntimeException('Không nén được ảnh nguồn.');
        }

        return $content;
    }

    private function imageFromPath(string $path, string $mime): ?GdImage
    {
        $image = match ($mime) {
            'image/jpeg', 'image/pjpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function orientImage(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if ($orientation === 1) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated instanceof GdImage && $rotated !== $image) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /**
     * @return array{width: ?int, height: ?int, mime: ?string, target_width: ?int, target_height: ?int, meets_width_or_height: bool, ratio_delta: ?float}
     */
    private function measureGeneratedImage(string $binary, ?string $requestedSize): array
    {
        $target = $this->parsePixelSize($requestedSize);
        $info = @getimagesizefromstring($binary);
        $width = is_array($info) ? (int) $info[0] : null;
        $height = is_array($info) ? (int) $info[1] : null;
        $mime = is_array($info) ? (string) ($info['mime'] ?? 'image/png') : 'image/png';
        $targetWidth = $target[0] ?? null;
        $targetHeight = $target[1] ?? null;
        // Only enforce when provider returned a measurable image; never crop/resize.
        $meets = $targetWidth === null || $targetHeight === null || $width === null || $height === null
            || $width >= $targetWidth
            || $height >= $targetHeight;
        $ratioDelta = null;

        if ($width && $height && $targetWidth && $targetHeight) {
            $ratioDelta = abs(($width / $height) - ($targetWidth / $targetHeight));
        }

        return [
            'width' => $width,
            'height' => $height,
            'mime' => $mime !== '' ? $mime : 'image/png',
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
            'meets_width_or_height' => $meets,
            'ratio_delta' => $ratioDelta,
        ];
    }

    private function providerSizePromptHint(?string $size): string
    {
        $pixels = $this->parsePixelSize($size);

        if ($pixels === null) {
            if ($size === 'auto' || $size === null || $size === '') {
                return '';
            }

            return 'Generate the final image at size ' . $size . '. Do not change the creative subject.';
        }

        [$width, $height] = $pixels;
        $ratio = round($width / max(1, $height), 4);
        $orientation = $width === $height ? 'square' : ($width > $height ? 'landscape' : 'portrait');
        $defaults = GptImageOptions::defaultsFromSettings(sprintf('%dx%d', $width, $height));
        $aspect = $defaults['aspect_ratio'] === 'auto' ? sprintf('%d:%d', $width, $height) : $defaults['aspect_ratio'];
        $resolution = strtoupper($defaults['resolution']);

        return implode(' ', [
            "Output canvas {$orientation} aspect ratio {$aspect} ({$width}x{$height} pixels, {$resolution}).",
            "The rendered image width must be at least {$width}px or height at least {$height}px.",
            'Prefer exact pixel size when the model supports it. Do not letterbox with empty borders.',
            'Do not change the creative subject.',
        ]);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parsePixelSize(?string $size): ?array
    {
        if (!is_string($size) || !preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return null;
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];

        if ($width < 1 || $height < 1) {
            return null;
        }

        return [$width, $height];
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/webp' => '.webp',
            default => '.png',
        };
    }

    private function mimeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'image/png',
        };
    }
}
