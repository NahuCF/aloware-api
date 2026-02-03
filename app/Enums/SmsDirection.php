<?php

namespace App\Enums;

enum SmsDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
