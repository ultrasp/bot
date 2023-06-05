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
    const MESSAGE_SELECT_MINUTE = 'Ishga kelgan daqiqangizni tanlang';

    const WORK_START = 'workStart';
    const WORK_END = 'workEnd';

    public function sendHour($chat_id, $commandType,$messageId = null)
    {
        $date = $commandType . "_" . date('Y-m-d');
        $hourKeyboard = $this->makeHourKeyboard($date);

        $tgService = new TelegramService;
        if($messageId){
            $tgService->editsendedMessage($messageId, $chat_id, self::MESSAGE_SELECT_HOUR , $hourKeyboard);
        }else{
            $tgService->sendMessage(self::MESSAGE_SELECT_HOUR, $chat_id, $hourKeyboard, true);
        }
    }

    public function sendMinute($chatId, $messageId, $hour, $commandType)
    {
        $date = date('Y-m-d');
        $keyboard = $this->makeMinuteKeyboard($commandType . "_" . $date . "_" . $hour);
        $tgService = new TelegramService;
        $tgService->editsendedMessage($messageId, $chatId, self::MESSAGE_SELECT_MINUTE . ' (' . $date . ' ' . $hour . ') ', $keyboard);
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
        list($commandType,$date,$hour, $minute) = explode("_", $param);
        // dd($date);

        if($minute == self::CALLBACK_BACK_HOUR){
            $this->sendHour($update->callback_query->message->chat->id,$commandType);
            return;
        }
        if ($date != date('Y-m-d')) {
            //send date is passed
            return;
        }

        $receiver = Receiver::where(['chat_id' => $update->callback_query->from->id])->first();
        $appendText = date('d.m.Y',strtotime($date))." ";
        if (!empty($receiver)) {
            $report = WorkReport::getReceiverDailyReport($receiver->id, date('Y-m-d'));
            if($commandType == self::WORK_START){
                $report->start_hour = $hour;
                $report->start_minute = $minute;
                $appendText .= "Ishga kelgan vaqtingiz ".$hour.":".$minute;
            }
            if($commandType == self::WORK_END){
                $report->end_hour = $hour;
                $report->end_minute = $minute;
                $appendText .= "Ishdan ketgan vaqtingiz ".$hour.":".$minute;
            }
            $report->setTotal();
            Setting::saveParam(Setting::MAKE_SYSTEM_REPORT, 1);
            $report->save();
        }
        
        $tgService = new TelegramService;
        $tgService->editsendedMessage(
            $update->callback_query->message->message_id,
            $update->callback_query->message->chat->id,
            "Ma'lumotlaringiz saqlandi.".$appendText
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
                    "text" => str_pad($hour,2,"0",STR_PAD_LEFT) ,
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

    public function isWorkTimeCommand($command){
        return in_array($command,[TelegramService::COMMAND_COME_TIME,TelegramService::COMMAND_LEAVE_WORK]);
    }

    public function sendWorkTime($command,$chat_id){
        $this->sendHour($chat_id,$command == TelegramService::COMMAND_COME_TIME ?  TelegramManager::WORK_START : self::WORK_END);
    }
}