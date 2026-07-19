<?php

namespace App\Support;

final class StudioSamples
{
    /**
     * @return array<string, array{
     *     title: string,
     *     description: string,
     *     tool: string,
     *     inputs: list<array{label: string, image: string}>,
     *     results: list<array{label: string, image: string}>
     * }>
     */
    public static function all(): array
    {
        return [
            'wireless-headphones' => [
                'title' => 'Wireless Bluetooth headphones',
                'description' => 'Turn one clean product photo into a complete ecommerce image set with consistent shape, materials, and color.',
                'tool' => 'product-detail',
                'inputs' => [
                    ['label' => 'Product image', 'image' => 'images/studio-samples/wireless-headphones/input.webp'],
                ],
                'results' => [
                    ['label' => 'Hero Banner', 'image' => 'images/studio-samples/wireless-headphones/hero.webp'],
                    ['label' => 'Close-up Details', 'image' => 'images/studio-samples/wireless-headphones/close-up.webp'],
                    ['label' => 'Lifestyle Scene', 'image' => 'images/studio-samples/wireless-headphones/lifestyle.webp'],
                    ['label' => 'Feature Highlight', 'image' => 'images/studio-samples/wireless-headphones/features.webp'],
                ],
            ],
            'luxury-perfume' => [
                'title' => 'Luxury perfume',
                'description' => 'Build premium catalog and campaign visuals while preserving the bottle, packaging, glass, and brand details.',
                'tool' => 'product-detail',
                'inputs' => [
                    ['label' => 'Product image', 'image' => 'images/studio-samples/luxury-perfume/input.webp'],
                ],
                'results' => [
                    ['label' => 'Hero Banner', 'image' => 'images/studio-samples/luxury-perfume/hero.webp'],
                    ['label' => 'Close-up Details', 'image' => 'images/studio-samples/luxury-perfume/close-up.webp'],
                    ['label' => 'Lifestyle Scene', 'image' => 'images/studio-samples/luxury-perfume/lifestyle.webp'],
                    ['label' => 'Ingredient Story', 'image' => 'images/studio-samples/luxury-perfume/ingredients.webp'],
                ],
            ],
            'leather-handbag' => [
                'title' => 'Leather handbag',
                'description' => 'Present one fashion product across catalog, material, street-style, and on-model compositions.',
                'tool' => 'product-detail',
                'inputs' => [
                    ['label' => 'Product image', 'image' => 'images/studio-samples/leather-handbag/input.webp'],
                ],
                'results' => [
                    ['label' => 'Hero Banner', 'image' => 'images/studio-samples/leather-handbag/hero.webp'],
                    ['label' => 'Close-up Details', 'image' => 'images/studio-samples/leather-handbag/close-up.webp'],
                    ['label' => 'Lifestyle Scene', 'image' => 'images/studio-samples/leather-handbag/lifestyle.webp'],
                    ['label' => 'On-model Look', 'image' => 'images/studio-samples/leather-handbag/on-model.webp'],
                ],
            ],
            'coffee-combo-menu' => [
                'title' => 'Coffee shop combo menu',
                'description' => 'Combine several separate food and drink references into one polished Vietnamese promotional poster.',
                'tool' => 'marketing-poster',
                'inputs' => [
                    ['label' => 'Coffee', 'image' => 'images/studio-samples/coffee-combo-menu/coffee.webp'],
                    ['label' => 'Matcha', 'image' => 'images/studio-samples/coffee-combo-menu/matcha.webp'],
                    ['label' => 'Pastries', 'image' => 'images/studio-samples/coffee-combo-menu/pastries.webp'],
                ],
                'results' => [
                    ['label' => 'Marketing poster', 'image' => 'images/studio-samples/coffee-combo-menu/poster.webp'],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     tool: string,
     *     inputs: list<array{label: string, image: string}>,
     *     results: list<array{label: string, image: string}>
     * }|null
     */
    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
