<?php

namespace App\Models;

use App\Services\GoogleService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessagePlan extends Model
{
    use SoftDeletes;
    const TYPE_ASK = 1;
    const TYPE_SYSTEM = 2;

    const TYPE_SYSTEM_CALLBACK = 4;
    const TYPE_BOT_ANSWER = 3;

    const CHASTOTA_DAILY = 1;
    const CHASTOTA_WORK_DAYS = 2;
    const CHASTOTA_RANGE_DAY = 3;

    const CHASTOTA_ONE_DAY = 4;
    public static function newItem($text, $sendMinute, $type, $chastota = 0)
    {
        $item = new self();
        $item->template = $text;
        $item->send_minute = $sendMinute;
        $item->type = $type;
        $item->chastota = $chastota;
        $item->save();
        return $item;
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
                return $dbTemplate->template == $template['message']
                    && $dbTemplate->send_minute == $template['time']
                    && $dbTemplate->chastota == $template['chastota'];
                // 'start_at' => $time[$startColumn + 3],
                // 'end_at' => $time[$startColumn + 4],

            });
            if (!empty($savedDb)) {
                $notRemoveTemplates[] = $savedDb->id;
            } else {
                try {
                    $item = self::newItem($template['message'], $template['time'], self::TYPE_ASK, $template['chastota']);
                    if ($item->chastota == self::CHASTOTA_RANGE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $end = Carbon::createFromFormat('d.m.Y', $template['end_at']);
                        $item->setRange($start->format('Y-m-d'), $end->format('Y-m-d'));
                    }
                    if ($item->chastota == self::CHASTOTA_ONE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $item->setRange($start->format('Y-m-d'), null);
                    }
                } catch (\Throwable $th) {

                }
            }
        }
        foreach ($allTemplates as $dbTemplate) {
            if (!in_array($dbTemplate->id, $notRemoveTemplates)) {
                $dbTemplate->delete();
                MessageSending::removeUnsendedSendings($dbTemplate->id);
            }
        }
    }

    public function setRange($startAt, $endAt)
    {
        $this->start_at = $startAt;
        $this->end_at = $endAt;
        $this->save();
    }

    public static function updatePlans()
    {
        $gooleService = new GoogleService();
        $times = $gooleService->readSheetValues(GoogleService::SPREADSHEET_ID, GoogleService::readSheet);

        $setting = Setting::getItem(Setting::MESSAGE_LIST);

        $encodeData = md5(serialize($times));

        // dd($setting);
        if ($setting->param_value == $encodeData) {
            return 0;
        } else {
            $setting->param_value = $encodeData;
            $setting->save();
        }
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

            if ($chastota == self::CHASTOTA_RANGE_DAY && (empty($time[$startColumn + 3]) || empty($time[$startColumn + 4]))) {
                $hasSetDate = false;

            }
            if ($chastota == self::CHASTOTA_ONE_DAY && empty($time[$startColumn + 3])) {
                $hasSetDate = false;
            }
            if (!empty($message) && !empty($time[$startColumn + 2]) && $hasSetDate) {
                $templates[] = [
                    'message' => $message,
                    'time' => $minuteFromMidnight,
                    'chastota' => $time[$startColumn + 2],
                    'start_at' => $time[$startColumn + 3] ?? null,
                    'end_at' => $time[$startColumn + 4] ?? null,
                ];

            }
        }
        // if (!empty($templates)) {
        MessagePlan::saveTemplates($templates);
        // }
        return 1;
    }

    public static function getDailyInfo($date)
    {
        return MessagePlan::withTrashed()
            ->where(['type' => self::TYPE_ASK])
            ->whereRaw('(deleted_at is null or (deleted_at is not null and exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) = "' . $date . '")))')
            ->orderBy('send_minute')
            ->get();
    }

    public static function getMonthlyInfo($date, $type = self::TYPE_ASK)
    {
        if (empty($type)) {
            $type = self::TYPE_ASK;
        }
        return MessagePlan::withTrashed()
            ->where(['type' => $type])
            ->whereRaw('(deleted_at is null or exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) >= "' . date('Y-m-01', strtotime($date)) . '" and DATE(send_time) <= "' . date('Y-m-t', strtotime($date)) . '" ))')
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
        return self::where(['template' => $command])->whereIn('type', [self::TYPE_SYSTEM, self::TYPE_SYSTEM_CALLBACK])->first();
    }

    public static function makeSystemAsk()
    {
        $commands = TelegramService::getAllCommands();
        foreach ($commands as $command) {
            $dbommand = '/' . $command;
            $mplan = self::getSystemAsk($dbommand);
            if (empty($mplan)) {
                self::newItem($dbommand, 0, $command == TelegramService::COMMAND_REGISTER ? self::TYPE_SYSTEM_CALLBACK : self::TYPE_SYSTEM);
            }
        }
    }

    public static function writeToExcelDaily($isBotCommand = false)
    {
        $service = new GoogleService();
        $selDate = date('Y-m-d');

        $newSheetName = date('m.Y', strtotime($selDate)) . ' ' . ($isBotCommand ? '0' : '1');
        $service->checkExistSheet($newSheetName);

        $plans = MessagePlan::getMonthlyInfo($selDate, $isBotCommand ? self::TYPE_SYSTEM : self::TYPE_ASK);
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
        // dd($sendings);
        foreach ($receivers as $receiver) {
            $data[] = [$receiver->lastname . ' ' . $receiver->firstname];
            foreach ($plans as $plan) {
                $row = [
                    ($isBotCommand ? '' : $plan->covertToString()) . ' ' . $plan->template
                ];
                for ($i = 0; $i < $days; $i++) {
                    $day = date('Y-m-d', strtotime(date('Y-m-01', strtotime($selDate)) . ' +' . $i . 'days'));
                    $key = $day . '_' . $plan->id . '_' . $receiver->id;
                    $daySendings = $sendings->get($key);
                    $messageText = '';
                    if ($daySendings) {
                        foreach ($daySendings as $key => $sendingItem) {
                            foreach ($sendingItem->incomes as $income) {
                                // dd($income);
                                $messageText .= $income->message . "\r ";
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

    public function canSend()
    {
        $canSend = false;
        if ($this->chastota == MessagePlan::CHASTOTA_DAILY) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_WORK_DAYS && date('D') != 'Sun') {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_RANGE_DAY && $this->start_at >= date('Y-m-d') && $this->end_at <= date('Y-m-d')) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_ONE_DAY && $this->start_at == date('Y-m-d')) {
            $canSend = true;
        }
        return $canSend;
    }
}