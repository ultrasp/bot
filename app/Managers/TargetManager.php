<?php

namespace App\Managers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\GoogleService;
use App\Services\TelegramService;
use App\Utils\BotCommandUtil;
use App\Utils\HourUtil;
use Carbon\Carbon;

class TargetManager
{
    const START_COLUMN = 2;

    const TARGET_SPREADSHEET_ID = '1x4BgNf5nzvgR7PCqo6FbkD5Lr81O_JvvMn12YOfi96U'; 
    public static function writeLid($name,$phone)
    {
        $gooleService = new GoogleService();
        $newRow = [
            date('Y-m-d H:i:s'),
            $name,
            $phone
        ];
        $rows = [$newRow]; // you can append several rows at once
        $gooleService->appendValues(self::TARGET_SPREADSHEET_ID, 'Sheet1', $rows);
   }


}