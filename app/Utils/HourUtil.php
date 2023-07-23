<?php

namespace App\Utils;

use App\Services\TelegramService;

class HourUtil
{
    public static function formatTime($text)
    {
        return str_pad($text,2,"0",STR_PAD_LEFT);
    }

}