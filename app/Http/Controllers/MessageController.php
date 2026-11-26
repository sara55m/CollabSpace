<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Messages\UpdateRequest;
use App\Models\Conversation;
use App\Http\Requests\Messages\IndexRequest;

class MessageController extends Controller
{
    public function index(IndexRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar', 'attachments'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->validated('per_page') ?? 30);

        return MessageResource::collection($messages)
            ->additional(['message' => __('messages.messages_retrieved')])
            ->response();
    }

    public function update(UpdateRequest $request, Message $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $body = $request->validated('body');

        // only mark as edited when something actually changed
        if ($body !== $message->body) {
            $message->body = $body;
            $message->edited_at = now();
            $message->save();
        }

        $message->load(['sender:id,name,avatar', 'attachments']);

        return (new MessageResource($message))
            ->additional(['message' => __('messages.message_updated')])
            ->response();
    }

    public function destroy(Message $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        DB::transaction(function () use ($message) {
            $message->attachments()->delete();
            $message->delete();
        });

        return response()->json(['message' => __('messages.message_deleted')]);
    }

}
