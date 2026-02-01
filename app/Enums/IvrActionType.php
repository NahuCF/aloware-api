<?php

namespace App\Enums;

enum IvrActionType: string
{
    case Menu = 'menu';
    case RouteToSkill = 'route_to_skill';
    case RouteToUser = 'route_to_user';
    case RouteToLine = 'route_to_line';
    case ForwardToAi = 'forward_to_ai';
}
