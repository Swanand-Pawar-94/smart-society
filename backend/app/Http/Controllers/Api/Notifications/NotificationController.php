<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notifications()->latest()->paginate()]);
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === $request->user()::class, 403);
        $notification->markAsRead();

        return response()->json(['data' => $notification->fresh()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => ['message' => 'Notifications marked as read.']]);
    }
}
