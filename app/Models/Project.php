<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    protected $fillable=['name','type','description','start_date','deadline','status','priority','owner_id'];

    protected function casts()
    {
        return [
            'start_date'=>'date',
            'deadline'=>'date',
            'status'=>ProjectStatus::class,
            'priority'=>ProjectPriority::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class,'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    protected static function booted(): void
    {
        static::deleting(fn (Project $p) => Storage::deleteDirectory("projects/{$p->id}"));
    }


}
