<?php

namespace App\Http\Controllers\Api\Flats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Flats\StoreFlatRequest;
use App\Http\Requests\Api\Flats\UpdateFlatRequest;
use App\Http\Resources\Api\Flats\FlatResource;
use App\Models\Flat;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FlatController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Flat::class);

        return FlatResource::collection(Flat::query()->with('owner')->latest()->paginate());
    }

    public function store(StoreFlatRequest $request): FlatResource
    {
        Gate::authorize('create', Flat::class);
        $flat = Flat::create($request->validated());

        return new FlatResource($flat->load('owner'));
    }

    public function show(Flat $flat): FlatResource
    {
        Gate::authorize('view', $flat);

        return new FlatResource($flat->load('owner'));
    }

    public function update(UpdateFlatRequest $request, Flat $flat): FlatResource
    {
        Gate::authorize('update', $flat);
        $flat->update($request->validated());

        return new FlatResource($flat->fresh('owner'));
    }

    public function destroy(Flat $flat): Response
    {
        Gate::authorize('delete', $flat);
        $flat->delete();

        return response()->noContent();
    }
}
