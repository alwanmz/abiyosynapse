<?php

use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('email verification screen can be rendered and sends a code', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('auth/verify-email-otp')
        ->where('email', $user->email));

    Mail::assertSent(\App\Mail\EmailOtpMail::class);
    expect($user->fresh()->email_otp_code)->not->toBeNull();
    expect($user->fresh()->email_otp_expires_at)->not->toBeNull();
});

test('verification screen does not resend a code if one is still outstanding', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();
    app(EmailOtpService::class)->sendCode($user);
    $firstCode = $user->fresh()->email_otp_code;

    Mail::fake(); // reset the "sent" tracker

    $this->actingAs($user)->get(route('verification.notice'));

    Mail::assertNotSent(\App\Mail\EmailOtpMail::class);
    expect($user->fresh()->email_otp_code)->toBe($firstCode);
});

test('correct code verifies the email', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_otp_code' => Hash::make('123456'),
        'email_otp_expires_at' => now()->addMinutes(10),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '123456',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->email_otp_code)->toBeNull();
});

test('wrong code does not verify the email', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_otp_code' => Hash::make('123456'),
        'email_otp_expires_at' => now()->addMinutes(10),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '999999',
    ]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('expired code does not verify the email', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_otp_code' => Hash::make('123456'),
        'email_otp_expires_at' => now()->subMinute(),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => '123456',
    ]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a used code cannot be replayed', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_otp_code' => Hash::make('123456'),
        'email_otp_expires_at' => now()->addMinutes(10),
    ])->save();

    $this->actingAs($user)->post(route('verification.verify'), ['code' => '123456']);

    $response = $this->actingAs($user)->post(route('verification.verify'), ['code' => '123456']);

    $response->assertSessionHasErrors('code');
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertRedirect(route('dashboard'));
});
