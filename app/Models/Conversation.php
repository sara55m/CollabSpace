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
use Illuminate\Support\Carbon;


class Conversation extends Model
{
    protected $fillable = ['title','avatar','type', 'project_id', 'direct_key','is_public'];

    protected function casts(): array
    {
        return
        [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
            'is_public'=>'boolean',
        ];
    }

    public function markReadFor(User $user, ?Carbon $at = null): void
    {
        //update conversation_user last_read_at for that user conversation
        $this->participants()->updateExistingPivot($user->id, [
            'last_read_at' => $at ?? now(),
        ]);
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
        static::deleting(function (Conversation $c) {
            //delete attachment records of conversation messages
            $c->attachments()->delete();
        });

        static::deleted(function (Conversation $c) {
            $avatar = $c->getRawOriginal('avatar');
            DB::afterCommit(function () use ($c,$avatar) {
                //delete conversation attachments form private disk
                Storage::disk('local')->deleteDirectory("conversations/{$c->id}");
                //delete avatars from public disk
                Storage::disk('public')->deleteDirectory("conversations/{$c->id}");
                if ($avatar) {
                    Storage::disk('public')->delete($avatar);
                }
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
