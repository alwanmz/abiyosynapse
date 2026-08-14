<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? 'unknown',
                    'title' => $notification->data['title'] ?? '',
                    'message' => $notification->data['message'] ?? '',
                    'actor' => $notification->data['updated_by'] 
                        ?? $notification->data['added_by'] 
                        ?? $notification->data['removed_by'] 
                        ?? $notification->data['assigned_by'] 
                        ?? 'System',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->toISOString(),
                    'data' => $notification->data,
                ];
            });

        return Inertia::render('notifications/page', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return back();
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notifications()->delete();

        return back();
    }
}
