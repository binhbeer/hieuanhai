<?php

namespace App\Services;

use App\Models\AiImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiImageEditor
{
    public function create(Request $request, array $photos, string $prompt, ?string $customPrompt = null, ?string $preset = null): AiImage
    {
        $photo = $photos[0] ?? null;

        if (! $photo instanceof UploadedFile) {
            throw new \InvalidArgumentException('Cần ít nhất một ảnh tham chiếu.');
        }

        $photos = array_values(array_filter($photos, fn ($item) => $item instanceof UploadedFile));
        $visitorKey = $this->visitorKey($request);
        $provider = (string) config('ai.default_for_images', 'openai');
        $model = (string) config('ai.image_model', 'cx/gpt-5.5-image');
        $finalPrompt = trim(implode("\n\n", array_filter([
            'Use the provided reference image as the source. Edit that image according to the instructions. Preserve the original subjects, identities, composition, pose, and count unless explicitly asked to change them. Do not create an unrelated new image.',
            $prompt,
            $customPrompt,
        ])));

        $image = AiImage::create([
            'user_id' => Auth::id(),
            'visitor_key' => $visitorKey,
            'ip_address' => $request->ip(),
            'preset' => $preset,
            'prompt' => $prompt,
            'custom_prompt' => $customPrompt,
            'provider' => $provider,
            'model' => $model,
            'status' => 'pending',
            'request_meta' => [
                'upload_name' => $photo->getClientOriginalName(),
                'upload_mime' => $photo->getClientMimeType(),
                'upload_size' => $photo->getSize(),
                'upload_count' => count($photos),
            ],
        ]);

        try {
            $result = $this->generateImage($photos, $finalPrompt, $provider, $model);
            $storedPath = 'ai-images/'.Str::uuid().$this->extensionFor($result['mime']);
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

    public function visitorKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId().'|'.$request->ip());
    }

    public function remainingToday(Request $request): ?int
    {
        if (Auth::check()) {
            return null;
        }

        return max(0, $this->dailyLimit() - $this->countToday($request));
    }

    public function isLimitExceeded(Request $request): bool
    {
        return Auth::guest() && $this->remainingToday($request) <= 0;
    }

    public function countToday(Request $request): int
    {
        return AiImage::query()
            ->where('visitor_key', $this->visitorKey($request))
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
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
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
     * @return array{base64: string, mime: string, source_path: string, source_paths: array<int, string>, usage: array<string, mixed>}
     */
    private function generateImage(array $photos, string $prompt, string $provider, string $model): array
    {
        $sourcePaths = [];
        $referenceImages = [];

        foreach (array_slice($photos, 0, 3) as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            $sourcePath = 'ai-image-sources/'.Str::uuid().$this->extensionFor($photo->getMimeType() ?: 'image/png');
            $sourceContent = $photo->get();

            if (! Storage::disk('public')->put($sourcePath, $sourceContent, ['visibility' => 'public'])) {
                throw new \RuntimeException('Không lưu được ảnh nguồn.');
            }

            $sourcePaths[] = $sourcePath;
            $referenceImages[] = 'data:'.($photo->getMimeType() ?: 'image/png').';base64,'.base64_encode($sourceContent);
        }

        if ($referenceImages === []) {
            throw new \RuntimeException('Cần ít nhất một ảnh tham chiếu.');
        }

        $providerConfig = config("ai.providers.$provider");

        if (! is_array($providerConfig)) {
            throw new \RuntimeException("Provider AI [$provider] chưa được cấu hình.");
        }

        $url = rtrim((string) ($providerConfig['url'] ?? ''), '/');
        $key = (string) ($providerConfig['key'] ?? '');

        if ($url === '' || $key === '') {
            throw new \RuntimeException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => 'auto',
            'quality' => 'auto',
            'background' => 'auto',
            'image_detail' => 'high',
            'output_format' => 'png',
            count($referenceImages) === 1 ? 'image' : 'images' => count($referenceImages) === 1 ? $referenceImages[0] : $referenceImages,
        ];

        $response = Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.image_timeout', 300))
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
            'source_path' => $sourcePaths[0],
            'source_paths' => $sourcePaths,
            'usage' => is_array($usage) ? $usage : [],
        ];
    }

    public function dailyLimit(): int
    {
        return max(1, (int) config('ai.image_daily_limit', 3));
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/webp' => '.webp',
            default => '.png',
        };
    }
}
