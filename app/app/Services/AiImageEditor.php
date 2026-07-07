<?php

namespace App\Services;

use App\Models\AiImage;
use GdImage;
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
    private const REFERENCE_MAX_WIDTH = 1024;

    private const REFERENCE_JPEG_QUALITY = 88;

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

            $sourceContent = $this->referenceImageContent($photo);
            $sourcePath = 'ai-image-sources/'.Str::uuid().'.jpg';

            if (! Storage::disk('public')->put($sourcePath, $sourceContent, ['visibility' => 'public'])) {
                throw new \RuntimeException('Không lưu được ảnh nguồn.');
            }

            $sourcePaths[] = $sourcePath;
            $referenceImages[] = 'data:image/jpeg;base64,'.base64_encode($sourceContent);
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

    private function referenceImageContent(UploadedFile $photo): string
    {
        $path = $photo->getRealPath();

        if (! is_string($path)) {
            throw new \InvalidArgumentException('Không đọc được ảnh tải lên.');
        }

        $info = @getimagesize($path);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : (string) $photo->getMimeType();
        $image = $this->imageFromPath($path, $mime);

        if (! $image) {
            throw new \InvalidArgumentException('Định dạng ảnh chưa hỗ trợ. Hãy dùng JPG, PNG, WEBP hoặc AVIF.');
        }

        $image = $this->orientImage($image, $path, $mime);
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min($width, self::REFERENCE_MAX_WIDTH);
        $targetHeight = (int) round($height * $targetWidth / $width);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            imagedestroy($image);

            throw new \RuntimeException('Không resize được ảnh nguồn.');
        }

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

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

        if (! $encoded || ! is_string($content)) {
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
}
