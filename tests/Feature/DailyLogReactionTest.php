<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLogReactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin']
        );
    }

    public function test_user_can_toggle_emoji_reaction_on_daily_log(): void
    {
        $adminRole = \App\Models\Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $log = DailyLog::create([
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
            'log_number' => 'CH20260722001',
            'category' => 'development',
            'description' => 'Test log description for reactions',
            'is_automated' => false,
        ]);

        // 1. Add reaction 👍
        $response = $this->actingAs($user)
            ->post(route('daily-logs.react', $log), [
                'emoji' => '👍',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('daily_log_reactions', [
            'daily_log_id' => $log->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        // 2. Toggle off reaction 👍
        $response = $this->actingAs($user)
            ->post(route('daily-logs.react', $log), [
                'emoji' => '👍',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('daily_log_reactions', [
            'daily_log_id' => $log->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_daily_logs_index_includes_reactions(): void
    {
        $adminRole = \App\Models\Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $log = DailyLog::create([
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
            'log_number' => 'CH20260722002',
            'category' => 'development',
            'description' => 'Another test log description',
            'is_automated' => false,
        ]);

        $log->reactions()->create([
            'user_id' => $user->id,
            'emoji' => '🔥',
        ]);

        $response = $this->actingAs($user)
            ->get(route('daily-logs', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('daily-logs/page')
            ->has('logs.0.reactions', 1)
            ->where('logs.0.reactions.0.emoji', '🔥')
        );
    }
}
