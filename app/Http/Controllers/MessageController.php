<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Messages\UpdateRequest;

class MessageController extends Controller
{
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
