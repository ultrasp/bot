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

    public function incomes()
    {
        return $this->hasMany(IncomeMessage::class,'sending_id');
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
            ->orderByRaw('send_time desc, id desc')
            ->first();
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

    public static function getMonthlyInfo($date,$isBotCommand = false)
    {
        return MessageSending::query()
            ->with('incomes')
            ->whereRaw('DATE(send_plan_time) >= "' . date('Y-m-01',strtotime($date)) . '" and date(send_plan_time) <= "'.date('Y-m-t',strtotime($date)).'"')
            ->whereRaw('send_time is not null')
            ->where(['is_fake' => $isBotCommand ? 1 : 0 ])
            ->get();
    }

    public static function removeUnsendedSendings($message_plan_id){
        MessageSending::where([
            'message_plan_id' => $message_plan_id,
            'send_time' => null,
            'is_fake' => 0
        ])->delete();
    }

}