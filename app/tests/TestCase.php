<?php

namespace Tests;

use App\Jobs\GenerateCategoryDescription;
use App\Jobs\GenerateTagDescription;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AppSettings::flush();
        Cache::flush();
        Bus::fake([GenerateCategoryDescription::class, GenerateTagDescription::class]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
