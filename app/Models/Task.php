<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $fillable = ['team_id','reviewer_id','title','description','status','priority','progress','sort_order','due_date'];

    protected function casts(): array
    {
        return [
            'priority'     =>TaskPriority::class,
            'status'       =>TaskStatus::class,
            'progress'     => 'integer',
            'due_date'     =>'date',
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
}
