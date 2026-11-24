<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teams\IndexRequest;
use App\Http\Requests\Teams\StoreRequest;
use App\Http\Requests\Teams\UpdateRequest;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TeamResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{

    public function index(IndexRequest $request,Project $project)
    {
        //user must be a member of the project
        Gate::authorize('view',$project);

        $filters=$request->validated();

        $teams=$project->teams()
        ->when($filters['name'] ?? null,fn($q,$name)=>$q->where('name','like',"%{$name}%"))
        ->with('members:id,name')
        ->withCount('members')
        ->latest()
        ->paginate($filters['per_page'] ?? 15)
        ->withQueryString();;

        return TeamResource::collection($teams)
        ->additional(['message' => __('messages.teams_returned')]);

    }
    public function store(StoreRequest $request,Project $project): JsonResponse
    {
        $validated=$request->validated();

        $team=DB::transaction(function () use ($validated,$project) {
            $team=$project->teams()->create([
                'name'=>$validated['name'],
            ]);

            //sync team members
            $team->members()->sync($this->pivotPayload($validated['members']));

            return $team->load('members:id,name')->loadCount('members');
        });

        return response()->json([
            'message' => __('messages.team_created'),
            'data'    => new TeamResource($team),
        ], 201);
    }

    public function show(Project $project,Team $team): JsonResponse
    {
        Gate::authorize('view',$project);

        $team->load('members:id,name')->loadCount('members');

        return response()->json([
            'message'=>__("messages.team_returned"),
            'data'=>new TeamResource($team),
        ]);
    }

    public function update(UpdateRequest $request,Project $project,Team $team): JsonResponse
    {
        $validated=$request->validated();

        DB::transaction(function () use ($validated,$team) {
            if(isset($validated['name'])){
                $team->update([
                    'name'=>$validated['name'],
                ]);
            }

            if(isset($validated['members'])){
                //sync team members
                $team->members()->sync($this->pivotPayload($validated['members']));
            }
        });

        $team->load('members:id,name')->loadCount('members');

        return response()->json([
            'message' => __('messages.team_updated'),
            'data'    => new TeamResource($team),
        ]);
    }

    public function destroy(Project $project,Team $team): JsonResponse
    {
        Gate::authorize('delete', $team);

        DB::transaction(function () use ($team) {
            $team->members()->detach();
            $team->delete();
        });

        return response()->json([
            'message' => __('messages.team_deleted'),
        ]);
    }

    private function pivotPayload(array $members): array
    {
        //map member ids to role and position
        return collect($members)->mapWithKeys(fn ($member) => [
            $member['id'] => [
                'role'     => $member['role'],
                'position' => $member['position'] ?? null,
            ],
        ])->all();
    }
}
