<?php

namespace App\Managers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\GoogleService;
use App\Services\TelegramService;

class MessagePlanManager
{

    public static function updatePlans()
    {
        $gooleService = new GoogleService();
        $times = $gooleService->readSheetValues(GoogleService::SPREADSHEET_ID, GoogleService::readSheet);

        $setting = Setting::getItem(Setting::MESSAGE_LIST);

        $encodeData = md5(serialize($times));

        // dd($setting);
        // if ($setting->param_value == $encodeData) {
        //     return 0;
        // } else {
        //     $setting->param_value = $encodeData;
        //     $setting->save();
        // }
        // dd($times);
        $templates = [];
        $startColumn = 1;
        // dd($times);
        foreach ($times as $key => $time) {
            if ($key == 0 || empty($time[$startColumn]))
                continue;
            $timeData = explode(':', $time[$startColumn]);
            $minuteFromMidnight = $timeData[0] * 60 + $timeData[1];

            $message = $time[$startColumn + 1];
            $chastota = $time[$startColumn + 2];
            $hasSetDate = true;

            if ($chastota == MessagePlan::CHASTOTA_RANGE_DAY && (empty($time[$startColumn + 3]) || empty($time[$startColumn + 4]))) {
                $hasSetDate = false;

            }
            if ($chastota == MessagePlan::CHASTOTA_ONE_DAY && empty($time[$startColumn + 3])) {
                $hasSetDate = false;
            }
            if (!empty($message) && !empty($time[$startColumn + 2]) && $hasSetDate) {
                $templates[] = [
                    'message' => $message,
                    'time' => $minuteFromMidnight,
                    'chastota' => $time[$startColumn + 2],
                    'start_at' => $time[$startColumn + 3] ?? null,
                    'end_at' => $time[$startColumn + 4] ?? null,
                    'groups' => $time[$startColumn + 5] ?? null,
                ];

            }
        }
        // if (!empty($templates)) {
        MessagePlan::saveTemplates($templates);
        // }
        return 1;
    }

    public static function writeToExcelDaily($isBotCommand = false)
    {
        $service = new GoogleService();
        $selDate = date('Y-m-d');

        $newSheetName = date('m.Y', strtotime($selDate)) . ' ' . ($isBotCommand ? '0' : '1');
        $service->checkExistSheet($newSheetName);

        $plans = MessagePlan::getMonthlyInfo($selDate, $isBotCommand ? MessagePlan::TYPE_SYSTEM : MessagePlan::TYPE_ASK);
        $header = [''];
        $days = date('t', strtotime($selDate));

        for ($i = 0; $i < $days; $i++) {
            $day = date('d.m.Y', strtotime(date('Y-m-01', strtotime($selDate)) . ' +' . $i . 'days'));
            $header[] = $day;
        }

        $data =
            [
                $header
            ];

        $receivers = Receiver::getEmployees();

        $sendings = MessageSending::getMonthlyInfo($selDate, $isBotCommand)->groupBy(function ($item) {
            return date('Y-m-d', strtotime($item->send_plan_time)) . '_' . $item->message_plan_id . '_' . $item->receiver_id;
        });

        if ($isBotCommand) {
            $workReports = WorkReport::getMonthlyInfo($selDate)->keyBy(function ($item) {
                return $item->date . '_' . $item->receiver_id;
            });
        }
        foreach ($receivers as $receiver) {
            $data[] = [$receiver->lastname . ' ' . $receiver->firstname];
            foreach ($plans as $plan) {
                $row = [
                    ($isBotCommand ? '' : $plan->covertToString()) . ' ' . $plan->template
                ];
                for ($i = 0; $i < $days; $i++) {
                    $day = date('Y-m-d', strtotime(date('Y-m-01', strtotime($selDate)) . ' +' . $i . 'days'));
                    $messageText = '';
                    $command = substr($plan->template, 1);
                    if ($isBotCommand && in_array($command, [TelegramService::COMMAND_COME_TIME, TelegramService::COMMAND_LEAVE_WORK, TelegramService::COMMAND_TOTAL_WORK_TIME])) {
                        $key = $day . '_' . $receiver->id;
                        $dayWorkReport = $workReports->get($key);
                        if ($dayWorkReport) {
                            if ($command == TelegramService::COMMAND_COME_TIME) {
                                $messageText = $dayWorkReport->start_hour . ':' . $dayWorkReport->start_minute;
                            }
                            if ($command == TelegramService::COMMAND_LEAVE_WORK) {
                                $messageText = $dayWorkReport->end_hour . ':' . $dayWorkReport->end_minute;
                            }
                            if ($command == TelegramService::COMMAND_TOTAL_WORK_TIME) {
                                $messageText = $dayWorkReport->total;
                            }
                        }
                    } else {
                        $key = $day . '_' . $plan->id . '_' . $receiver->id;
                        $daySendings = $sendings->get($key);
                        if ($daySendings) {
                            foreach ($daySendings as $key => $sendingItem) {
                                foreach ($sendingItem->incomes as $income) {
                                    // dd($income);
                                    $messageText .= $income->message . "\r ";
                                }
                            }
                        }
                    }
                    $row[] = $messageText;
                }
                $data[] = $row;
            }
        }
        $service->deleteRows($newSheetName);
        $service->writeValues($newSheetName, array_values($data));
    }


}