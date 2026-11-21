<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamUser extends Pivot
{
    protected $fillable=['team_id','user_id','role','position'];

    protected function casts()
    {
        return [
            'role'=>TeamRole::class,
        ];
    }
}
