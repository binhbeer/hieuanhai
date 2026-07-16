<?php

namespace App\Services;

use App\Ai\EnglishContentTranslationAgent;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Tag;
use App\Support\AppSettings;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use RuntimeException;
use Throwable;

class EnglishContentTranslator
{
    /**
     * @param  list<Category|Tag|GeneratedMedia>  $records
     * @return array{translated: int, skipped: int}
     */
    public function translate(array $records, bool $force = false): array
    {
        $records = array_values(array_filter(
            $records,
            fn (Category|Tag|GeneratedMedia $record): bool => $force || ! $this->isReady($record),
        ));

        if ($records === []) {
            return ['translated' => 0, 'skipped' => 0];
        }

        $translations = collect($this->request($records))
            ->keyBy(fn (mixed $translation): int => (int) data_get($translation, 'id'));
        $translated = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $translation = $translations->get((int) $record->getKey());
            $title = Str::of((string) data_get($translation, 'title'))->squish()->limit(80, '')->toString();
            $description = Str::of((string) data_get($translation, 'description'))->squish()->limit(160, '')->toString();

            if ($title === '' || $description === '') {
                $skipped++;

                continue;
            }

            $titleAttribute = $record instanceof GeneratedMedia ? 'title' : 'name';
            $record
                ->setTranslation($titleAttribute, 'en', $title)
                ->setTranslation('description', 'en', $description);

            if ($record instanceof Category || $record instanceof Tag) {
                $record->slug_en = $this->slug($record, $title);
            }

            $record->save();
            $translated++;
        }

        return ['translated' => $translated, 'skipped' => $skipped];
    }

    /**
     * @param  list<Category|Tag|GeneratedMedia>  $records
     * @return list<array{id: int, title: string, description: string}>
     */
    private function request(array $records): array
    {
        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = AppSettings::string('ai.tag_model')
            ?: AppSettings::string('ai.text_model', (string) config('ai.text_model', 'gpt-5.5'));
        $this->configureProvider($provider);
        $payload = array_map(fn (Category|Tag|GeneratedMedia $record): array => [
            'id' => (int) $record->getKey(),
            'title' => $this->sourceTitle($record),
            'description' => (string) $record->getTranslationWithoutFallback('description', 'vi'),
            'prompt' => $record instanceof GeneratedMedia ? Str::limit((string) $record->prompt, 500, '') : '',
        ], $records);

        try {
            $response = EnglishContentTranslationAgent::make()->prompt(
                "Dịch các record JSON sau sang English:\n".json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                provider: $provider,
                model: $model,
                timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Không dịch được metadata sang English.', previous: $e);
        }

        $data = $response instanceof Arrayable ? $response->toArray() : [];
        $translations = is_array($data['translations'] ?? null) ? $data['translations'] : $data;

        if (! array_is_list($translations)) {
            return [];
        }

        $validated = [];

        foreach ($translations as $translation) {
            if (! is_array($translation) || ! is_numeric($translation['id'] ?? null)) {
                continue;
            }

            $validated[] = [
                'id' => (int) $translation['id'],
                'title' => is_string($translation['title'] ?? null) ? $translation['title'] : '',
                'description' => is_string($translation['description'] ?? null) ? $translation['description'] : '',
            ];
        }

        if (count($validated) !== count($records)) {
            throw new RuntimeException('AI trả thiếu bản dịch trong batch.');
        }

        return $validated;
    }

    private function configureProvider(string $provider): void
    {
        $url = rtrim(AppSettings::string('ai.'.$provider.'_url'), '/');
        $key = AppSettings::string('ai.'.$provider.'_api_key');

        if ($url === '' || $key === '') {
            throw new RuntimeException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        config([
            "ai.providers.$provider.driver" => 'openrouter',
            "ai.providers.$provider.key" => $key,
            "ai.providers.$provider.url" => $url,
        ]);
        Ai::forgetInstance($provider);
    }

    private function sourceTitle(Category|Tag|GeneratedMedia $record): string
    {
        return (string) $record->getTranslationWithoutFallback($record instanceof GeneratedMedia ? 'title' : 'name', 'vi');
    }

    private function isReady(Category|Tag|GeneratedMedia $record): bool
    {
        $titleAttribute = $record instanceof GeneratedMedia ? 'title' : 'name';

        return filled($record->getTranslationWithoutFallback($titleAttribute, 'en'))
            && filled($record->getTranslationWithoutFallback('description', 'en'))
            && ($record instanceof GeneratedMedia || filled($record->slug_en));
    }

    private function slug(Category|Tag $record, string $title): string
    {
        $slug = Str::limit(Str::slug($title, '-', 'en'), 220, '') ?: 'item';
        $query = $record->newQuery()
            ->where('slug_en', $slug)
            ->whereKeyNot($record->getKey());

        return $query->exists() ? $slug.'-'.$record->getKey() : $slug;
    }
}
