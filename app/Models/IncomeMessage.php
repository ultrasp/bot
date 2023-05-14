<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeMessage extends Model
{
    use  SoftDeletes;
    public static function storeData($messageData,$writer_id,$sending_id = 0){
        $message = new self();
        $message->writer_id = $writer_id;
        $message->message = $messageData->message->text;
        $message->chat_id = $messageData->message->chat->id;
        $message->update_id = $messageData->update_id;
        $message->sending_id = $sending_id;
        $message->save();
        return $message;
    }
}
