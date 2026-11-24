<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AttachmentResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        =>$this->name,
            'type'        =>$this->type,
            'status'      =>$this->status,
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'deadline'    => $this->deadline?->format('Y-m-d'),
            'priority'    =>$this->priority,

            'members'     =>$this->whenLoaded('members',fn()=>$this->members->map(fn ($member) => [
                'id'    => $member->id,
                'name'  => $member->name,
            ])),

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'teams' => TeamResource::collection(
                $this->whenLoaded('teams')
            ),

            'tasks' => TaskResource::collection(
                $this->whenLoaded('tasks')
            ),

            'attachments_count' =>$this->whenCounted('attachments_count'),

            'teams_count' =>$this->whenCounted('teams_count'),

            'tasks_count' =>$this->whenCounted('tasks_count'),
        ];
    }
}
