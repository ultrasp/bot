<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUpdate extends Model
{
    protected $table = 't_updates';
    public static function storeData($data)
    {
        $message = new self();
        $message->update_json = $data;
        $message->save();
        return $message;
    }
}