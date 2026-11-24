<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name'=>$this->name,
            'members'     =>$this->whenLoaded('members',fn()=>$this->members->map(fn ($member) => [
                'id'      => $member->id,
                'name'    => $member->name,
                'role'    =>$member->pivot->role,
                'position'=>$member->pivot->position,
            ])),

            'members_count'=>$this->whenCounted('members_count'),
        ];
    }
}
