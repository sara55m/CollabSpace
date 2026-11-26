<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'conversation_id'=>$this->conversation_id,
            'sender_id'       => $this->user_id,
            'is_mine'         => $this->user_id !== null
                && $this->user_id === $request->user()?->id,
            'body'            => $this->body,
            'created_at'      => $this->created_at->toIso8601String(),
            'edited_at'      => $this->edited_at->toIso8601String(),

            'sender'=>$this->whenLoaded('sender',fn()=>$this->sender ? [
                'id'=>$this->sender->id,
                'name'=>$this->sender->name,
                'avatar'=>$this->sender->avatar,
            ]:null),

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
        ];
    }
}
