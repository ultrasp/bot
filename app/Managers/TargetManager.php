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
    const LADY_SPREADSHEET_ID = '1A566YC7EZxgxXAfKf1KA53xeQQuya_sBEgx-4n3BYgk';

    public static function writeLid($name, $phone, $source)
    {
        $excels = [
            1 => self::TARGET_SPREADSHEET_ID,
            3 => self::LADY_SPREADSHEET_ID,
        ];

        $gooleService = new GoogleService();
        $newRow = [
            date('Y-m-d H:i:s'),
            $name,
            $phone
        ];
        $rows = [$newRow]; // you can append several rows at once
        $gooleService->appendValues($excels[$source], 'Sheet1', $rows);
    }


}