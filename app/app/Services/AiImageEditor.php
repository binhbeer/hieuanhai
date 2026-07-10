<?php

namespace App\Services;

use App\Ai\ImageReviewAgent;
use App\Ai\PromptRewriteAgent;
use App\Events\AiImageCompleted;
use App\Models\AiImage;
use App\Models\AiTag;
use App\Models\Category;
use App\Models\User;
use App\Support\AppSettings;
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
use Throwable;

class AiImageEditor
{
    private const REFERENCE_MAX_WIDTH = 1024;

    private const REFERENCE_JPEG_QUALITY = 88;

    private const REGISTERED_DAILY_LIMIT = 5;

    /**
     * @param  array<int, mixed>  $photos
     */
    public function create(Request $request, array $photos, string $prompt): AiImage
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt là bắt buộc.');
        }

        $this->reviewForCreation($prompt);
        $photos = array_values(array_filter($photos, fn ($item) => $item instanceof UploadedFile));
        $photo = $photos[0] ?? null;
        $visitorKey = $this->visitorKey($request);
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default_for_images', 'openai'));
        $model = AppSettings::string('ai.image_model', (string) config('ai.image_model', 'cx/gpt-5.5-image'));
        $finalPrompt = trim(implode("\n\n", array_filter([
            $photos === [] ? null : 'Use the provided reference image as the source. Edit that image according to the instructions. Preserve the original subjects, identities, composition, pose, and count unless explicitly asked to change them. Do not create an unrelated new image.',
            $prompt,
        ])));

        $image = AiImage::create([
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
            $result = $this->generateImage($photos, $finalPrompt, $provider, $model);
            $storedPath = $this->datedPath('ai-images', Str::uuid().$this->extensionFor($result['mime']));
            $content = base64_decode($result['base64'], true);

            if ($content === false) {
                throw new \RuntimeException('API trả về ảnh base64 không hợp lệ.');
            }

            if (! Storage::disk('public')->put($storedPath, $content, ['visibility' => 'public'])) {
                throw new \RuntimeException('Không lưu được ảnh đã tạo.');
            }

            $image->update([
                'source_path' => $result['source_path'],
                'result_path' => $storedPath,
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
     */
    public function createPending(Request $request, array $photos, string $prompt, array $referenceImageIds = []): AiImage
    {
        if (! Auth::check()) {
            throw new \InvalidArgumentException('Vui lòng đăng nhập để tạo ảnh.');
        }

        if ($this->requiresEmailVerificationForImageCreation()) {
            throw new \InvalidArgumentException('Vui lòng xác minh email để tiếp tục nhận lượt tạo ảnh hằng ngày sau ngày đăng ký đầu tiên.');
        }

        if ($this->isLimitExceeded($request)) {
            throw new \InvalidArgumentException('Bạn đã dùng hết lượt tạo ảnh hôm nay.');
        }

        $userId = Auth::id();

        return DB::transaction(function () use ($request, $photos, $prompt, $referenceImageIds, $userId) {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (AiImage::query()->where('user_id', $userId)->where('status', 'pending')->exists()) {
                throw new \InvalidArgumentException('Bạn đang có ảnh đang tạo. Vui lòng chờ ảnh hiện tại hoàn tất.');
            }

            $prompt = trim($prompt);

            if ($prompt === '') {
                throw new \InvalidArgumentException('Prompt là bắt buộc.');
            }

            $referenceImageIds = array_slice(array_values(array_unique(array_map('intval', $referenceImageIds))), 0, 3);
            $referenceUploads = $this->storeReferenceImageUploads($referenceImageIds);
            $photos = array_slice(array_values(array_filter($photos, fn ($item) => $item instanceof UploadedFile)), 0, max(0, 3 - count($referenceUploads)));
            $pendingUploads = [...$referenceUploads, ...$this->storePendingUploads($photos)];
            $photo = $photos[0] ?? null;

            return AiImage::create([
                'user_id' => $userId,
                'visitor_key' => $this->visitorKey($request),
                'ip_address' => $request->ip(),
                'prompt' => $prompt,
                'provider' => AppSettings::string('ai.image_provider', (string) config('ai.default_for_images', 'openai')),
                'model' => AppSettings::string('ai.image_model', (string) config('ai.image_model', 'cx/gpt-5.5-image')),
                'status' => 'pending',
                'request_meta' => [
                    'upload_name' => $photo?->getClientOriginalName(),
                    'upload_mime' => $photo?->getClientMimeType(),
                    'upload_size' => $photo?->getSize(),
                    'upload_count' => count($pendingUploads),
                    'reference_image_ids' => array_values(array_filter(array_map(fn (array $upload) => $upload['image_id'] ?? null, $referenceUploads))),
                    'pending_uploads' => $pendingUploads,
                    'progress' => 'queued',
                ],
            ]);
        });
    }

    public function completePending(AiImage $image): AiImage
    {
        if ($image->status !== 'pending') {
            return $image->refresh();
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $pendingUploads = $requestMeta['pending_uploads'] ?? [];

        try {
            $photos = $this->pendingUploadedFiles($pendingUploads);
            $finalPrompt = trim(implode("\n\n", array_filter([
                $photos === [] ? null : 'Use the provided reference image as the source. Edit that image according to the instructions. Preserve the original subjects, identities, composition, pose, and count unless explicitly asked to change them. Do not create an unrelated new image.',
                $image->prompt,
            ])));

            $this->updateImageProgress($image, $requestMeta, 'reviewing');
            $this->reviewForCreation($image->prompt);
            $this->updateImageProgress($image, $requestMeta, 'generating');
            $result = $this->generateImage($photos, $finalPrompt, $image->provider, $image->model);
            $this->updateImageProgress($image, $requestMeta, 'saving');
            $storedPath = $this->datedPath('ai-images', Str::uuid().$this->extensionFor($result['mime']));
            $content = base64_decode($result['base64'], true);

            if ($content === false) {
                throw new \RuntimeException('API trả về ảnh base64 không hợp lệ.');
            }

            if (! Storage::disk('public')->put($storedPath, $content, ['visibility' => 'public'])) {
                throw new \RuntimeException('Không lưu được ảnh đã tạo.');
            }

            unset($requestMeta['pending_uploads'], $requestMeta['progress']);

            $image->update([
                'source_path' => $result['source_path'],
                'result_path' => $storedPath,
                'status' => 'succeeded',
                'request_meta' => $requestMeta,
                'response_meta' => [
                    'provider' => $image->provider,
                    'model' => $image->model,
                    'usage' => $result['usage'],
                    'source_paths' => $result['source_paths'],
                ],
            ]);
        } catch (Throwable $e) {
            if (! $e instanceof \InvalidArgumentException) {
                report($e);
            }

            unset($requestMeta['pending_uploads']);
            $requestMeta['progress'] = 'failed';

            $image->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 2000, ''),
                'request_meta' => $requestMeta,
            ]);
        } finally {
            $this->deletePendingUploads($pendingUploads);
        }

        return $image->refresh();
    }

    /**
     * @param  array<string, mixed>  $requestMeta
     */
    private function updateImageProgress(AiImage $image, array &$requestMeta, string $progress): void
    {
        $requestMeta['progress'] = $progress;

        $image->update(['request_meta' => $requestMeta]);
        AiImageCompleted::dispatch($image->refresh());
    }

    public function publish(AiImage $image, Request $request, bool $requireOwner = true): AiImage
    {
        if ($requireOwner && ! $this->ownsImage($image, $request)) {
            throw new \InvalidArgumentException('Không tìm thấy ảnh để publish.');
        }

        if ($image->status !== 'succeeded' || ! $image->result_path) {
            throw new \InvalidArgumentException('Chỉ publish được ảnh đã tạo xong.');
        }

        $review = $this->reviewForPublish($image->prompt);

        DB::transaction(function () use ($image, $review): void {
            $image->update([
                'category_id' => $this->classifyCategory($review['category'])->id,
                'title' => $review['title'],
                'is_published' => true,
                'published_at' => $image->published_at ?? now(),
            ]);

            $this->syncTags($image, $review['tags']);
        });

        return $image->refresh()->load('tags');
    }

    /**
     * @return Collection<int, AiImage>
     */
    public function publishedGallery(?Category $category = null, int $limit = 80, string $search = '', string $sort = 'featured', ?AiTag $tag = null): Collection
    {
        $search = trim($search);
        $sort = in_array($sort, ['featured', 'new', 'popular'], true) ? $sort : 'featured';

        $query = AiImage::query()
            ->with(['category', 'user'])
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereKey($tag->id)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('prompt', 'like', '%'.$search.'%')
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('tags', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
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
     * @return Collection<int, AiImage>
     */
    public function relatedPublished(AiImage $image, int $limit = 8): Collection
    {
        $tagIds = $image->tags()->pluck('ai_tags.id')->all();

        $query = AiImage::query()
            ->with(['category', 'user', 'tags'])
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->whereKeyNot($image->id);

        if ($tagIds !== []) {
            return $query
                ->whereHas('tags', fn ($query) => $query->whereIn('ai_tags.id', $tagIds))
                ->withCount(['tags as matching_tags_count' => fn ($query) => $query->whereIn('ai_tags.id', $tagIds)])
                ->orderByDesc('matching_tags_count')
                ->latest('published_at')
                ->limit($limit)
                ->get();
        }

        return $query
            ->when($image->category_id, fn ($query) => $query->where('category_id', $image->category_id))
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

        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $source)) {
            throw new \InvalidArgumentException('Source không hợp lệ.');
        }

        return Str::limit($source, 120, '');
    }

    public function visitorKey(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        return hash('sha256', ($sessionId ?: 'stateless').'|'.$request->ip());
    }

    public function remainingToday(Request $request): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
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
            && ! $user->isAdmin()
            && ! $user->hasVerifiedEmail()
            && ! $user->created_at?->isToday();
    }

    public function countToday(Request $request): int
    {
        $query = AiImage::query();

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
        return AiImage::query()
            ->where('status', 'succeeded')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();
    }

    public function guestImageCount(Request $request): int
    {
        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        return $query
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->count();
    }

    /**
     * @return Collection<int, AiImage>
     */
    public function guestHistory(Request $request, int $limit = 12): Collection
    {
        $query = AiImage::query();

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
        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $this->visitorKey($request));

        $image = $query->find($id);

        if (! $image) {
            return;
        }

        $sourcePaths = is_array($image->response_meta) ? ($image->response_meta['source_paths'] ?? []) : [];

        Storage::disk('public')->delete(array_values(array_filter([...$sourcePaths, $image->source_path, $image->result_path])));
        $image->delete();
    }

    public function resultUrl(AiImage $image): ?string
    {
        if (! $image->result_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($image->result_path);
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

        $images = AiImage::query()
            ->whereIn('id', $imageIds)
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->get()
            ->keyBy('id');

        foreach ($imageIds as $imageId) {
            $image = $images->get($imageId);

            if (! $image || ! $image->result_path || ! $disk->exists($image->result_path)) {
                continue;
            }

            $extension = pathinfo($image->result_path, PATHINFO_EXTENSION) ?: 'png';
            $path = $this->datedPath('ai-image-pending', Str::uuid().'.'.$extension);

            if (! $disk->put($path, $disk->get($image->result_path), ['visibility' => 'private'])) {
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

            if (! is_string($sourcePath)) {
                throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
            }

            $content = file_get_contents($sourcePath);

            if ($content === false) {
                throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
            }

            $path = $this->datedPath('ai-image-pending', Str::uuid().'.'.$photo->extension());

            if (! $disk->put($path, $content, ['visibility' => 'private'])) {
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
        if (! is_array($uploads)) {
            return [];
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $files = [];

        foreach ($uploads as $upload) {
            if (! is_array($upload) || ! is_string($upload['path'] ?? null)) {
                continue;
            }

            $path = $upload['path'];
            $absolutePath = $disk->path($path);

            if (! is_file($absolutePath)) {
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
        if (! is_array($uploads)) {
            return;
        }

        Storage::disk('public')->delete(array_values(array_filter(array_map(
            fn ($upload) => is_array($upload) && is_string($upload['path'] ?? null) ? $upload['path'] : null,
            $uploads,
        ))));
    }

    /**
     * @param  array<int, mixed>  $photos
     * @return array{base64: string, mime: string, source_path: string|null, source_paths: array<int, string>, usage: array<string, mixed>}
     */
    private function generateImage(array $photos, string $prompt, string $provider, string $model): array
    {
        $sourcePaths = [];
        $referenceImages = [];

        foreach (array_slice($photos, 0, 3) as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            $sourceContent = $this->referenceImageContent($photo);
            $sourcePath = $this->datedPath('ai-image-sources', Str::uuid().'.jpg');

            if (! Storage::disk('public')->put($sourcePath, $sourceContent, ['visibility' => 'public'])) {
                throw new \RuntimeException('Không lưu được ảnh nguồn.');
            }

            $sourcePaths[] = $sourcePath;
            $referenceImages[] = 'data:image/jpeg;base64,'.base64_encode($sourceContent);
        }

        $providerConfig = config("ai.providers.$provider");

        if (! is_array($providerConfig)) {
            throw new \RuntimeException("Provider AI [$provider] chưa được cấu hình.");
        }

        $url = rtrim(AppSettings::string('ai.'.$provider.'_url'), '/');
        $key = AppSettings::string('ai.'.$provider.'_api_key');

        if ($url === '' || $key === '') {
            throw new \RuntimeException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => AppSettings::string('ai.image_size', (string) config('ai.image_size', 'auto')),
            'quality' => AppSettings::string('ai.image_quality', (string) config('ai.image_quality', 'auto')),
            'background' => 'auto',
            'image_detail' => AppSettings::string('ai.image_detail', (string) config('ai.image_detail', 'high')),
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
            ->post($url.'/images/generations', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('API tạo ảnh lỗi '.$response->status().': '.Str::limit($response->body(), 1000, ''));
        }

        $data = $response->json();

        $base64 = data_get($data, 'data.0.b64_json');
        $usage = data_get($data, 'usage', []);

        if (! is_string($base64) || $base64 === '') {
            throw new \RuntimeException('API không trả về ảnh base64.');
        }

        return [
            'base64' => Str::after($base64, ','),
            'mime' => 'image/png',
            'source_path' => $sourcePaths[0] ?? null,
            'source_paths' => $sourcePaths,
            'usage' => is_array($usage) ? $usage : [],
        ];
    }

    public function dailyLimit(): int
    {
        return self::REGISTERED_DAILY_LIMIT;
    }

    public function rewritePrompt(string $prompt, string $instruction = ''): string
    {
        $prompt = trim($prompt);
        $instruction = trim($instruction);

        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt là bắt buộc.');
        }

        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = AppSettings::string('ai.prompt_rewrite_model', (string) config('ai.prompt_rewrite_model', 'gpt-5.5'));

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

    private function classifyCategory(string $slug): Category
    {
        $categoryNames = $this->categoryNames();
        $slug = array_key_exists($slug, $categoryNames) ? $slug : $this->fallbackCategorySlug($categoryNames);

        return Category::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    private function reviewForCreation(string $prompt): array
    {
        $review = $this->reviewPrompt($prompt, publish: false);
        $reason = is_string($review['reason'] ?? null) ? $review['reason'] : '';

        return ['allowed' => true, 'reason' => Str::limit($reason, 500, '')];
    }

    /**
     * @return array{allowed: bool, title: string, category: string, tags: list<string>, reason: string}
     */
    private function reviewForPublish(string $prompt): array
    {
        $review = $this->reviewPrompt($prompt, publish: true);
        $title = $this->imageTitle($review['title'] ?? null, $prompt);
        $category = is_string($review['category'] ?? null) ? $review['category'] : 'other';
        $reason = is_string($review['reason'] ?? null) ? $review['reason'] : '';
        $tags = $this->tagNames($review['tags'] ?? []);

        $categoryNames = $this->categoryNames();

        return [
            'allowed' => true,
            'title' => $title,
            'category' => array_key_exists($category, $categoryNames) ? $category : $this->fallbackCategorySlug($categoryNames),
            'tags' => $tags,
            'reason' => Str::limit($reason, 500, ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewPrompt(string $prompt, bool $publish): array
    {
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = AppSettings::string('ai.image_review_model', (string) config('ai.image_review_model', 'gpt-5.5'));

        $this->configureReviewProvider($provider);

        try {
            $response = ImageReviewAgent::make(publish: $publish)->prompt(
                ($publish ? "Duyệt prompt để publish ảnh, tạo title, chọn danh mục và tags phù hợp.\n\nPrompt:\n" : "Duyệt prompt tạo ảnh sau.\n\nPrompt:\n").$prompt,
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new \InvalidArgumentException('Không duyệt được prompt ảnh. Vui lòng thử lại sau.');
        }

        $review = $response instanceof Arrayable ? $response->toArray() : [];
        $blockedPolicy = is_string($review['blocked_policy'] ?? null) ? $review['blocked_policy'] : 'none';

        if (in_array($blockedPolicy, ['sexual', 'political'], true)) {
            throw new \InvalidArgumentException('Prompt không phù hợp để tạo hoặc publish ảnh.');
        }

        $review['allowed'] = true;

        return $review;
    }

    private function configureReviewProvider(string $provider): void
    {
        $url = rtrim(AppSettings::string('ai.'.$provider.'_url'), '/');
        $key = AppSettings::string('ai.'.$provider.'_api_key');

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

    private function ownsImage(AiImage $image, Request $request): bool
    {
        return Auth::check()
            ? $image->user_id === Auth::id()
            : $image->visitor_key === $this->visitorKey($request);
    }

    private function datedPath(string $directory, string $filename): string
    {
        return $directory.'/'.now()->format('Ym/d').'/'.$filename;
    }

    private function imageTitle(mixed $title, string $prompt): string
    {
        $title = is_string($title) ? $title : $prompt;
        $title = Str::of($title)->squish()->limit(80, '')->toString();

        return $title !== '' ? $title : Str::limit($prompt, 80, '');
    }

    /**
     * @return array<string, string>
     */
    private function categoryNames(): array
    {
        return Category::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
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
        if (! is_array($tags)) {
            return [];
        }

        $names = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $name = Str::of($tag)->lower()->squish()->limit(40, '')->toString();
            $slug = $this->tagSlug($name);

            if ($name !== '' && $slug !== '') {
                $names[$slug] = $name;
            }
        }

        return array_slice(array_values($names), 0, 5);
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(AiImage $image, array $tags): void
    {
        $tagNames = collect($tags)->mapWithKeys(fn (string $name): array => [$this->tagSlug($name) => $name]);
        $existing = AiTag::query()
            ->whereIn('slug', $tagNames->keys())
            ->pluck('id', 'slug');

        $ids = $tagNames
            ->map(fn (string $name, string $slug): int => (int) ($existing[$slug] ?? AiTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            )->id))
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

        if (! is_string($path)) {
            throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
        }

        $info = @getimagesize($path);
        $mime = is_array($info) ? $info['mime'] : (string) $photo->getMimeType();
        $image = $this->imageFromPath($path, $mime);

        if (! $image) {
            throw new \InvalidArgumentException('Định dạng ảnh chưa hỗ trợ. Hãy dùng JPG, PNG, WEBP hoặc AVIF.');
        }

        $image = $this->orientImage($image, $path, $mime);
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = max(1, min($width, self::REFERENCE_MAX_WIDTH));
        $targetHeight = max(1, (int) round($height * $targetWidth / $width));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            imagedestroy($image);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white === false || ! imagefill($canvas, 0, 0, $white)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        if (! imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        imagedestroy($image);

        ob_start();
        $encoded = imagejpeg($canvas, null, self::REFERENCE_JPEG_QUALITY);
        $content = ob_get_clean();
        imagedestroy($canvas);

        if (! $encoded) {
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
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
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
