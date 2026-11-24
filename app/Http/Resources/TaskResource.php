<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'title'        =>$this->title,
            'status'      =>$this->status->value,
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'due_date'    => $this->due_date?->format('Y-m-d'),
            'priority'    =>$this->priority->value,
            'progress'=>$this->progress,

            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),

            'sort_order'   => $this->sort_order,

            'team_id'      => $this->team_id,
            'reviewer_id'  => $this->reviewer_id,

            'assignees'     =>$this->whenLoaded('assignees',fn()=>$this->assignees->map(fn ($assignee) => [
                'id'    => $assignee->id,
                'name'  => $assignee->name,
            ])),

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'attachments_count'=>$this->whenCounted('attachments_count'),
            'assignees_count'=>$this->whenCounted('assignees_count'),
        ];
    }
}
