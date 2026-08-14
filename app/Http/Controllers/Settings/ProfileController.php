<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings (name, email, and avatar).
     *
     * The avatar arrives as a pre-cropped square Blob from the client,
     * so the controller just needs to save it and clean up the old one.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except(['avatar', 'remove_avatar']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $this->safelyDeleteAvatar($user->avatar_path);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->safelyDeleteAvatar($user->avatar_path);
            $user->avatar_path = null;
        }

        $user->save();
        $this->forgetUserCaches();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $this->safelyDeleteAvatar($user->avatar_path);

        Auth::logout();

        $user->delete();
        $this->forgetUserCaches();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function forgetUserCaches(): void
    {
        Cache::forget('teams.index');
        Cache::forget('users.for-teams');
        Cache::forget('users.for-tickets');
        Cache::forget('projects.index');
    }

    protected function safelyDeleteAvatar(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete user avatar', [
                'path' => $path,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
