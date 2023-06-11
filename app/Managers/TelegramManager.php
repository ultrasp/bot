<?php

namespace App\Managers;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\TelegramService;
use GuzzleHttp\Client;

class TelegramManager
{

    public function __construct()
    {
    }


    const CALLBACK_HOUR = 'ct_hour';
    const CALLBACK_MINUTE = 'ct_minute';
    const MESSAGE_SELECT_HOUR = 'Ishga kelgan soatizni tanlang';
    const MESSAGE_SELECT_LEAVE_HOUR = 'Ishni tugatgan soatingizni yozing';
    const MESSAGE_SELECT_MINUTE = 'Ishga kelgan daqiqangizni tanlang';
    const MESSAGE_SELECT_LEAVE_MINUTE = 'Ishga tugatgan daqiqangizni tanlang';

    const WORK_START = 'workStart';
    const WORK_END = 'workEnd';

    public function sendHour($chat_id, $commandType, $messageId = null)
    {
        $messageText = $commandType == TelegramManager::WORK_START ? self::MESSAGE_SELECT_HOUR : self::MESSAGE_SELECT_LEAVE_HOUR;
        $date = $commandType . "_" . date('Y-m-d');
        $hourKeyboard = $this->makeHourKeyboard($date);

        $tgService = new TelegramService;
        if ($messageId) {
            $tgService->editsendedMessage($messageId, $chat_id, $messageText, $hourKeyboard);
        } else {
            $tgService->sendMessage($messageText, $chat_id, $hourKeyboard, true);
        }
    }

    public function sendMinute($chatId, $messageId, $hour, $commandType)
    {
        $date = date('Y-m-d');
        $keyboard = $this->makeMinuteKeyboard($commandType . "_" . $date . "_" . $hour);
        $tgService = new TelegramService;
        $tgService->editsendedMessage($messageId, $chatId, ($commandType == TelegramManager::WORK_START ? self::MESSAGE_SELECT_MINUTE : self::MESSAGE_SELECT_LEAVE_MINUTE) . ' (' . $date . ' ' . $hour . ') ', $keyboard);
    }

    public function isCallbackQuery($update)
    {
        return property_exists($update, 'callback_query');
    }

    public function handleCallbackQuery($update)
    {
        $callbackKey = $update->callback_query->data;
        // dd($callbackKey);
        if (str_starts_with($callbackKey, self::CALLBACK_HOUR)) {
            $this->handleHourCallback($update);
        }
        if (str_starts_with($callbackKey, self::CALLBACK_MINUTE)) {
            $this->handleMinuteCallback($update);
        }
    }

    public function handleHourCallback($update)
    {
        $param = substr($update->callback_query->data, strlen(self::CALLBACK_HOUR) + 1);

        list($commandType, $date, $hour) = explode("_", $param);
        // dd($date);
        if ($date != date('Y-m-d')) {
            //send date is passed
            return;
        }
        $this->sendMinute($update->callback_query->message->chat->id, $update->callback_query->message->message_id, $hour, $commandType);

    }

    public function handleMinuteCallback($update)
    {
        $param = substr($update->callback_query->data, strlen(self::CALLBACK_MINUTE) + 1);
        // dd($param);
        list($commandType, $date, $hour, $minute) = explode("_", $param);
        // dd($date);

        if ($minute == self::CALLBACK_BACK_HOUR) {
            $this->sendHour($update->callback_query->message->chat->id, $commandType);
            return;
        }
        if ($date != date('Y-m-d')) {
            //send date is passed
            return;
        }

        $receiver = Receiver::where(['chat_id' => $update->callback_query->from->id])->first();
        $appendText = date('d.m.Y', strtotime($date)) . " ";
        if (!empty($receiver)) {
            $report = WorkReport::getReceiverDailyReport($receiver->id, date('Y-m-d'));
            if ($commandType == self::WORK_START) {
                $report->start_hour = $hour;
                $report->start_minute = $minute;
                $appendText .= "Ishga kelgan vaqtingiz " . $hour . ":" . $minute;
            }
            if ($commandType == self::WORK_END) {
                $report->end_hour = $hour;
                $report->end_minute = $minute;
                $appendText .= "Ishdan ketgan vaqtingiz " . $hour . ":" . $minute;
            }
            $report->setTotal();
            Setting::saveParam(Setting::MAKE_SYSTEM_REPORT, 1);
            $report->save();
        }

        $tgService = new TelegramService;
        $tgService->editsendedMessage(
            $update->callback_query->message->message_id,
            $update->callback_query->message->chat->id,
            "Ma'lumotlaringiz saqlandi." . $appendText
        );

    }

    public function makeHourKeyboard($specialKey)
    {
        $hours = [];
        for ($i = 0; $i < 4; $i++) {
            $row = [];
            for ($n = 0; $n < 6; $n++) {
                $hour = $i * 6 + $n;
                $row[] = [
                    "text" => str_pad($hour, 2, "0", STR_PAD_LEFT),
                    "callback_data" => self::CALLBACK_HOUR . "_" . $specialKey . "_" . $hour
                ];
            }
            $hours[] = $row;
        }
        return $hours;
    }

