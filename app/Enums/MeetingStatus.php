<?php

namespace App\Enums;

enum MeetingStatus:string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
