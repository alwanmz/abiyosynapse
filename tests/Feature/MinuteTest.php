<?php

namespace Tests\Feature;

use App\Models\Minute;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinuteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin']
        );
    }

    public function test_user_can_view_edit_minute_page(): void
    {
        $adminRole = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $minute = Minute::create([
            'created_by' => $user->id,
            'title' => 'Meeting Internal Web',
            'meeting_date' => '2026-07-25',
            'location' => 'Zoom meeting',
        ]);

        $response = $this->actingAs($user)->get(route('minutes.edit', $minute));

        $response->assertOk();
    }

    public function test_user_can_update_minute_with_method_spoofing(): void
    {
        $adminRole = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $minute = Minute::create([
            'created_by' => $user->id,
            'title' => 'Meeting Internal Web',
            'meeting_date' => '2026-07-25',
            'location' => 'Zoom meeting',
        ]);

        $response = $this->actingAs($user)->post(route('minutes.update', $minute), [
            '_method' => 'put',
            'title' => 'Meeting Internal Web Updated',
            'meeting_date' => '2026-07-25',
            'location' => 'Google Meet',
        ]);

        $response->assertRedirect(route('minutes.show', $minute));

        $this->assertDatabaseHas('minutes', [
            'id' => $minute->id,
            'title' => 'Meeting Internal Web Updated',
            'location' => 'Google Meet',
        ]);
    }
}
