<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $createdAt = $media->created_at ?? now();
        $modelType = strtolower(class_basename((string) $media->model_type)) ?: 'unknown';
        $mimeHead = explode('/', (string) $media->mime_type, 2)[0] ?: 'other';

        return sprintf(
            '%s/%s/%s/%s/%d/%s/',
            $mimeHead,
            $modelType,
            $createdAt->format('Ym'),
            $createdAt->format('d'),
            (int) $media->model_id,
            (string) $media->getKey(),
        );
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'convs/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'resps/';
    }
}
