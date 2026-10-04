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

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json([
            'count' => $count,
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['data' => $item->fresh()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => ['message' => 'Notifications marked as read.']]);
    }
}
