<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    protected $fillable = ['team_id','reviewer_id','title','description','status','priority','progress','sort_order','due_date','start_date'];

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


}
