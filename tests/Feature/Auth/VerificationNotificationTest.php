<?php

use App\Mail\EmailOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('resend sends a new otp code', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect();

    Mail::assertSent(EmailOtpMail::class);
    expect($user->fresh()->email_otp_code)->not->toBeNull();
});

test('resend is rate limited after repeated attempts', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)->post(route('verification.send'));
    }

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertSessionHasErrors('code');
});
