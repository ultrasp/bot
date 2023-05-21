<?php

namespace App\Models;

use App\Services\GoogleService;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    const MESSAGE_LIST = 'message_list';
    const SENDING_CREATE_TIME = 'sending_create_time';
    const MAKE_REPORT = 'make_report';
    const USER_LIST = 'user_list';
    const MAKE_USER_LIST = 'make_user_list';
    public static function newItem($paramKey)
    {
        $item = new self();
        $item->param_key = $paramKey;
        return $item;
    }

    public static function getItem($paramKey){
        $param = self::where(['param_key' => $paramKey])->first();
        return !empty($param) ? $param : self::newItem($paramKey);
    }

    public static function saveParam($paramKey, $paramValue){
        $param = self::getItem($paramKey);
        if(empty($param)){
            $param = self::newItem($paramKey);
        }
        $param->param_value = $paramValue;
        $param->save();
        
    }

    public function setVal($value){
        $this->param_value = $value;
        $this->save();
    }
}