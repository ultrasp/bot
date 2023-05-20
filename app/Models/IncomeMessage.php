<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeMessage extends Model
{
    use SoftDeletes;
    public static function storeData($messageData, $writer_id, $sending_id, $messagePlanId, $type = 0)
    {
        $message = null;

        if(property_exists($messageData->message,'text')){
            $messageText = $messageData->message->text;
        }
        if(property_exists($messageData->message,'contact')){
            $messageText = $messageData->message->contact->phone_number;
        }
        if(empty($messageText)){
            return;
        }
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