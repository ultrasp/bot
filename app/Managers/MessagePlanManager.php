<?php

namespace App\Managers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\GoogleService;
use App\Services\TelegramService;
use Carbon\Carbon;

class MessagePlanManager
{
    const START_COLUMN = 2;

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
        $startColumn = self::START_COLUMN;
        // dd($times);
        foreach ($times as $key => $time) {
            if ($key == 0 || !isset($time[$startColumn]) || $time[$startColumn] == '')
                continue;

            $timeData = explode(':', $time[$startColumn]);
            $minuteFromMidnight = 0;

            if (isset($timeData[1])) {
                $minuteFromMidnight = $timeData[0] * 60 + $timeData[1];
            }

            $message = $time[$startColumn + 1];
            $chastota = $time[$startColumn + 2];
            $hasSetDate = true;

            if ($chastota == MessagePlan::CHASTOTA_RANGE_DAY && (empty($time[$startColumn + 3]) || empty($time[$startColumn + 4]))) {
                $hasSetDate = false;

            }
            if ($chastota == MessagePlan::CHASTOTA_ONE_DAY && empty($time[$startColumn + 3])) {
                $hasSetDate = false;
            }
            if (!empty($message) && (!empty($time[$startColumn + 2]) || !empty($time[$startColumn - 2])) && $hasSetDate) {
                $templates[] = [
                    'message' => $message,
                    'time' => $minuteFromMidnight,
                    'chastota' => $time[$startColumn + 2],
                    'start_at' => $time[$startColumn + 3] ?? null,
                    'end_at' => $time[$startColumn + 4] ?? null,
                    'groups' => $time[$startColumn + 5] ?? null,
                    'parent_command_text' => $time[$startColumn - 2] ?? null,
                    'parent_command_action' => $time[$startColumn - 1] ?? 0
                ];
            }
        }
        // dd($templates);
        // if (!empty($templates)) {
        MessagePlanManager::saveTemplates($templates);
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

    public static function saveTemplates($templates)
    {
        self::saveAskTemplates($templates);
        self::saveCallbackTemplates($templates);
    }

    public static function saveAskTemplates($templates)
    {
        $allTemplates = MessagePlan::getAllByType(MessagePlan::TYPE_ASK);
        $notRemoveTemplates = [];

        foreach ($templates as $template) {
            if (!empty($template['parent_command_text'])) {
                continue;
            }
            $savedDb = $allTemplates->first(function ($dbTemplate) use ($template) {
                return $dbTemplate->template == $template['message']
                    && $dbTemplate->send_minute == $template['time']
                    && $dbTemplate->chastota == $template['chastota'];
            });

            if (!empty($savedDb)) {
                $notRemoveTemplates[] = $savedDb->id;
            } else {
                try {
                    $savedDb = MessagePlan::newItem($template['message'], $template['time'], MessagePlan::TYPE_ASK, $template['chastota']);
                    if ($savedDb->chastota == MessagePlan::CHASTOTA_RANGE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $end = Carbon::createFromFormat('d.m.Y', $template['end_at']);
                        $savedDb->setRange($start->format('Y-m-d'), $end->format('Y-m-d'));
                    }
                    if ($savedDb->chastota == MessagePlan::CHASTOTA_ONE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $savedDb->setRange($start->format('Y-m-d'), null);
                    }
                } catch (\Throwable $th) {

                }
            }
            if (!empty($savedDb)) {
                $savedDb->attachReceviers($template['groups']);
            }
        }

        foreach ($allTemplates as $dbTemplate) {
            if (!in_array($dbTemplate->id, $notRemoveTemplates)) {
                $dbTemplate->delete();
                MessageSending::removeUnsendedSendings($dbTemplate->id);
            }
        }

    }


    public static function saveCallbackTemplates($templates)
    {


        $commandTemplates = MessagePlan::getAllByType(MessagePlan::TYPE_SYSTEM);
        $commandCustomCallbacks = MessagePlan::getAllByType(MessagePlan::TYPE_CUSTOM_CALLBACK);
        $notRemoveCallbacks = [];
        // dd($templates);
        foreach ($templates as $template) {
            if (empty($template['parent_command_text'])) {
                continue;
            }

            // dd($template);
            $comMessage = $commandTemplates->first(function ($commandTemplate) use ($template) {
                return $commandTemplate->template == $template['parent_command_text'];
            });

            if (empty($comMessage)) {
                continue;
            }
            $savedDb = null;
            $savedDb = $commandCustomCallbacks->first(function ($dbTemplate) use ($template, $comMessage) {
                return ($dbTemplate->parent_action_type == $template['parent_command_action'] ? MessagePlan::PARENT_ACTION_TYPE_RESPONCED : MessagePlan::PARENT_ACTION_TYPE_NOT_ANSWER)
                    && $dbTemplate->parent_id == $comMessage->id
                    && $dbTemplate->send_minute == $template['time']
                    && $dbTemplate->send_groups == $template['groups']
                ;
            });

            if (!empty($savedDb)) {
                $notRemoveCallbacks[] = $savedDb->id;
                continue;
            }

            try {
                $savedDb = MessagePlan::newItem($template['message'], $template['time'], MessagePlan::TYPE_CUSTOM_CALLBACK, $template['chastota']);
                $savedDb->parent_id = $comMessage->id;
                $savedDb->parent_action_type = $template['parent_command_text'] ? MessagePlan::PARENT_ACTION_TYPE_RESPONCED : MessagePlan::PARENT_ACTION_TYPE_NOT_ANSWER;
                $savedDb->save();
            } catch (\Throwable $th) {
                echo $th->getMessage();
            }

            if (!empty($savedDb)) {
                $savedDb->attachReceviers($template['groups']);
            }
        }

        // dd($notRemoveCallbacks);
        foreach ($commandCustomCallbacks as $dbTemplate) {
            if (!in_array($dbTemplate->id, $notRemoveCallbacks)) {
                $dbTemplate->delete();
                MessageSending::removeUnsendedSendings($dbTemplate->id);
            }
        }


    }

}