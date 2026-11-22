<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }

    //set conversation last_message_at as the time the message was sent
    protected static function booted(): void
    {
        static::created(fn (Message $m) => $m->conversation()->update(['last_message_at' => $m->created_at]));
    }
}
