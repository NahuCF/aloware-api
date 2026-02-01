<?php

namespace App\Enums;

enum UserStatus: string
{
    case Available = 'available';
    case Away = 'away';
    case OnCall = 'on_call';
    case Offline = 'offline';
}
