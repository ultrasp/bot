<?php

namespace App\Models;

use App\Services\GoogleService;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessagePlan extends Model
{
    use SoftDeletes;
    const TYPE_ASK = 1;
    const TYPE_SYSTEM = 2;
    const TYPE_BOT_ANSWER = 3;
    public static function newItem($text, $sendMinute, $type)
    {
        $item = new self();
        $item->template = $text;
        $item->send_minute = $sendMinute;
        $item->type = $type;
        $item->save();
    }

    public static function getAllByType($type)
    {
        return self::where(['type' => $type])->get();
    }

    public static function saveTemplates($templates)
    {
        $allTemplates = self::getAllByType(self::TYPE_ASK);
        $notRemoveTemplates = [];
        foreach ($templates as $template) {
            $savedDb = $allTemplates->first(function ($dbTemplate) use ($template) {
                return $dbTemplate->template == $template['message'] && $dbTemplate->send_minute == $template['time'];
            });
            if (!empty($savedDb)) {
                $notRemoveTemplates[] = $savedDb->id;
            } else {
                self::newItem($template['message'], $template['time'], self::TYPE_ASK);
            }
        }
        foreach ($allTemplates as $dbTemplate) {
            if (!in_array($dbTemplate->id, $notRemoveTemplates)) {
                $dbTemplate->delete();
                MessageSending::removeUnsendedSendings($dbTemplate->id);
            }
        }
        // $removeTemplates = $allTemplates->filter(function ($dbTemplate) use ($notRemoveTemplates) {
        //     return !in_array($dbTemplate->id, $notRemoveTemplates);
        // })->pluck('id')->toArray();
        // if (!empty($removeTemplates)) {
        //     self::query()->whereIn('id', $removeTemplates)->delete();
        // }
    }


    public static function updatePlans()
    {
        $gooleService = new GoogleService();
        $times = $gooleService->readSheetValues(GoogleService::SPREADSHEET_ID, GoogleService::readSheet);

        $setting = Setting::getItem(Setting::USER_LIST);

        $encodeData = md5(serialize($times));

        // dd($setting);
        if ($setting->param_value == $encodeData) {
            return 1;
        } else {
            $setting->param_value = $encodeData;
            $setting->save();
        }
        // dd($times);
        $templates = [];
        $startColumn = 1;
        foreach ($times as $key => $time) {
            if ($key == 0 || empty($time[$startColumn]))
                continue;
            $timeData = explode(':', $time[$startColumn]);
            $minuteFromMidnight = $timeData[0] * 60 + $timeData[1];

            $message = $time[$startColumn + 1];
            if (!empty($message)) {
                $templates[] = [
                    'message' => $message,
                    'time' => $minuteFromMidnight
                ];

            }
        }
        if (!empty($templates)) {
            MessagePlan::saveTemplates($templates);
        }
        return 0;
    }

    public static function getDailyInfo($date)
    {
        return MessagePlan::withTrashed()
            ->where(['type' => self::TYPE_ASK])
            ->whereRaw('(deleted_at is null or (deleted_at is not null and exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) = "' . $date . '")))')
            ->orderBy('send_minute')
            ->get();
    }

    public static function getMonthlyInfo($date)
    {
        return MessagePlan::withTrashed()
            ->where(['type' => self::TYPE_ASK])
            ->whereRaw('exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) >= "' . date('Y-m-01', strtotime($date)) . '" and DATE(send_time) <= "' . date('Y-m-t', strtotime($date)) . '" )')
            ->orderBy('send_minute')
            ->get();
    }

    public function covertToString()
    {
        $hour = intval($this->send_minute / 60);
        return $hour . ':' . ($this->send_minute - $hour * 60);
    }

    public static function getSystemAsk($command)
    {
        return self::where(['template' => $command, 'type' => self::TYPE_SYSTEM])->first();
    }

    public static function makeSystemAsk()
    {
        $commands = TelegramService::getAllCommands();
        foreach ($commands as $command) {
            $dbommand = '/' . $command;
            $mplan = self::getSystemAsk($dbommand);
            if (empty($mplan)) {
                self::newItem($dbommand, 0, self::TYPE_SYSTEM);
            }
        }
    }

    public static function writeToExcelDaily()
    {
        $service = new GoogleService();
        $selDate = date('Y-m-d');

        $newSheetName = date('d.m.Y', strtotime($selDate)) . ' data';
        $service->checkExistSheet($newSheetName);

        $plans = MessagePlan::getMonthlyInfo($selDate);
        $header = [''];
        $days = date('t',strtotime($selDate));

        for ($i = 0; $i < $days; $i++) {
            $day = date('d.m.Y', strtotime(date('Y-m-01', strtotime($selDate)) . ' +' . $i . 'days'));
            $header[] = $day;
        }

        $data =
            [
                $header
            ];

        $receivers = Receiver::getEmployees();

        $sendings = MessageSending::getMonthlyInfo($selDate)->groupBy(function ($item) {
            return date('Y-m-d', strtotime($item->send_plan_time)) . '_' . $item->message_plan_id . '_' . $item->receiver_id;
        });

        foreach ($receivers as $receiver) {
            $data[] = [$receiver->lastname . ' ' . $receiver->firstname];
            foreach ($plans as $plan) {
                $row = [
                    $plan->covertToString() . ' ' . $plan->template
                ];
                for ($i = 0; $i < $days; $i++) {
                    $day = date('d.m.Y', strtotime(date('Y-m-01', strtotime($selDate)) . ' +' . $i . 'days'));
                    $key = $day.'_'.$plan->id.'_'.$receiver->id;
                    $daySendings = $sendings->get($key);
                    $messageText = '';
                    if($daySendings){
                        foreach ($daySendings as $key => $sendingItem) {
                            foreach($sendingItem->incomes as $income){
                                $messageText .= $income->message;
                            }
                        }
                    }
                    $row[] = $messageText;
                }
        
                $data[] = $row;
            }
        }
        dd($data);
        // $sendings = MessageSending::getMonthlyInfo(date('Y-m-d'))->keyBy(function ($item) {
        //     return $item->message_plan_id . '_' . $item->receiver_id;
        // });
        // foreach ($plans as $plan) {
        //         $row = [
        //             $plan->covertToString(). $plan->template,
        //         ];
        //         $row[] = $receiver->lastname . ' ' . $receiver->firstname;
        //         $sending = $sendings->get($plan->id . '_' . $receiver->id);
        //         if (!empty($sending)) {
        //             // dd($sending);
        //             $row[] = $sending->answer_time ?? '';
        //             if (!empty($sending->telegram_message_id)) {
        //                 $responces = IncomeMessage::where(['writer_id' => $receiver->id, 'sending_id' => $sending->id])->get();
        //                 // dd($responces);
        //                 foreach ($responces as $responce) {
        //                     $row[] = $responce->message;
        //                 }
        //             }
        //         }
        //         $data[] = array_values($row);
        //     }
        // }
        // // dd($data);
        $service->deleteRows($newSheetName);
        $service->writeValues($newSheetName, array_values($data));
    }
}