<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Operator = 'operator';
    case Admin = 'admin';
}
