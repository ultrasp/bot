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
        $removeTemplates = $allTemplates->filter(function ($dbTemplate) use ($notRemoveTemplates) {
            return !in_array($dbTemplate->id, $notRemoveTemplates);
        })->pluck('id')->toArray();
        if (!empty($removeTemplates)) {
            self::query()->whereIn('id', $removeTemplates)->delete();
        }
    }

    public static function getDailyInfo($date)
    {
        return MessagePlan::withTrashed()
            ->where(['type' => self::TYPE_ASK])
            ->whereRaw('(deleted_at is null or (deleted_at is not null and exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) = "' . $date . '")))')
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
            $dbommand = '/'.$command;
            $mplan = self::getSystemAsk($dbommand);
            if (empty($mplan)) {
                self::newItem($dbommand, 0, self::TYPE_SYSTEM);
            }
        }
    }

    public static function writeToExcelDaily()
    {
        $service = new GoogleService();
        $newSheetName = date('d.m.Y') . ' data';
        $service->checkExistSheet($newSheetName);

        $plans = MessagePlan::getDailyInfo(date('Y-m-d'));
        $data =
            [
                [
                    'time',
                    'ask',
                    'student',
                    'answer_time',
                    'answer',
                ]
            ];
        $sendings = MessageSending::getDailyInfo(date('Y-m-d'))->keyBy(function ($item) {
            return $item->message_plan_id . '_' . $item->receiver_id;
        });
        $receivers = Receiver::getEmployees();
        // dd($sendings);
        // $data = [];
        foreach ($plans as $plan) {
            // dd($plan->covertToString());
            // $data[$plan->id] = [
            // ];
            foreach ($receivers as $receiver) {
                $row = [
                    $plan->covertToString(),
                    $plan->template,
                ];
                $row[] = $receiver->lastname . ' ' . $receiver->firstname;
                $sending = $sendings->get($plan->id . '_' . $receiver->id);
                if (!empty($sending)) {
                    // dd($sending);
                    $row[] = $sending->answer_time ?? '';
                    if (!empty($sending->telegram_message_id)) {
                        $responces = IncomeMessage::where(['writer_id' => $receiver->id, 'sending_id' => $sending->id])->get();
                        // dd($responces);
                        foreach ($responces as $responce) {
                            $row[] = $responce->message;
                        }
                    }
                }
                $data[] = array_values($row);
            }
        }
        // dd($data);
        $service->deleteRows($newSheetName);
        $service->writeValues($newSheetName, array_values($data));
    }
}