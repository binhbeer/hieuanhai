<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\StudioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_manage_studio(): void
    {
        $user = User::factory()->create(['id' => 2]);

        $this->actingAs($user)
            ->get(route('manage.studio.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_and_filter_studio_projects(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $creator = User::factory()->create(['name' => 'Creator Alpha']);
        $otherCreator = User::factory()->create(['name' => 'Creator Beta']);

        $productDraft = StudioProject::create([
            'user_id' => $creator->id,
            'tool' => 'product-detail',
            'name' => 'Alpha product draft',
            'form_data' => [],
            'input_paths' => [],
        ]);
        $posterSubmitted = StudioProject::create([
            'user_id' => $otherCreator->id,
            'tool' => 'marketing-poster',
            'name' => 'Beta poster submitted',
            'form_data' => [],
            'input_paths' => [],
            'submitted_at' => now(),
        ]);
        GeneratedMedia::create([
            'user_id' => $otherCreator->id,
            'visitor_key' => 'studio-media-beta',
            'studio_project_id' => $posterSubmitted->id,
            'prompt' => 'Poster prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/studio-poster.png',
        ]);
        $creating = StudioProject::create([
            'user_id' => $creator->id,
            'tool' => 'product-detail',
            'name' => 'Alpha creating project',
            'form_data' => [],
            'input_paths' => [],
            'submitted_at' => now(),
        ]);
        GeneratedMedia::create([
            'user_id' => $creator->id,
            'visitor_key' => 'studio-media-creating',
            'studio_project_id' => $creating->id,
            'prompt' => 'Creating prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('manage.studio.index'))
            ->assertOk()
            ->assertSee(__('Manage AI Studio'))
            ->assertSee('Alpha product draft')
            ->assertSee('Beta poster submitted')
            ->assertSee('Alpha creating project');

        Livewire::actingAs($admin)
            ->test('pages::manage.studio')
            ->assertSee(__('Total projects'))
            ->assertSee(__('Draft projects'))
            ->assertSee(__('Submitted projects'))
            ->assertSee(__('Studio media'))
            ->set('tool', 'product-detail')
            ->assertSee('Alpha product draft')
            ->assertSee('Alpha creating project')
            ->assertDontSee('Beta poster submitted')
            ->set('tool', 'all')
            ->set('status', 'draft')
            ->assertSee('Alpha product draft')
            ->assertDontSee('Beta poster submitted')
            ->assertDontSee('Alpha creating project')
            ->set('status', 'creating')
            ->assertSee('Alpha creating project')
            ->assertDontSee('Alpha product draft')
            ->assertDontSee('Beta poster submitted')
            ->set('status', 'completed')
            ->assertSee('Beta poster submitted')
            ->assertDontSee('Alpha product draft')
            ->assertDontSee('Alpha creating project')
            ->set('status', 'all')
            ->set('creatorId', (string) $creator->id)
            ->assertSee('Alpha product draft')
            ->assertSee('Alpha creating project')
            ->assertDontSee('Beta poster submitted')
            ->set('search', (string) $productDraft->id)
            ->assertSee('Alpha product draft')
            ->assertDontSee('Alpha creating project');
    }

    public function test_studio_page_groups_project_activity_for_the_last_thirty_days(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        $draft = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Recent draft',
            'form_data' => [],
            'input_paths' => [],
        ]);
        $draft->forceFill(['created_at' => now()->subDay()])->save();

        $submitted = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'marketing-poster',
            'name' => 'Recent submitted',
            'form_data' => [],
            'input_paths' => [],
            'submitted_at' => now()->subDay(),
        ]);
        $submitted->forceFill(['created_at' => now()->subDay()])->save();

        $old = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Old project',
            'form_data' => [],
            'input_paths' => [],
            'submitted_at' => now()->subDays(30),
        ]);
        $old->forceFill(['created_at' => now()->subDays(30)])->save();

        $component = Livewire::actingAs($admin)->test('pages::manage.studio');
        $stats = $component->get('dailyStats');
        $day = collect($stats)->first(fn (array $row): bool => $row['date']->isSameDay(now()->subDay()));

        $this->assertCount(30, $stats);
        $this->assertSame(2, $day['total']);
        $this->assertSame(1, $day['draft']);
        $this->assertSame(1, $day['submitted']);
        $this->assertSame(2, collect($stats)->sum('total'));
        $this->assertSame([
            'total' => 3,
            'draft' => 1,
            'submitted' => 2,
            'media' => 0,
        ], $component->get('stats'));
        $component
            ->assertSee(__('Last 30 days'))
            ->assertSee(__('Created projects'));
    }
}
