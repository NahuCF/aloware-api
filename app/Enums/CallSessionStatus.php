<?php

namespace App\Enums;

enum CallSessionStatus: string
{
    case InProgress = 'in_progress';
    case Connected = 'connected';
    case Transferred = 'transferred';
    case Completed = 'completed';
}
