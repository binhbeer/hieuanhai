<?php

namespace App\Actions;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\StudioProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class DeleteUserAccount
{
    public function __invoke(User $user): void
    {
        [$mediaIds, $paths] = DB::transaction(function () use ($user): array {
            $user = User::query()->lockForUpdate()->find($user->id);

            if (! $user) {
                return [[], []];
            }

            $mediaIds = [];
            $paths = [];

            MediaFavorite::query()
                ->where('user_id', $user->id)
                ->eachById(fn (MediaFavorite $favorite) => $favorite->delete());

            GeneratedMedia::query()
                ->where('user_id', $user->id)
                ->eachById(function (GeneratedMedia $image) use (&$mediaIds, &$paths): void {
                    $mediaIds = [...$mediaIds, ...$this->mediaIds($image)];
                    $paths = [...$paths, ...array_values(array_filter([
                        $image->result_path,
                        ...$this->paths(data_get($image->request_meta, 'pending_uploads')),
                        ...$this->paths(data_get($image->response_meta, 'source_paths')),
                    ]))];
                    $image->deletePreservingMedia();
                });

            StudioProject::query()
                ->where('user_id', $user->id)
                ->eachById(function (StudioProject $project) use (&$paths): void {
                    $paths = [...$paths, ...$this->paths($project->input_paths)];
                    $project->delete();
                });

            ApiRequest::query()->where('user_id', $user->id)->delete();
            ApiKey::query()
                ->where('user_id', $user->id)
                ->eachById(fn (ApiKey $key) => $key->delete());

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $mediaIds = [...$mediaIds, ...$this->mediaIds($user)];
            $paths = [...$paths, ...$this->paths($user->avatar_path)];
            $user->deletePreservingMedia();

            return [$mediaIds, $paths];
        });

        Media::query()->whereKey($mediaIds)->each(function (Media $media): void {
            try {
                $media->delete();
            } catch (Throwable $e) {
                report($e);
            }
        });

        $paths = array_values(array_unique($paths));

        if ($paths !== [] && ! Storage::disk('public')->delete($paths)) {
            report(new RuntimeException('Không xóa được toàn bộ file của tài khoản.'));
        }
    }

    /** @return array<int, int|string> */
    private function mediaIds(User|GeneratedMedia $model): array
    {
        return DB::table('media')
            ->where('model_type', $model::class)
            ->where('model_id', $model->id)
            ->pluck('id')
            ->all();
    }

    /** @return array<int, string> */
    private function paths(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        if (array_key_exists('path', $value)) {
            return is_string($value['path']) && $value['path'] !== '' ? [$value['path']] : [];
        }

        return collect($value)
            ->flatMap(fn (mixed $item): array => $this->paths($item))
            ->values()
            ->all();
    }
}
