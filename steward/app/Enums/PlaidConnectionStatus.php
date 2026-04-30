<?php
namespace App\Enums;

enum PlaidConnectionStatus: string
{
    case Active = 'active';
    case Error = 'error';
    case NeedsReauth = 'needs_reauth';
}
