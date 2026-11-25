<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    protected $fillable = ['team_id','reviewer_id','title','description','status','priority','progress','sort_order','due_date','start_date',
    'completed_at'];

    protected function casts(): array
    {
        return [
            'priority'     =>TaskPriority::class,
            'status'       =>TaskStatus::class,
            'progress'     => 'integer',
            'due_date'     =>'date',
            'start_date'   =>'date',
            'completed_at' =>'datetime',
        ];
    }

    public function project():BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class,'reviewer_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class,'attachable');
    }

    public bool $afterCommit = true;

    protected static function booted(): void
    {
        static::deleted(function (Task $task) {
            Storage::deleteDirectory("projects/{$task->project_id}/tasks/{$task->id}");
        });
    }

    public function scopeFilter(Builder $query,array $filters): Builder
    {
        return $query
        ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
        ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
        ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
        ->when($filters['team_id'] ?? null, fn ($q, $t) => $q->where('team_id', $t));
    }


}
