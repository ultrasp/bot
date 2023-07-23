<?php

namespace App\Utils;

use App\Services\TelegramService;

class BotCommandUtil
{
    public static function isEqualCommand($text, $botCommand)
    {
        if ($botCommand == TelegramService::COMMAND_REGISTER) {
            return self::clearCommand($text) == $botCommand;
        }
        return $text == $botCommand;
    }

    public static function clearCommand($text)
    {
        return substr($text, 1);
    }

    public static function isInCommands($text, $commands)
    {
        $command = self::clearCommand($text);
        return in_array($command, $commands) || in_array($text,$commands);
    }

    public static function isWorkTimeCommand($command)
    {
        return self::isInCommands($command, [TelegramService::COMMAND_COME_TIME, TelegramService::COMMAND_LEAVE_WORK]);
    }


}