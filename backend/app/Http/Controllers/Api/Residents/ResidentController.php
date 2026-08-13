<?php

namespace App\Http\Controllers\Api\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Residents\StoreResidentRequest;
use App\Http\Requests\Api\Residents\UpdateResidentRequest;
use App\Http\Resources\Api\Residents\ResidentResource;
use App\Models\Resident;
use App\Services\Residents\ResidentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ResidentController extends Controller
{
    public function __construct(private ResidentService $residents) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Resident::class);

        return ResidentResource::collection(Resident::query()->with(['user', 'flat.owner'])->latest()->paginate());
    }

    public function store(StoreResidentRequest $request): ResidentResource
    {
        Gate::authorize('create', Resident::class);
        $resident = $this->residents->create($request->validated());

        return new ResidentResource($resident->load(['user', 'flat.owner']));
    }

    public function show(Resident $resident): ResidentResource
    {
        Gate::authorize('view', $resident);

        return new ResidentResource($resident->load(['user', 'flat.owner']));
    }

    public function update(UpdateResidentRequest $request, Resident $resident): ResidentResource
    {
        Gate::authorize('update', $resident);

        return new ResidentResource($this->residents->update($resident->load('user'), $request->validated()));
    }

    public function destroy(Resident $resident): Response
    {
        Gate::authorize('delete', $resident);
        $resident->delete();

        return response()->noContent();
    }

    public function profile(Request $request): ResidentResource
    {
        $resident = $request->user()->resident()->with(['user.profile', 'flat.owner'])->firstOrFail();
        Gate::authorize('view', $resident);

        return new ResidentResource($resident);
    }

    public function updateProfile(UpdateResidentRequest $request): ResidentResource
    {
        $resident = $request->user()->resident()->with('user')->firstOrFail();
        Gate::authorize('update', $resident);
        $data = $request->validated();
        $profileData = Arr::only($data, ['name', 'email', 'phone', 'password']);
        $updated = $this->residents->update($resident, $profileData);
        $updated->user->profile()->updateOrCreate(
            ['user_id' => $updated->user_id],
            Arr::only($data, ['date_of_birth', 'gender', 'emergency_contact']),
        );

        return new ResidentResource($updated->fresh(['user.profile', 'flat.owner']));
    }
}
