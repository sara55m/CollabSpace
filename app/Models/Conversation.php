<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Casts\Attribute;


class Conversation extends Model
{
    protected $fillable = ['title','avatar','type', 'project_id', 'direct_key'];

    protected function casts(): array
    {
        return
        [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime'
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    //conversation has many attachments through messages
    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Attachment::class,
            Message::class,
            'conversation_id',
            'attachable_id'
            )->where('attachments.attachable_type',(new Message)->getMorphClass());
    }

    protected static function booted(): void
    {
        static::deleted(function (Conversation $c) {
            DB::afterCommit(function () use ($c) {
                Storage::deleteDirectory("conversations/{$c->id}");
            });
        });
    }

    protected function avatar(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value
            ? Storage::disk('public')->url($value)
            : null);
    }

}
