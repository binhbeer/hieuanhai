<?php

namespace App\Services;

use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Models\SkillProject;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\GptImageOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SkillProjectGenerator
{
    public function __construct(private AiImageEditor $editor) {}

    /**
     * @param  array<int, array{prompt: string, title: string, output_type: string}>  $outputs
     * @return Collection<int, GeneratedMedia>
     */
    public function create(
        Request $request,
        SkillProject $project,
        array $outputs,
        string $aspectRatio,
        string $resolution,
        string $imageDetail = 'high',
        ?string $model = null,
    ): Collection {
        $user = Auth::user();

        if (! $user instanceof User || $project->user_id !== $user->id) {
            throw new \InvalidArgumentException('Không tìm thấy dự án.');
        }

        if ($this->editor->requiresEmailVerificationForImageCreation()) {
            throw new \InvalidArgumentException('Vui lòng xác minh email để tiếp tục nhận lượt tạo ảnh hằng ngày sau ngày đăng ký đầu tiên.');
        }

        if (! in_array($project->skill, SkillProject::SKILLS, true)) {
            throw new \InvalidArgumentException('Công cụ AI không hợp lệ.');
        }

        $outputs = array_values(array_filter($outputs, fn (array $output): bool => filled($output['prompt'])
            && filled($output['title'])
            && filled($output['output_type'])));

        if ($outputs === [] || count($outputs) > 6) {
            throw new \InvalidArgumentException('Hãy chọn từ 1 đến 6 loại ảnh.');
        }

        foreach ($outputs as $output) {
            if (Str::length($output['prompt']) > 12000 || Str::length($output['title']) > 255 || Str::length($output['output_type']) > 80) {
                throw new \InvalidArgumentException('Nội dung dự án quá dài.');
            }
        }

        if (! in_array($aspectRatio, GptImageOptions::ASPECT_RATIOS, true)
            || ! in_array($resolution, GptImageOptions::RESOLUTIONS, true)
            || ! GptImageOptions::isValidImageDetail($imageDetail)) {
            throw new \InvalidArgumentException('Tùy chọn ảnh không hợp lệ.');
        }

        $model = AppSettings::resolveImageModel($model);
        $inputPaths = $this->inputReferences($project);

        if ($project->skill === 'product-detail' && ! collect($inputPaths)->contains(fn (array $input): bool => $input['role'] === 'product')) {
            throw new \InvalidArgumentException('Hãy tải lên ảnh sản phẩm chính.');
        }

        $createdPendingPaths = [];

        try {
            return DB::transaction(function () use ($request, $project, $outputs, $aspectRatio, $resolution, $imageDetail, $model, $inputPaths, $user, &$createdPendingPaths): Collection {
                $lockedProject = SkillProject::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->find($project->id);

                if (! $lockedProject) {
                    throw new \InvalidArgumentException('Không tìm thấy dự án.');
                }

                User::query()->whereKey($user->id)->lockForUpdate()->first();

                $mediaQuery = (new GeneratedMedia)->disableModelCaching()->newQuery();

                if ($mediaQuery->clone()->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                    throw new \InvalidArgumentException('Bạn đang có ảnh đang tạo. Vui lòng chờ ảnh hiện tại hoàn tất.');
                }

                $usedToday = $mediaQuery
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'succeeded'])
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();

                if (! $user->isAdmin() && $usedToday + count($outputs) > $this->editor->dailyLimit()) {
                    throw new \InvalidArgumentException('Bạn không còn đủ lượt tạo ảnh hôm nay cho dự án này.');
                }

                $provider = AppSettings::string('ai.image_provider', (string) config('ai.default_for_images', 'openai'));
                $size = GptImageOptions::size($aspectRatio, $resolution);
                $version = max(0, (int) (new GeneratedMedia)->disableModelCaching()->newQuery()
                    ->where('skill_project_id', $lockedProject->id)
                    ->get(['request_meta'])
                    ->max(fn (GeneratedMedia $media): int => max(1, (int) data_get($media->request_meta, 'version', 1)))) + 1;
                $images = new Collection;

                foreach ($outputs as $output) {
                    $pendingUploads = $this->copyInputs($inputPaths);
                    $referenceRoles = array_column($pendingUploads, 'role');
                    $createdPendingPaths = [...$createdPendingPaths, ...array_column($pendingUploads, 'path')];
                    $image = GeneratedMedia::create([
                        'user_id' => $user->id,
                        'skill_project_id' => $lockedProject->id,
                        'visitor_key' => $this->editor->visitorKey($request),
                        'ip_address' => $request->ip(),
                        'title' => trim($output['title']),
                        'preset' => $lockedProject->skill,
                        'prompt' => trim($output['prompt']),
                        'custom_prompt' => is_string(data_get($lockedProject->form_data, 'notes')) ? trim((string) data_get($lockedProject->form_data, 'notes')) : null,
                        'source' => 'skills',
                        'provider' => $provider,
                        'model' => $model,
                        'status' => 'pending',
                        'request_meta' => [
                            'upload_count' => count($pendingUploads),
                            'pending_uploads' => $pendingUploads,
                            'reference_roles' => $referenceRoles,
                            'prompt_contract' => $lockedProject->skill === 'product-detail' ? 'product-detail-v2' : null,
                            'aspect_ratio' => $aspectRatio,
                            'resolution' => $resolution,
                            'size' => $size,
                            'image_detail' => $imageDetail,
                            'skill' => $lockedProject->skill,
                            'version' => $version,
                            'output_type' => $output['output_type'],
                            'progress' => 'queued',
                        ],
                    ]);

                    CreateAiImage::dispatch($image->id, $image->user_id)->afterCommit();
                    $images->push($image);
                }

                if ($lockedProject->submitted_at === null) {
                    $lockedProject->submitted_at = Carbon::now();
                }

                $lockedProject->save();

                return $images;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($createdPendingPaths);

            throw $e;
        }
    }

    /**
     * @return array<int, array{path: string, role: string}>
     */
    private function inputReferences(SkillProject $project): array
    {
        $paths = is_array($project->input_paths) ? $project->input_paths : [];

        if ($project->skill !== 'product-detail') {
            return collect($paths)
                ->flatten()
                ->filter(fn (mixed $path): bool => is_string($path) && Storage::disk('public')->exists($path))
                ->take(AppSettings::maxReferencePhotos())
                ->map(fn (string $path): array => ['path' => $path, 'role' => 'reference'])
                ->values()
                ->all();
        }

        $legacyPaths = is_array($paths['references'] ?? null) ? $paths['references'] : [];
        $legacy = collect($legacyPaths)->filter(fn (mixed $path): bool => is_string($path))->values()->all();
        $product = is_string($paths['product'] ?? null) ? $paths['product'] : ($legacy[0] ?? null);
        $additionalPaths = is_array($paths['additional_products'] ?? null) ? $paths['additional_products'] : array_slice($legacy, 1);
        $additional = collect($additionalPaths)->filter(fn (mixed $path): bool => is_string($path))->take(2)->all();
        $references = [
            ['path' => $product, 'role' => 'product'],
            ['path' => $paths['logo'] ?? null, 'role' => 'logo'],
            ['path' => $paths['model'] ?? null, 'role' => 'model'],
            ...array_map(fn (string $path): array => ['path' => $path, 'role' => 'additional_product'], $additional),
        ];

        return collect($references)
            ->filter(fn (array $input): bool => is_string($input['path']) && Storage::disk('public')->exists($input['path']))
            ->take(AppSettings::maxReferencePhotos())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{path: string, role: string}>  $inputs
     * @return array<int, array{path: string, name: string|null, mime: string|null, role: string}>
     */
    private function copyInputs(array $inputs): array
    {
        $disk = Storage::disk('public');
        $uploads = [];

        foreach ($inputs as $input) {
            $sourcePath = $input['path'];
            $content = $disk->get($sourcePath);

            if (! is_string($content)) {
                throw new \RuntimeException('Không đọc được ảnh dự án.');
            }

            $path = 'ai-image-pending/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);

            if (! $disk->put($path, $content, ['visibility' => 'private'])) {
                throw new \RuntimeException('Không lưu được ảnh tải lên.');
            }

            $uploads[] = [
                'path' => $path,
                'name' => basename($sourcePath),
                'mime' => $disk->mimeType($sourcePath) ?: null,
                'role' => $input['role'],
            ];
        }

        return $uploads;
    }
}
