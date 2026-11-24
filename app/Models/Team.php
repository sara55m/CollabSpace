<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TeamUser::class)
            ->withPivot('role','position')
            ->withTimestamps();
    }

    public function leader(): BelongsToMany
    {
        return $this->users()->wherePivot('role', TeamRole::Leader);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
