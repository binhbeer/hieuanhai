<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Mod = 'mod';
    case User = 'user';
}
