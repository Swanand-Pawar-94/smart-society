<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffMemberController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => StaffMember::query()->with('user:id,name,email,role')->latest()->paginate()]);
    }

    public function store(Request $request): JsonResponse
    {
        $staff = StaffMember::create($request->validate([
            'user_id' => ['nullable', 'exists:users,id', 'unique:staff_members,user_id'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:staff_members,employee_id'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['required', 'string', 'max:100'],
            'shift' => ['nullable', 'in:MORNING,EVENING,NIGHT,ROTATING'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE,ON_LEAVE'],
            'joining_date' => ['required', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:20'],
        ]));

        return response()->json(['data' => $staff->load('user:id,name,email,role')], 201);
    }

    public function show(StaffMember $staffMember): JsonResponse
    {
        return response()->json(['data' => $staffMember->load('user:id,name,email,role')]);
    }

    public function update(Request $request, StaffMember $staffMember): JsonResponse
    {
        if ($request->has('status')) {
            $request->merge(['status' => strtoupper((string) $request->input('status'))]);
        }

        $staffMember->update($request->validate([
            'user_id' => ['sometimes', 'nullable', 'exists:users,id', 'unique:staff_members,user_id,'.$staffMember->id],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:staff_members,employee_id,'.$staffMember->id],
            'name' => ['sometimes', 'string', 'max:255'], 'mobile' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'designation' => ['sometimes', 'string', 'max:100'],
            'shift' => ['sometimes', 'nullable', 'in:MORNING,EVENING,NIGHT,ROTATING'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE,ON_LEAVE'],
            'joining_date' => ['sometimes', 'date'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]));

        return response()->json(['data' => $staffMember->fresh('user:id,name,email,role')]);
    }

    public function updateStatus(Request $request, StaffMember $staffMember): JsonResponse
    {
        $status = strtoupper((string) $request->input('status'));
        $request->merge(['status' => $status]);

        $data = $request->validate([
            'status' => ['required', 'in:ACTIVE,INACTIVE,ON_LEAVE'],
        ]);

        $staffMember->update(['status' => $data['status']]);

        $message = $data['status'] === StaffMember::STATUS_ACTIVE
            ? 'Staff account activated successfully.'
            : ($data['status'] === StaffMember::STATUS_INACTIVE
                ? 'Staff account deactivated successfully.'
                : 'Staff account status updated successfully.');

        return response()->json([
            'message' => $message,
            'data' => $staffMember->fresh('user:id,name,email,role'),
        ]);
    }
}

