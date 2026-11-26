<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class Message extends Model
{
    protected $fillable = ['body','user_id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class,'attachable');
    }

    public function withinDeleteWindow(): bool
    {
        //a message can be deleted if it is within the allowed duration(ex:15 minutes)
        return $this->created_at->gt(now()->subMinutes(config('conversation.delete_window_minutes')));
    }

    //set conversation last_message_at as the time the message was sent
    protected static function booted(): void
    {
        static::created(fn (Message $m) => $m->conversation()->update(['last_message_at' => $m->created_at]));

        static::deleted(function (Message $m) {
            //re-compute last_read_at to point at the latest message before the deleted one
            $m->conversation()->update([
                'last_message_at' => Message::where('conversation_id', $m->conversation_id)->max('created_at'),
            ]);
            //clean-up deleted files from storage
            DB::afterCommit(function () use ($m) {
                Storage::deleteDirectory("conversations/{$m->conversation_id}/messages/{$m->id}");
            });
        });
    }
}
