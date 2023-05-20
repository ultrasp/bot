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

    public function message_plan()
    {
        return $this->belongsTo(MessagePlan::class);
    }

    public static function createSendings()
    {
        $receivers = Receiver::getEmployees();
        $templates = MessagePlan::getAllByType(MessagePlan::TYPE_ASK);
        if (!empty($receivers) && !empty($templates)) {
            foreach ($receivers as $receiver) {
                foreach ($templates as $template) {
                    $send_time = date('Y-m-d H:i:s', strtotime('midnight +' . $template->send_minute . ' minutes'));
                    $item = self::newItem($receiver->id, $template->id, $send_time, $template->template);
                }
            }
        }
    }

    public static function newItem($receiver_id, $message_plan_id, $send_time, $message, $withSave = true)
    {
        $item = new self();
        $item->receiver_id = $receiver_id;
        $item->message_plan_id = $message_plan_id;
        $item->send_plan_time = $send_time;
        $item->message = $message;
        if ($withSave)
            $item->save();
        return $item;
    }

    public static function getLatestSendByWorkerId($workerId)
    {
        return self::whereNotNull('send_time')
            ->where('receiver_id', $workerId)
            ->where('telegram_message_id', '!=', '0')
            ->orderBy('send_time', 'desc')
            ->first();
    }
    public static function send()
    {
        // $date = date('Y-m-d H:i:00');
        // echo $date;
        $sendings = self::where([
            'send_time' => null,
            'is_fake' => 0
        ])
            // ->where('send_plan_time', $date)
            // ->whereRaw('send_plan_time between "'.date('Y-m-d H:i:s',strtotime('+5 minutes')).'" and "'.date('Y-m-d H:i:s').'"')
            ->whereRaw('send_plan_time <=  "' . date('Y-m-d H:i:s') . '"')
            ->get();
        var_dump($sendings);
        $service = new TelegramService();
        foreach ($sendings as $sending) {
            if (!empty($sending->receiver)) {
                $status = $service->sendMessage($sending->message, $sending->receiver->chat_id);

                // $writer = Receiver::getBot($sending->receiver->id,);
                // $item = self::newItem($receiver->id,$template->id,$send_time,$template->template);
                // IncomeMessage::storeData($sending->message, $writer->id, $sending ? $sending->id : 0, $sending->message_plan_id,1);
                $sending->saveSendTime($status->ok ? $status->result->message_id : -1);
            }
        }
    }

    public function saveSendTime($api_message_id)
    {
        $this->send_time = date('Y-m-d H:i:s');
        $this->telegram_message_id = $api_message_id;
        $this->save();
    }

    public static function getDailyInfo($date)
    {
        return MessageSending::query()
            ->whereRaw('DATE(send_plan_time) = "' . $date . '"')
            ->get();
    }

}