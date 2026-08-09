<?php

namespace App\Enums;

enum ActivityAction: string
{
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case RESTORE = 'RESTORE';
    case UPLOAD = 'UPLOAD';
    case DOWNLOAD = 'DOWNLOAD';
    case VIEW = 'VIEW';
    case EXPORT = 'EXPORT';
    case CHANGE_PERMISSION = 'CHANGE_PERMISSION';
}
