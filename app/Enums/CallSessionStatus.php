<?php

namespace App\Enums;

enum CallSessionStatus: string
{
    case InProgress = 'in_progress';
    case Transferred = 'transferred';
    case Completed = 'completed';
}
