<?php

namespace App\Support;

class GptImageOptions
{
    public const ASPECT_RATIOS = [
        'auto',
        '1:1',
        '3:4',
        '2:3',
        '9:16',
        '4:3',
        '3:2',
        '16:9',
    ];

    public const RESOLUTIONS = [
        '1k',
        '2k',
        '4k',
    ];

    public const IMAGE_DETAILS = [
        'auto',
        'low',
        'high',
        'original',
    ];

    /**
     * @var array<string, array{1k: string, 2k: string, 4k: string}>
     */
    private const SIZES = [
        'auto' => [
            '1k' => '1024x1024',
            '2k' => '2048x2048',
            '4k' => '2880x2880',
        ],
        '1:1' => [
            '1k' => '1024x1024',
            '2k' => '2048x2048',
            '4k' => '2880x2880',
        ],
        '3:4' => [
            '1k' => '1024x1360',
            '2k' => '1536x2048',
            '4k' => '2448x3264',
        ],
        '2:3' => [
            '1k' => '1024x1536',
            '2k' => '1344x2016',
            '4k' => '2336x3504',
        ],
        '9:16' => [
            '1k' => '1008x1792',
            '2k' => '1152x2048',
            '4k' => '2160x3840',
        ],
        '4:3' => [
            '1k' => '1360x1024',
            '2k' => '2048x1536',
            '4k' => '3264x2448',
        ],
        '3:2' => [
            '1k' => '1536x1024',
            '2k' => '2016x1344',
            '4k' => '3504x2336',
        ],
        '16:9' => [
            '1k' => '1792x1008',
            '2k' => '2048x1152',
            '4k' => '3840x2160',
        ],
    ];

    /**
     * @return array{aspect_ratio: string, resolution: string}
     */
    public static function defaultsFromSettings(?string $size = null): array
    {
        $size = $size ?? AppSettings::string('ai.image_size', (string) config('ai.image_size', 'auto'));

        return match ($size) {
            'auto' => ['aspect_ratio' => 'auto', 'resolution' => '1k'],
            '1024x1024' => ['aspect_ratio' => '1:1', 'resolution' => '1k'],
            '1024x1536' => ['aspect_ratio' => '2:3', 'resolution' => '1k'],
            '1536x1024' => ['aspect_ratio' => '3:2', 'resolution' => '1k'],
            '1024x1792' => ['aspect_ratio' => '9:16', 'resolution' => '1k'],
            '1792x1024' => ['aspect_ratio' => '16:9', 'resolution' => '1k'],
            default => self::nearestFromSize($size),
        };
    }

    public static function defaultImageDetail(?string $detail = null): string
    {
        $detail = $detail ?? AppSettings::string('ai.image_detail', (string) config('ai.image_detail', 'high'));

        return in_array($detail, self::IMAGE_DETAILS, true) ? $detail : 'high';
    }

    public static function size(string $aspectRatio, string $resolution): string
    {
        $aspectRatio = in_array($aspectRatio, self::ASPECT_RATIOS, true) ? $aspectRatio : 'auto';
        $resolution = in_array($resolution, self::RESOLUTIONS, true) ? $resolution : '1k';

        return self::SIZES[$aspectRatio][$resolution];
    }

    public static function isValidSize(string $size): bool
    {
        foreach (self::SIZES as $resolutions) {
            if (in_array($size, $resolutions, true)) {
                return true;
            }
        }

        return $size === 'auto';
    }

    public static function isValidImageDetail(string $detail): bool
    {
        return in_array($detail, self::IMAGE_DETAILS, true);
    }

    /**
     * @return array{aspect_ratio: string, resolution: string}
     */
    private static function nearestFromSize(string $size): array
    {
        if (! preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return ['aspect_ratio' => 'auto', 'resolution' => '1k'];
        }

        $width = max(1, (int) $matches[1]);
        $height = max(1, (int) $matches[2]);
        $longEdge = max($width, $height);
        $ratio = $width / $height;

        $resolution = match (true) {
            $longEdge >= 2560 => '4k',
            $longEdge >= 1536 => '2k',
            default => '1k',
        };

        $targets = [
            '1:1' => 1.0,
            '3:4' => 3 / 4,
            '2:3' => 2 / 3,
            '9:16' => 9 / 16,
            '4:3' => 4 / 3,
            '3:2' => 3 / 2,
            '16:9' => 16 / 9,
        ];

        $bestAspect = '1:1';
        $bestDelta = PHP_FLOAT_MAX;

        foreach ($targets as $aspect => $target) {
            $delta = abs($ratio - $target);

            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $bestAspect = $aspect;
            }
        }

        return ['aspect_ratio' => $bestAspect, 'resolution' => $resolution];
    }
}
