<?php

namespace App\Http\Controllers;

use App\Enums\ConversationType;
use App\Http\Requests\Conversations\IndexRequest;
use App\Http\Requests\Conversations\StoreRequest;
use App\Http\Requests\Conversations\UpdateRequest;
use App\Http\Requests\Messages\StoreRequest as MessagesStoreRequest;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\JsonResponse;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Request;
use App\Http\Requests\Conversations\UpdateAvatarRequest;


class ConversationController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $filters=$request->validated();
        $user=$request->user();

        $conversations=Conversation::query()
        //view only conversations that a user is a participant in
        ->join('conversation_user as cu',function($join) use ($user){
            $join->on('cu.conversation_id','=','conversations.id')
            ->where('cu.user_id',$user->id);
        })
        ->select('conversations.*', 'cu.last_read_at as my_last_read_at')
        //unread conversation messages count(messages sent from other users with created at after conversation_user last_read_at)
        ->selectSub(
            Message::query()
                ->selectRaw('count(*)')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where(fn ($q) => $q->whereNull('messages.user_id')
                                    ->orWhere('messages.user_id', '!=', $user->id))
                ->where(fn ($q) => $q->whereNull('cu.last_read_at')
                                    ->orWhereColumn('messages.created_at', '>', 'cu.last_read_at')),
            'unread_count'
        )
        //hide empty direct chats, but always show project channels
        ->where(fn ($q) => $q
        ->whereNotNull('conversations.last_message_at')
        ->orWhere('conversations.type', ConversationType::Project->value))
        ->when($filters['type'] ?? null,fn($q,$t)=>$q->where('conversations.type',$t))
        ->when($filters['search'] ?? null, function ($q, $term) {
            $like = '%' . addcslashes($term, '\\%_') . '%';

            $q->where('conversations.title', 'like', $like);
        })
        //load last message
        ->with('latestMessage:messages.id,messages.conversation_id,messages.user_id,messages.body,messages.created_at')
        ->orderByDesc('conversations.last_message_at')
        ->orderByDesc('conversations.id')
        ->paginate($filters['per_page'] ?? 20);

        //for direct chats load other person data
        $conversations->getCollection()
            ->where('type', ConversationType::Direct)
            ->load(['participants' => fn ($q) => $q
                ->where('users.id', '!=', $user->id)
                ->select('users.id', 'users.name','users.avatar')]);


        return ConversationResource::collection($conversations)
        ->additional(['message' => __('messages.conversations_retrieved')])
        ->response();

    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        //------Direct Conversation-----
        if ($validated['type'] === ConversationType::Direct->value) {
            // make direct key using user ids (same key for A,B and B,A)
            $key = collect([$user->id, $validated['user_id']])->sort()->implode('_');

            $conversation = Conversation::where('direct_key', $key)->first();

            if (! $conversation) {
                try {
                    $conversation = DB::transaction(function () use ($user, $validated, $key) {
                        $conversation = Conversation::create([
                            'type'       => ConversationType::Direct,
                            'direct_key' => $key,
                        ]);

                        // attach the creator and the other user as participants
                        $conversation->participants()->attach([$validated['user_id'], $user->id]);

                        return $conversation;
                    });
                } catch (UniqueConstraintViolationException) {
                    // a simultaneous request created it first, so return that one
                    $conversation = Conversation::where('direct_key', $key)->firstOrFail();
                }
            }

            // the resource needs the other participant for title and avatar
            $conversation->load(['participants' => fn ($q) => $q
                ->select('users.id', 'users.name', 'users.avatar')]);
        } else {
             //------Project Group Conversation-----
             $storedPath = null;
            try {
                $conversation = DB::transaction(function () use ($user, $validated, $request,&$storedPath) {
                    $conversation = Conversation::create([
                        'type'       => ConversationType::Project,
                        'title'      => $validated['title'],
                        'project_id' => $validated['project_id'],
                    ]);

                    $conversation->participants()->attach(
                        array_unique([$user->id, ...$validated['participant_ids']])
                    );

                    //upload avatar file
                    if ($request->hasFile('avatar')) {
                        $storedPath = $request->file('avatar')
                            ->store("conversations/{$conversation->id}/avatar", 'public');

                        $conversation->update(['avatar' => $storedPath]);
                    }

                    return $conversation;
                });
            } catch (\Throwable $e) {
                if ($storedPath) {
                    Storage::disk('public')->delete($storedPath);
                }

                throw $e;
            }
        }

        return (new ConversationResource($conversation))
            ->additional(['message' => __('messages.conversation_created')])
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function sendMessage(MessagesStoreRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $user = $request->user();
        $storedPaths = [];

        try {
            $message = DB::transaction(function () use ($request, $conversation, $user, &$storedPaths) {
                $message = $conversation->messages()->create([
                    'user_id' => $user->id,
                    'body'    => $request->validated('body'),
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store(
                        "conversations/{$conversation->id}/messages/{$message->id}",
                        'local'
                    );

                    $storedPaths[] = $path;

                    $message->attachments()->create([
                        'path'      => $path,
                        'name'      => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size'      => $file->getSize(),
                    ]);
                }

                // the sender has seen everything up to their own message
                $conversation->participants()->updateExistingPivot($user->id, [
                    'last_read_at' => $message->created_at,
                ]);

                return $message;
            });
        } catch (\Throwable $e) {
            // the database rolled back, so cleanup storage
            Storage::disk('local')->delete($storedPaths);

            throw $e;
        }

        $message->load(['sender:id,name,avatar', 'attachments']);

        return (new MessageResource($message))
            ->additional(['message' => __('messages.message_sent')])
            ->response()
            ->setStatusCode(201);
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->markReadFor($request->user());

        return response()->json([
            'message' => __('messages.marked_as_read'),
            'data'    => ['unread_count' => 0],
        ]);
    }

    public function update(UpdateRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $conversation->update(['title' => $request->validated('title')]);

        return (new ConversationResource($conversation))
            ->additional(['message' => __('messages.conversation_updated')])
            ->response();
    }

    public function updateAvatar(UpdateAvatarRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $oldPath = $conversation->getRawOriginal('avatar');

        $newPath = $request->file('avatar')
            ->store("conversations/{$conversation->id}/avatar", 'public');

        try {
            $conversation->update(['avatar' => $newPath]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($newPath);

            throw $e;
        }

        // delete the old file only after the new one is saved
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return (new ConversationResource($conversation))
            ->additional(['message' => __('messages.conversation_updated')])
            ->response();
    }

    public function destroyAvatar(Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        if ($oldPath = $conversation->getRawOriginal('avatar')) {
            $conversation->update(['avatar' => null]);
            Storage::disk('public')->delete($oldPath);
            //delete public avatars directory
            Storage::disk('public')->deleteDirectory("conversations/{$conversation->id}/avatar");
        }

        return (new ConversationResource($conversation))
            ->additional(['message' => __('messages.conversation_updated')])
            ->response();
    }
}
