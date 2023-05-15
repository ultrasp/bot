<?php

namespace App\Models;

use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Model;

class MessageSending extends Model
{
    public function receiver()
    {
        return $this->belongsTo(Receiver::class);
    }

    public static function createSendings()
    {
        $receivers = Receiver::getEmployees();
        $templates = MessagePlan::getAllByType(MessagePlan::TYPE_ASK);
        if (!empty($receivers) && !empty($templates)) {
            foreach ($receivers as $receiver) {
                foreach ($templates as $template) {
                    $item = new self();
                    $item->receiver_id = $receiver->id;
                    $item->message_plan_id = $template->id;
                    $item->send_plan_time = date('Y-m-d H:i:s', strtotime('midnight +' . $template->send_minute . ' minutes'));
                    $item->message = $template->template;
                    $item->save();
                }
            }
        }
    }

    public static function getLatestSendByWorkerId($workerId)
    {
        return self::whereNotNull('send_time')
            ->where('receiver_id', $workerId)
            ->where('telegram_message_id', '>', '0')
            ->orderBy('send_time', 'desc')
            ->first();
    }
    public static function send()
    {
        // $date = date('Y-m-d H:i:00');
        // echo $date;
        $sendings = self::where([
            'send_time' => null,
        ])
            // ->where('send_plan_time', $date)
            // ->whereRaw('send_plan_time between "'.date('Y-m-d H:i:s',strtotime('+5 minutes')).'" and "'.date('Y-m-d H:i:s').'"')
            ->whereRaw('send_plan_time <=  "'.date('Y-m-d H:i:s').'"')
            ->get();
        var_dump($sendings);
        $service = new TelegramService();
        foreach ($sendings as $sending) {
            if (!empty($sending->receiver)) {
                $sending->send_time = date('Y-m-d H:i:s');
                $sending->save();
                $status = $service->sendMessage($sending->message, $sending->receiver->chat_id);
                $sending->telegram_message_id = $status->ok ? $status->result->message_id : -1;
                $sending->save();
            }
        }
    }

    public static function getDailyInfo($date)
    {
        return MessageSending::query()
            ->whereRaw('DATE(send_plan_time) = "'.$date.'"')
            ->get();
    }

}