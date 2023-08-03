<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BotSending extends Model
{
    use SoftDeletes;
    public function reader()
    {
        return $this->belongsTo(Receiver::class, 'reader_id');
    }

    public static function storeData($reader_id, $messageText, $messagePlanId = null)
    {
        $message = new self();
        $message->reader_id = $reader_id;
        $message->message = $messageText;
        $message->message_plan_id = $messagePlanId;
        $message->save();
        return $message;
    }

    public function storeUpdate($updateJson)
    {
        $this->update_json = $updateJson;
        $this->save();
    }

}