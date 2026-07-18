<?php

use App\Jobs\GenerateCategoryDescription;
use App\Jobs\GenerateTagDescription;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Tag;
use App\Support\AppSettings;
use App\Support\LocalizedRoute;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Sitemap\Sitemap as SitemapFile;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapEntry;
use Spatie\Sitemap\Tags\Url;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('ai-images:recover-stale')->everyMinute()->withoutOverlapping();
Schedule::command('queue:monitor redis:default --max=10')->everyMinute()->withoutOverlapping();
Schedule::command('sitemap:generate')->everyTenMinutes();
// Schedule::command('categories:backfill-descriptions')->dailyAt('01:30')->withoutOverlapping();
// Schedule::command('tags:backfill-descriptions')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('sitemap:generate', function (): void {
    $publicImages = fn () => GeneratedMedia::query()->publiclyVisible();
    $englishEnabled = AppSettings::bool('locales.en.enabled');

    $publicImageFilter = fn ($query) => $query->publiclyVisible();

    $date = fn (mixed $value): ?CarbonInterface => $value ? Carbon::parse($value) : null;
    $maxDate = function (?CarbonInterface $current, mixed $candidate) use ($date): CarbonInterface {
        $candidate = $date($candidate) ?? now();

        return $current && $current->getTimestamp() > $candidate->getTimestamp() ? $current : $candidate;
    };

    $url = function (string $url, mixed $lastModified = null) use ($date): Url {
        $tag = Url::create($url);
        $lastModified = $date($lastModified);

        return $lastModified ? $tag->setLastModificationDate($lastModified) : $tag;
    };

    $write = function (string $file, SitemapFile $sitemap, mixed $lastModified = null) use ($date): CarbonInterface {
        $sitemap->writeToFile(public_path($file));

        return $date($lastModified) ?? now();
    };

    foreach (glob(public_path('sitemap*.xml')) ?: [] as $path) {
        if (is_file($path) && basename($path) !== 'sitemap.xml') {
            unlink($path);
        }
    }

    $index = SitemapIndex::create();
    $latestImageUpdate = $date($publicImages()->max('updated_at')) ?? now();

    $guideRoutes = ['guide.index', 'guide.getting-started', 'guide.web', 'guide.api', 'guide.faq'];
    $publicRoutes = [
        'home',
        'gallery.index',
        'quick.index',
        'quick.remove-object',
        'quick.restore-old-photo',
        'quick.replace-background',
        'quick.product-photo',
        'quick.face-swap',
        'quick.change-outfit',
        'quick.add-person',
        'quick.id-photo',
        'creator.index',
        'studio.index',
    ];
    $pages = SitemapFile::create();

    foreach ($publicRoutes as $publicRoute) {
        $pages->add($url(LocalizedRoute::url($publicRoute, locale: 'vi'), $publicRoute === 'gallery.index' ? $latestImageUpdate : now()));
    }

    foreach ($guideRoutes as $guideRoute) {
        $pages->add($url(LocalizedRoute::url($guideRoute, locale: 'vi'), now()));
    }

    if ($englishEnabled) {
        foreach ($publicRoutes as $publicRoute) {
            $pages->add($url(LocalizedRoute::url($publicRoute, locale: 'en'), $publicRoute === 'gallery.index' ? $latestImageUpdate : now()));
        }

        foreach ($guideRoutes as $guideRoute) {
            $pages->add($url(LocalizedRoute::url($guideRoute, locale: 'en'), now()));
        }
    }
    $pagesLastModified = $write('sitemap-pages.xml', $pages, now());
    $index->add(SitemapEntry::create(url('/sitemap-pages.xml'))->setLastModificationDate($pagesLastModified));

    $categories = SitemapFile::create();
    $categoriesLastModified = null;

    Category::query()
        ->active()
        ->ordered()
        ->get()
        ->each(function (Category $category) use ($categories, $englishEnabled, $maxDate, $publicImages, $url, &$categoriesLastModified): void {
            $lastModified = $publicImages()
                ->where('category_id', $category->id)
                ->max('updated_at') ?? $category->updated_at;

            $categories->add($url(LocalizedRoute::url('categories.show', $category, 'vi'), $lastModified));

            if ($englishEnabled && $category->englishReady()) {
                $categories->add($url(LocalizedRoute::url('categories.show', $category, 'en'), $lastModified));
            }

            $categoriesLastModified = $maxDate($categoriesLastModified, $lastModified);
        });

    $categoriesLastModified = $write('sitemap-categories.xml', $categories, $categoriesLastModified);
    $index->add(SitemapEntry::create(url('/sitemap-categories.xml'))->setLastModificationDate($categoriesLastModified));

    $tags = SitemapFile::create();
    $tagsLastModified = null;

    Tag::query()
        ->whereHas('media', $publicImageFilter)
        ->orderBy('id')
        ->get()
        ->each(function (Tag $tag) use ($englishEnabled, $maxDate, $tags, $url, &$tagsLastModified): void {
            $lastModified = $tag->media()
                ->publiclyVisible()
                ->max('generated_media.updated_at') ?? $tag->updated_at;

            $tags->add($url(LocalizedRoute::url('tags.show', $tag, 'vi'), $lastModified));

            if ($englishEnabled && $tag->englishReady()) {
                $tags->add($url(LocalizedRoute::url('tags.show', $tag, 'en'), $lastModified));
            }

            $tagsLastModified = $maxDate($tagsLastModified, $lastModified);
        });

    $tagsLastModified = $write('sitemap-tags.xml', $tags, $tagsLastModified);
    $index->add(SitemapEntry::create(url('/sitemap-tags.xml'))->setLastModificationDate($tagsLastModified));

    $latestImages = SitemapFile::create();
    $latestImagesLastModified = null;

    $publicImages()
        ->orderByRaw('COALESCE(published_at, created_at) desc')
        ->limit(100)
        ->get()
        ->each(function (GeneratedMedia $image) use ($englishEnabled, $latestImages, $maxDate, $url, &$latestImagesLastModified): void {
            $lastModified = $image->updated_at ?? $image->published_at ?? $image->created_at;

            $latestImages->add($url(LocalizedRoute::url('images.show', $image, 'vi'), $lastModified));

            if ($englishEnabled && $image->englishReady()) {
                $latestImages->add($url(LocalizedRoute::url('images.show', $image, 'en'), $lastModified));
            }

            $latestImagesLastModified = $maxDate($latestImagesLastModified, $lastModified);
        });

    $latestImagesLastModified = $write('sitemap-images-latest.xml', $latestImages, $latestImagesLastModified);
    $index->add(SitemapEntry::create(url('/sitemap-images-latest.xml'))->setLastModificationDate($latestImagesLastModified));

    $months = [];
    $publicImages()
        ->select(['id', 'published_at', 'created_at'])
        ->chunkById(1000, function ($images) use (&$months): void {
            foreach ($images as $image) {
                $monthDate = $image->published_at ?? $image->created_at;

                if ($monthDate) {
                    $months[$monthDate->format('Y-m')] = true;
                }
            }
        });

    krsort($months);

    foreach (array_keys($months) as $month) {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $images = SitemapFile::create();
        $imagesLastModified = null;

        $publicImages()
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('published_at', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end): void {
                        $query->whereNull('published_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->chunkById(1000, function ($chunk) use ($englishEnabled, $images, $maxDate, $url, &$imagesLastModified): void {
                /** @var Collection<int, GeneratedMedia> $chunk */
                foreach ($chunk as $image) {
                    $lastModified = $image->updated_at ?? $image->published_at ?? $image->created_at;

                    $images->add($url(LocalizedRoute::url('images.show', $image, 'vi'), $lastModified));

                    if ($englishEnabled && $image->englishReady()) {
                        $images->add($url(LocalizedRoute::url('images.show', $image, 'en'), $lastModified));
                    }

                    $imagesLastModified = $maxDate($imagesLastModified, $lastModified);
                }
            });

        $file = 'sitemap-images-'.$month.'.xml';
        $imagesLastModified = $write($file, $images, $imagesLastModified);
        $index->add(SitemapEntry::create(url('/'.$file))->setLastModificationDate($imagesLastModified));
    }

    $index->writeToFile(public_path('sitemap.xml'));

    $this->info('Sitemap generated.');
})->purpose('Generate sitemap index and child sitemaps');

