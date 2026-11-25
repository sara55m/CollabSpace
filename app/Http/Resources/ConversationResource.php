<?php

namespace App\Http\Resources;

use App\Enums\ConversationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //if the conversation is direct load the other person
        $isDirect=$this->type === ConversationType::Direct;
        $other = $isDirect && $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', '!=', $request->user()->id)
            : null;

        return [
            'id'    => $this->id,
            'type'  => $this->type->value,
            //if the conversation is direct its title and photo are the other person name and avatar
            'title' => $isDirect
            ? ($other?->name ?? __('messages.deleted_user'))
            : $this->title,
            'avatar' => $isDirect ? $other?->avatar : $this->avatar,

            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'id'         => $this->latestMessage->id,
                'body'       => $this->latestMessage->body,
                'sender_id'  => $this->latestMessage->user_id,
                'created_at' => $this->latestMessage->created_at->toIso8601String(),
            ] : null),

            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count'    => (int) ($this->unread_count ?? 0)
        ];
    }
}
