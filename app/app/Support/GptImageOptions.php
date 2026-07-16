<?php

namespace App\Support;

class GptImageOptions
{
    public const ASPECT_RATIOS = [
        'auto',
        '1:1',
        '3:4',
        '4:5',
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
     * Provider-native size map (current gateway ignores large OpenAI sizes).
     * Long-edge targets stay within observed provider output so width|height can meet config without crop/resize.
     *
     * Observed ceilings (runtime): ~1254 square, ~1672 long edge for non-square.
     *
     * @var array<string, array{'1k': string, '2k': string, '4k': string}>
     */
    private const SIZES = [
        'auto' => [
            '1k' => '1024x1024',
            '2k' => '1248x1248',
            '4k' => '1248x1248',
        ],
        '1:1' => [
            '1k' => '1024x1024',
            '2k' => '1248x1248',
            '4k' => '1248x1248',
        ],
        '3:4' => [
            '1k' => '768x1024',
            '2k' => '1152x1536',
            '4k' => '1248x1664',
        ],
        '4:5' => [
            '1k' => '816x1024',
            '2k' => '1224x1536',
            '4k' => '1328x1664',
        ],
        '2:3' => [
            '1k' => '688x1024',
            '2k' => '1024x1536',
            '4k' => '1104x1664',
        ],
        '9:16' => [
            '1k' => '576x1024',
            '2k' => '864x1536',
            '4k' => '928x1664',
        ],
        '4:3' => [
            '1k' => '1024x768',
            '2k' => '1536x1152',
            '4k' => '1664x1248',
        ],
        '3:2' => [
            '1k' => '1024x688',
            '2k' => '1536x1024',
            '4k' => '1664x1104',
        ],
        '16:9' => [
            '1k' => '1024x576',
            '2k' => '1536x864',
            '4k' => '1664x928',
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
            '1024x1536' => ['aspect_ratio' => '2:3', 'resolution' => '2k'],
            '816x1024' => ['aspect_ratio' => '4:5', 'resolution' => '1k'],
            '1224x1536' => ['aspect_ratio' => '4:5', 'resolution' => '2k'],
            '1328x1664' => ['aspect_ratio' => '4:5', 'resolution' => '4k'],
            '1536x1024' => ['aspect_ratio' => '3:2', 'resolution' => '2k'],
            '1024x1792', '576x1024' => ['aspect_ratio' => '9:16', 'resolution' => '1k'],
            '1792x1024', '1024x576' => ['aspect_ratio' => '16:9', 'resolution' => '1k'],
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
            $longEdge >= 1600 => '4k',
            $longEdge >= 1400 => '2k',
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
