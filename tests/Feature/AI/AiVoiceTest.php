<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Services\AiService;
use App\Services\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    (new RoleSeeder())->run();
    (new RolePermissionSeeder())->run();
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);
    $this->user = User::factory()->create();
    $role = Role::where('name', 'super_admin')->firstOrFail();
    CompanyUser::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'role_id' => $role->id, 'is_default' => true, 'joined_at' => now()]);
    $this->user->forceFill(['current_company_id' => $this->company->id])->save();
});

test('voice endpoint only returns a transcript and does not create an AI action', function () {
    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('transcribeAudio')->once()->andReturn('Buat draft maintenance untuk equipment EQ-01.');
    });

    $response = $this->actingAs($this->user)->post('/ai/voice/transcribe', [
        'audio' => UploadedFile::fake()->create('voice.webm', 100, 'video/webm'),
        'language' => 'id',
    ]);

    $response->assertOk()->assertJsonPath('transcript', 'Buat draft maintenance untuk equipment EQ-01.')
        ->assertJsonPath('next_step', 'review_and_send_to_copilot');
    expect(\App\Models\AiActionRun::count())->toBe(0);
});
