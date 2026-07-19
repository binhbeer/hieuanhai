<?php

namespace Tests\Feature;

use App\Actions\DeleteUserAccount;
use App\Events\AiImageCompleted;
use App\Exceptions\AccountDeletedException;
use App\Jobs\CreateAiImage;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\StudioProject;
use App\Models\User;
use App\Services\ImageCreationService;
use App\Support\UserActivityLock;
use App\Support\UserSessionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_and_unverified_users_can_delete_their_accounts(): void
    {
        foreach ([User::factory()->create(), User::factory()->unverified()->create()] as $user) {
            Livewire::actingAs($user)
                ->test('settings.profile')
                ->assertSee(__('Delete account'));

            Livewire::actingAs($user)
                ->test('settings.delete-user-modal')
                ->set('password', 'password')
                ->call('deleteUser')
                ->assertHasNoErrors()
                ->assertRedirect('/');

            $this->assertNull($user->fresh());
        }
    }

    public function test_wrong_password_does_not_delete_account_data(): void
    {
        $user = User::factory()->create();
        $image = $this->imageFor($user);

        Livewire::actingAs($user)
            ->test('settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
        $this->assertNotNull($image->fresh());
    }

    public function test_account_deletion_removes_owned_records_and_files_without_touching_other_users(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->imageFor($user, 'owned');
        $otherImage = $this->imageFor($other, 'other');
        $favoriteTarget = $this->imageFor($other, 'favorite-target');
        MediaFavorite::create(['user_id' => $user->id, 'media_id' => $favoriteTarget->id]);

        $owned->addMediaFromString('result')->usingFileName('owned.png')->toMediaCollection('result');
        $user->addMediaFromString('avatar')->usingFileName('avatar.png')->toMediaCollection('avatar');

        foreach (['owned-result.png', 'pending.jpg', 'source.jpg', 'studio.jpg', 'avatar-legacy.jpg'] as $file) {
            Storage::disk('public')->put($file, $file);
        }
        $owned->update([
            'result_path' => 'owned-result.png',
            'request_meta' => ['pending_uploads' => [['path' => 'pending.jpg']]],
            'response_meta' => ['source_paths' => ['source.jpg']],
        ]);
        $user->forceFill(['avatar_path' => 'avatar-legacy.jpg'])->save();

        StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Owned project',
            'input_paths' => ['product' => 'studio.jpg'],
        ]);
        $otherProject = StudioProject::create([
            'user_id' => $other->id,
            'tool' => 'product-detail',
            'name' => 'Other project',
        ]);

        $key = ApiKey::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'owned-key'),
            'token_prefix' => 'owned',
            'quota_limit' => 10,
        ]);
        ApiRequest::create([
            'api_key_id' => $key->id,
            'user_id' => $user->id,
            'media_id' => $owned->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'duration_ms' => 10,
        ]);
        DB::table('sessions')->insert([
            'id' => 'owned-session',
            'user_id' => $user->id,
            'payload' => 'owned',
            'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'owned-token',
            'created_at' => now(),
        ]);

        app(DeleteUserAccount::class)($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('generated_media', ['id' => $owned->id]);
        $this->assertDatabaseMissing('media', ['model_type' => User::class, 'model_id' => $user->id]);
        $this->assertDatabaseMissing('media', ['model_type' => GeneratedMedia::class, 'model_id' => $owned->id]);
        $this->assertDatabaseMissing('studio_projects', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
        $this->assertDatabaseMissing('api_requests', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertSame(0, $favoriteTarget->fresh()->favorites_count);
        foreach (['owned-result.png', 'pending.jpg', 'source.jpg', 'studio.jpg', 'avatar-legacy.jpg'] as $file) {
            $this->assertFalse(Storage::disk('public')->exists($file));
        }

        $this->assertNotNull($other->fresh());
        $this->assertNotNull($otherImage->fresh());
        $this->assertNotNull($otherProject->fresh());
    }

    public function test_deletion_marker_blocks_new_user_activity_until_cleanup_finishes(): void
    {
        config(['cache.stores.deletion-test' => ['driver' => 'array']]);
        $cache = Cache::store('deletion-test');
        $lock = new UserActivityLock($cache);
        $ran = false;

        $cache->put('user-deleting:42', true, 60);

        try {
            $lock->run(42, function () use (&$ran): void {
                $ran = true;
            });
            $this->fail('Expected account deletion marker to block activity.');
        } catch (AccountDeletedException) {
            $this->assertFalse($ran);
        } finally {
            $cache->forget('user-deleting:42');
        }
    }

    public function test_session_cleanup_always_removes_database_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'cleanup-session',
            'user_id' => $user->id,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
        config(['session.driver' => 'array']);

        app(UserSessionData::class)->delete($user->id);

        $this->assertDatabaseMissing('sessions', ['id' => 'cleanup-session']);
    }

    public function test_queued_job_after_account_deletion_cleans_abandoned_row_and_upload(): void
    {
        Storage::fake('public');
        Event::fake([AiImageCompleted::class]);
        $user = User::factory()->create();
        Storage::disk('public')->put('pending-after-delete.jpg', 'pending');
        $image = $this->imageFor($user, 'queued');
        $image->update(['request_meta' => ['pending_uploads' => [['path' => 'pending-after-delete.jpg']]]]);
        $job = new CreateAiImage($image->id, $user->id);

        $user->delete();
        $job->handle(app(ImageCreationService::class));

        $this->assertNull($image->fresh());
        Storage::disk('public')->assertMissing('pending-after-delete.jpg');
        Event::assertNotDispatched(AiImageCompleted::class);
    }

    private function imageFor(User $user, string $visitorKey = 'test'): GeneratedMedia
    {
        return GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => $visitorKey,
            'prompt' => 'Test prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);
    }
}
