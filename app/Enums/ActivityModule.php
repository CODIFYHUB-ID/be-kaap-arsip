<?php

namespace App\Enums;

enum ActivityModule: string
{
    case AUTH = 'AUTH';
    case DASHBOARD = 'DASHBOARD';
    case MITRA = 'MITRA';
    case DOCUMENT = 'DOCUMENT';
    case LETTER = 'LETTER';
    case REPORT = 'REPORT';
    case RECYCLE_BIN = 'RECYCLE_BIN';
    case USER = 'USER';
    case ROLE = 'ROLE';
    case PERMISSION = 'PERMISSION';
    case CATEGORY = 'CATEGORY';
    case SYSTEM = 'SYSTEM';
    case RECEIPT = 'RECEIPT';
}
