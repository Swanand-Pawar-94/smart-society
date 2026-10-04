<?php

namespace App\Http\Controllers\Api\Visitors;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\Visitor;
use App\Services\Notifications\FirebaseCloudMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Simple visitor request endpoint for testing the FCM notification flow.
 *
 * Any authenticated user can POST here. If the user is a RESIDENT, the
 * visitor is linked to their own flat automatically. This makes it possible
 * to test the full notification loop on a single device:
 *   1. Login as resident -> FCM token registered.
 *   2. POST /api/visitor-requests -> visitor created -> FCM sent to same device.
 *   3. Device receives notification.
 */
class VisitorRequestController extends Controller
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcm
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_name'  => ['required', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'string', 'max:25'],
            'purpose'       => ['nullable', 'string', 'max:255'],
            'visitor_type'  => ['nullable', 'string', 'in:GUEST,DELIVERY,CAB,SERVICE_PROVIDER,OTHER'],
            // flat_id is optional - if omitted we use the authenticated resident's flat
            'flat_id'       => ['nullable', 'integer', 'exists:flats,id'],
            'resident_id'   => ['nullable', 'integer', 'exists:residents,id'],
        ]);

        $user = $request->user();

        // Resolve resident & flat ─────────────────────────────────────────────
        if (! empty($validated['resident_id'])) {
            $resident = Resident::with('flat', 'user')->find($validated['resident_id']);
        } elseif (! empty($validated['flat_id'])) {
            $resident = Resident::with('flat', 'user')
                ->where('flat_id', $validated['flat_id'])
                ->where('is_primary_contact', true)
                ->first()
                ?? Resident::with('flat', 'user')->where('flat_id', $validated['flat_id'])->first();
        } else {
            // Fall back to the authenticated user's own resident record
            $resident = Resident::with('flat', 'user')
                ->where('user_id', $user->id)
                ->first();
        }

        if (! $resident || ! $resident->flat) {
            return response()->json([
                'message' => 'Could not determine a resident/flat for this request. ' .
                    'Pass flat_id or resident_id, or ensure the authenticated user is linked to a flat.',
                'errors'  => ['flat_id' => ['No matching flat/resident found.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create visitor record ───────────────────────────────────────────────
        $visitor = Visitor::create([
            'visitor_name'          => $validated['visitor_name'],
            'mobile_number'         => $validated['mobile_number'] ?? null,
            'purpose'               => $validated['purpose'] ?? 'Test visitor request',
            'visitor_type'          => $validated['visitor_type'] ?? Visitor::TYPE_GUEST,
            'flat_id'               => $resident->flat_id,
            'resident_id'           => $resident->id,
            'entry_status'          => Visitor::ENTRY_WAITING,
            'approval_status'       => Visitor::APPROVAL_PENDING,
            'created_by_security_id' => null,
        ]);

        $visitor->setRelation('flat', $resident->flat);

        Log::info('[VisitorRequest] Visitor created', [
            'visitor_id'  => $visitor->id,
            'visitor_name' => $visitor->visitor_name,
            'flat_id'     => $visitor->flat_id,
            'resident_id' => $visitor->resident_id,
            'user_id'     => $user->id,
        ]);

        // Fire FCM push notification ──────────────────────────────────────────
        $residentUser = $resident->user;
        $fcmResult    = ['status' => 'SKIPPED', 'reason' => 'Resident has no linked user'];

        if ($residentUser) {
            try {
                $fcmResult = $this->fcm->sendVisitorApprovalNotification($residentUser, $visitor);
                Log::info('[VisitorRequest] FCM dispatch result', [
                    'visitor_id' => $visitor->id,
                    'fcm_status' => $fcmResult['status'] ?? 'UNKNOWN',
                ]);
            } catch (\Throwable $e) {
                Log::error('[VisitorRequest] FCM dispatch threw an exception', [
                    'visitor_id' => $visitor->id,
                    'error'      => $e->getMessage(),
                ]);
                $fcmResult = ['status' => 'EXCEPTION', 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => 'Visitor request created successfully.',
            'data'    => [
                'visitor'       => [
                    'id'              => $visitor->id,
                    'visitor_name'    => $visitor->visitor_name,
                    'mobile_number'   => $visitor->mobile_number,
                    'purpose'         => $visitor->purpose,
                    'visitor_type'    => $visitor->visitor_type,
                    'approval_status' => $visitor->approval_status,
                    'entry_status'    => $visitor->entry_status,
                    'flat_id'         => $visitor->flat_id,
                    'flat_number'     => $resident->flat->flat_number ?? null,
                    'building'        => $resident->flat->building ?? null,
                    'resident_id'     => $visitor->resident_id,
                    'created_at'      => $visitor->created_at,
                ],
                'fcm_notification' => $fcmResult,
            ],
        ], Response::HTTP_CREATED);
    }
}