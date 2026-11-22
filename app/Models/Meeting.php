<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    protected $fillable = ['project_id', 'title', 'starts_at', 'duration_minutes', 'note'];

    protected function casts()
    {
        return [
            'starts_at'=>'datetime',
            'status'=>MeetingStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class,'organizer_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    //calculate the ending time
    protected function endsAt(): Attribute
    {
        return Attribute::get(fn() => $this->starts_at->copy()->addMinutes($this->duration_minutes) );
    }
}
