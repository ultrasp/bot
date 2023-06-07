<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeMessage extends Model
{
    use SoftDeletes;
    public function receiver()
    {
        return $this->belongsTo(Receiver::class, 'writer_id');
    }

    public static function storeData($messageText,$messageData, $writer_id, $sending_id, $messagePlanId, $type = 0)
    {
        $message = new self();
        $message->writer_id = $writer_id;
        $message->message = $messageText;
        $message->chat_id = $messageData->message->chat->id;
        $message->update_id = $messageData->update_id;
        $message->sending_id = $sending_id;
        $message->message_plan_id = $messagePlanId;
        $message->type = $type;
        $message->save();
        return $message;
    }

    public static function getLatestIncomeByWorkerId($workerId)
    {
        return self::query()
            ->where('writer_id', $workerId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

}