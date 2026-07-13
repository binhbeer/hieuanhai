<?php

use App\Models\AiImage;
use App\Models\AiTag;
use App\Models\Category;
use Carbon\CarbonInterface;
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

Artisan::command('sitemap:generate', function (): void {
    $publicImages = fn () => AiImage::query()->publiclyVisible();

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

    $pages = SitemapFile::create()
        ->add($url(route('home'), $latestImageUpdate));
    $pagesLastModified = $write('sitemap-pages.xml', $pages, now());
    $index->add(SitemapEntry::create(url('/sitemap-pages.xml'))->setLastModificationDate($pagesLastModified));

    $categories = SitemapFile::create();
    $categoriesLastModified = null;

    Category::query()
        ->active()
        ->ordered()
        ->get()
        ->each(function (Category $category) use ($categories, $maxDate, $publicImages, $url, &$categoriesLastModified): void {
            $lastModified = $publicImages()
                ->where('category_id', $category->id)
                ->max('updated_at') ?? $category->updated_at;

            $categories->add($url(route('categories.show', $category), $lastModified));
            $categoriesLastModified = $maxDate($categoriesLastModified, $lastModified);
        });

    $categoriesLastModified = $write('sitemap-categories.xml', $categories, $categoriesLastModified);
    $index->add(SitemapEntry::create(url('/sitemap-categories.xml'))->setLastModificationDate($categoriesLastModified));

    $tags = SitemapFile::create();
    $tagsLastModified = null;

    AiTag::query()
        ->whereHas('images', $publicImageFilter)
        ->orderBy('name')
        ->get()
        ->each(function (AiTag $tag) use ($maxDate, $tags, $url, &$tagsLastModified): void {
            $lastModified = $tag->images()
                ->publiclyVisible()
                ->max('ai_images.updated_at') ?? $tag->updated_at;

            $tags->add($url(route('tags.show', $tag), $lastModified));
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
        ->each(function (AiImage $image) use ($latestImages, $maxDate, $url, &$latestImagesLastModified): void {
            $lastModified = $image->updated_at ?? $image->published_at ?? $image->created_at;

            $latestImages->add($url(route('images.show', $image), $lastModified));
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
            ->chunkById(1000, function ($chunk) use ($images, $maxDate, $url, &$imagesLastModified): void {
                foreach ($chunk as $image) {
                    $lastModified = $image->updated_at ?? $image->published_at ?? $image->created_at;

                    $images->add($url(route('images.show', $image), $lastModified));
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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
