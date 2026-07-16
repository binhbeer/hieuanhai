<?php

namespace Tests\Feature;

use App\Ai\EnglishContentTranslationAgent;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_translates_existing_content_without_overwriting_ready_records(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        $image = GeneratedMedia::create([
            'title' => ['vi' => 'Chân dung studio'],
            'description' => ['vi' => 'Chân dung studio với ánh sáng mềm.'],
            'visitor_key' => 'translation-test',
            'prompt' => 'Portrait in a studio',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/portrait.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
        EnglishContentTranslationAgent::fake([[
            [
                'id' => $image->id,
                'title' => 'Studio portrait',
                'description' => 'A studio portrait with soft lighting and a clean background for polished visual inspiration.',
            ],
        ]]);

        $this->artisan('content:translate-en', ['--type' => 'images', '--limit' => 1])
            ->expectsOutput('Translated 1; skipped 0; failed 0.')
            ->assertSuccessful();

        $image->refresh();
        $this->assertSame('Studio portrait', $image->getTranslationWithoutFallback('title', 'en'));
        $this->assertSame('A studio portrait with soft lighting and a clean background for polished visual inspiration.', $image->getTranslationWithoutFallback('description', 'en'));
        $this->artisan('content:translate-en', ['--type' => 'images'])
            ->expectsOutput('Translated 0; skipped 0; failed 0.')
            ->assertSuccessful();
        $this->assertSame('Studio portrait', $image->fresh()->getTranslationWithoutFallback('title', 'en'));
    }

    public function test_command_generates_unique_english_slugs_for_taxonomy(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        $first = Tag::create(['name' => ['vi' => 'Mèo một'], 'slug' => 'meo-mot']);
        $second = Tag::create(['name' => ['vi' => 'Mèo hai'], 'slug' => 'meo-hai']);
        EnglishContentTranslationAgent::fake([[
            'translations' => [
                ['id' => $first->id, 'title' => 'Cute cat', 'description' => 'Cute cat images with expressive poses and warm details for playful visual inspiration and creative ideas.'],
                ['id' => $second->id, 'title' => 'Cute cat', 'description' => 'Cute cat portraits with gentle lighting and charming expressions for polished visual inspiration and creative ideas.'],
            ],
        ]]);

        $this->artisan('content:translate-en', ['--type' => 'tags'])
            ->expectsOutput('Translated 2; skipped 0; failed 0.')
            ->assertSuccessful();

        $this->assertSame('cute-cat', $first->fresh()->slug_en);
        $this->assertSame('cute-cat-'.$second->id, $second->fresh()->slug_en);
    }
}