    const CALLBACK_BACK_HOUR = 'back';

    public function makeMinuteKeyboard($specialKey)
    {
        $keyboards = [];
        for ($i = 0; $i < 4; $i++) {
            $row = [];
            for ($n = 0; $n < 3; $n++) {
                $minute = $i * 15 + $n * 5;
                $row[] = [
                    "text" => str_pad($minute, 2, "0", STR_PAD_LEFT),
                    "callback_data" => self::CALLBACK_MINUTE . "_" . $specialKey . "_" . $minute
                ];
            }
            $keyboards[] = $row;
        }
        //back button
        $keyboards[] = [
            [
                "text" => 'Orqaga',
                "callback_data" => self::CALLBACK_MINUTE . "_" . $specialKey . "_" . self::CALLBACK_BACK_HOUR
            ]
        ];

        return $keyboards;
    }

    public function isWorkTimeCommand($command)
    {
        return in_array($command, [TelegramService::COMMAND_COME_TIME, TelegramService::COMMAND_LEAVE_WORK]);
    }

    public function sendWorkTime($command, $chat_id)
    {
        $this->sendHour($chat_id, $command == TelegramService::COMMAND_COME_TIME ? TelegramManager::WORK_START : self::WORK_END);
    }

    public function isFileMessage($tUpdate){
        $filesProps = ['photo','document','video','audio','animation'];
        $isFile = false;
        foreach ($filesProps as $key => $prop) {
            if (property_exists($tUpdate->message, $prop)) {
                $isFile = true;
                break;
            }
        }
        return $isFile;
    }
    public function forwardMessage($tUpdate = null)
    {
        // $tUpdate = '{"update_id":319791824,"message":{"message_id":2799,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1686455277,"photo":[{"file_id":"AgACAgIAAxkBAAIK72SFQ-wJEdPZj9HsnH8AAXuZ8Oqa-QACm8wxG0dqKUgQqRxybRKa-wEAAwIAA3MAAy8E","file_unique_id":"AQADm8wxG0dqKUh4","file_size":914,"width":90,"height":51},{"file_id":"AgACAgIAAxkBAAIK72SFQ-wJEdPZj9HsnH8AAXuZ8Oqa-QACm8wxG0dqKUgQqRxybRKa-wEAAwIAA20AAy8E","file_unique_id":"AQADm8wxG0dqKUhy","file_size":11255,"width":320,"height":180},{"file_id":"AgACAgIAAxkBAAIK72SFQ-wJEdPZj9HsnH8AAXuZ8Oqa-QACm8wxG0dqKUgQqRxybRKa-wEAAwIAA3gAAy8E","file_unique_id":"AQADm8wxG0dqKUh9","file_size":50145,"width":800,"height":450},{"file_id":"AgACAgIAAxkBAAIK72SFQ-wJEdPZj9HsnH8AAXuZ8Oqa-QACm8wxG0dqKUgQqRxybRKa-wEAAwIAA3kAAy8E","file_unique_id":"AQADm8wxG0dqKUh-","file_size":97504,"width":1280,"height":720}]}}';
        // $tUpdate = json_decode($tUpdate);
        $tgService = new TelegramService();
        // $tgService->sendBotId = TelegramService::MANAGER_BOT_ID;
        $responce = $tgService->forwardMessage(TelegramService::MANAGER_GROUP_ID, $tUpdate->message->chat->id, $tUpdate->message->message_id);
        $url = 'https://t.me/c/'.substr(TelegramService::MANAGER_GROUP_ID,4).'/'.$responce->result->message_id;
        return $url;
    }

    public function getTgMessage($tgUpdate){
        $messageText = null;
        if (property_exists($tgUpdate->message, 'text')) {
            $messageText = $tgUpdate->message->text;
        }
        if (property_exists($tgUpdate->message, 'contact')) {
            $messageText = $tgUpdate->message->contact->phone_number;
        }

        if ($this->isFileMessage($tgUpdate)) {
            $messageText = $this->forwardMessage($tgUpdate);
        }
        return $messageText;
    }
    // public function makeCustomRespone($messagePlanId, $writer)
    // {
    //     if ($messagePlanId == 0) {
    //         return;
    //     }
    //     $customResponces = MessagePlan::where([
    //         'type' => MessagePlan::TYPE_CUSTOM_CALLBACK,
    //         'parent_id' => $messagePlanId,
    //         'parent_action_type' => MessagePlan::PARENT_ACTION_TYPE_RESPONCED
    //     ])->get();

    //     if (!empty($customResponces)) {
    //         $inMessage = IncomeMessage::where('writer_id', $writer->id)->latest()->first();
    //         $messagePlan = MessagePlan::where('id', $messagePlanId)->first();

    //         $tgservice = new TelegramService();
    //         $empKeyboards = $tgservice->getEmpKeyboard();

    //         if ($messagePlan->type == MessagePlan::TYPE_SYSTEM && $inMessage->message_plan_id == $messagePlan->id && $inMessage->sending_id == 0) {
    //             foreach ($customResponces as $customResponce) {
    //                 $tgservice->sendMessage($customResponce->template, $writer->chat_id, $empKeyboards);
    //             }
    //         }
    //     }
    // }
}