Artisan::command('categories:backfill-descriptions {--limit=0 : Max categories to queue (0 = all)}', function (): int {
    $limit = (int) $this->option('limit');

    if ($limit < 0) {
        $this->error('--limit must be zero or greater.');

        return 2;
    }

    $query = Category::query()
        ->active()
        ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
        ->ordered();

    if ($limit > 0) {
        $query->limit($limit);
    }

    $categoryIds = $query->pluck('id');
    $categoryIds->each(fn (int $categoryId) => GenerateCategoryDescription::dispatch($categoryId));
    $this->info("Queued {$categoryIds->count()} category descriptions.");

    return 0;
})->purpose('Queue missing SEO descriptions for active categories');

Artisan::command('tags:backfill-descriptions {--limit=0 : Max tags to queue (0 = all)}', function (): int {
    $limit = (int) $this->option('limit');

    if ($limit < 0) {
        $this->error('--limit must be zero or greater.');

        return 2;
    }

    $query = Tag::query()
        ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
        ->whereHas('media', fn ($query) => $query
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path'))
        ->orderBy('id');

    if ($limit > 0) {
        $query->limit($limit);
    }

    $tagIds = $query->pluck('id');
    $tagIds->each(fn (int $tagId) => GenerateTagDescription::dispatch($tagId));
    $this->info("Queued {$tagIds->count()} tag descriptions.");

    return 0;
})->purpose('Queue missing SEO descriptions for public tags');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
