<?php

namespace App\Enums;

enum TaskStatus:string
{
    case Todo       = 'todo';
    case InProgress = 'in_progress';
    case InReview   = 'in_review';
    case Done       = 'done';

    //manage status transitions (to-do-><-in-progress-><-in-review->done->in-progress)
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Todo       => [self::InProgress],
            self::InProgress => [self::Todo, self::InReview],
            self::InReview   => [self::InProgress, self::Done],
            self::Done       => [self::InProgress],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
