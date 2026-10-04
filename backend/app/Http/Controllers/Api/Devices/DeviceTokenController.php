<?php

namespace App\Http\Controllers\Api\Devices;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $fcmToken = $request->input('fcm_token') ?? $request->input('token');
        if (! $fcmToken || ! is_string($fcmToken)) {
            return response()->json([
                'message' => 'The fcm_token field is required.',
                'errors' => ['fcm_token' => ['The fcm_token field is required.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $platform = $request->input('platform', 'android');
        $deviceId = $request->input('device_id');
        $user = $request->user();

        $token = UserDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $fcmToken,
            ],
            [
                'platform' => in_array($platform, ['android', 'ios', 'web'], true) ? $platform : 'android',
                'device_id' => $deviceId,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered successfully.',
            'data' => $token,
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): JsonResponse
    {
        $fcmToken = $request->input('fcm_token') ?? $request->input('token');
        if ($fcmToken) {
            UserDeviceToken::where('user_id', $request->user()->id)
                ->where('fcm_token', $fcmToken)
                ->update(['is_active' => false]);
        }

        return response()->json([
            'message' => 'Device token removed successfully.',
        ]);
    }
}