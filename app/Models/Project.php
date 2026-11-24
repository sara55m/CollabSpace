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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class,'attachable');
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
        static::deleted(fn (Project $p) => Storage::deleteDirectory("projects/{$p->id}"));
    }

    public function scopeVisibleTo(Builder $query,User $user)
    {
        return $query->whereHas('members',function(Builder $q) use($user){
            $q->whereKey($user->id);
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
        ->when($filters['name'] ?? null,fn($q,$v)=>$q->where('name','like',"%{$v}%"))
        ->when($filters['status'] ?? null,fn($q,$v)=>$q->where('status',$v))
        ->when($filters['priority'] ?? null,fn($q,$v)=>$q->where('priority',$v))
        ->when($filters['start_date_from'] ?? null,fn($q,$v)=>$q->whereDate('start_date','>=',$v))
        ->when($filters['start_date_to'] ?? null,fn($q,$v)=>$q->whereDate('start_date','<=',$v))
        ->when($filters['deadline_from'] ?? null,fn($q,$v)=>$q->whereDate('deadline','>=',$v))
        ->when($filters['deadline_to'] ?? null,fn($q,$v)=>$q->whereDate('deadline','<=',$v));
    }

}
