<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Tag;
use App\Services\EnglishContentTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class TranslatePublicContent extends Command
{
    protected $signature = 'content:translate-en
        {--type=all : all, categories, tags, or images}
        {--limit=0 : Max records to process per type (0 = all)}
        {--batch=10 : Records per AI request}
        {--force : Replace existing English translations}';

    protected $description = 'Translate existing public content metadata from Vietnamese to English';

    public function handle(EnglishContentTranslator $translator): int
    {
        $type = (string) $this->option('type');
        $limit = (int) $this->option('limit');
        $batch = (int) $this->option('batch');

        if (! in_array($type, ['all', 'categories', 'tags', 'images'], true)) {
            $this->error('--type must be all, categories, tags, or images.');

            return self::INVALID;
        }

        if ($limit < 0 || $batch < 1 || $batch > 20) {
            $this->error('--limit must be zero or greater; --batch must be between 1 and 20.');

            return self::INVALID;
        }

        $types = $type === 'all' ? ['categories', 'tags', 'images'] : [$type];
        $translated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($types as $currentType) {
            if ($currentType === 'categories') {
                $records = $this->categories($limit);
            } elseif ($currentType === 'tags') {
                $records = $this->tags($limit);
            } else {
                $records = $this->images($limit);
            }

            $this->info('Translating '.$currentType.'...');

            foreach (array_chunk($records, $batch) as $chunk) {
                try {
                    $result = $translator->translate($chunk, (bool) $this->option('force'));
                    $translated += $result['translated'];
                    $skipped += $result['skipped'];
                } catch (Throwable $e) {
                    report($e);
                    $failed += count($chunk);
                    $this->warn($e->getMessage());
                }
            }
        }

        $this->info("Translated {$translated}; skipped {$skipped}; failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<Category> */
    private function categories(int $limit): array
    {
        $query = Category::query();
        $this->missing($query, 'name', includeSlug: true);

        return array_values($this->limit($query, $limit)->get()->all());
    }

    /** @return list<Tag> */
    private function tags(int $limit): array
    {
        $query = Tag::query();
        $this->missing($query, 'name', includeSlug: true);

        return array_values($this->limit($query, $limit)->get()->all());
    }

    /** @return list<GeneratedMedia> */
    private function images(int $limit): array
    {
        $query = GeneratedMedia::query()->publiclyVisible();
        $this->missing($query, 'title');

        return array_values($this->limit($query, $limit)->get()->all());
    }

    /** @param Builder<*> $query */
    private function missing(Builder $query, string $title, bool $includeSlug = false): void
    {
        if ($this->option('force')) {
            return;
        }

        $query->where(function (Builder $query) use ($includeSlug, $title): void {
            $query
                ->whereNull($title.'->en')
                ->orWhere($title.'->en', '')
                ->orWhereNull('description->en')
                ->orWhere('description->en', '');

            if ($includeSlug) {
                $query->orWhereNull('slug_en')->orWhere('slug_en', '');
            }
        });
    }

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function limit(Builder $query, int $limit): Builder
    {
        $query->orderBy('id');

        return $limit > 0 ? $query->limit($limit) : $query;
    }
}
