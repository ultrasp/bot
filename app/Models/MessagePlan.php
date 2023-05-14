<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessagePlan extends Model
{
    use  SoftDeletes;
    const TYPE_ASK = 1;
    public static function newItem($text,$sendMinute){
        $item = new self();
        $item->template = $text;
        $item->send_minute = $sendMinute;
        $item->type = self::TYPE_ASK;
        $item->save();
    }

    public static function getAllByType($type){
        return self::where(['type' => $type])->get();
    }

    public static function saveTemplates($templates){
        $allTemplates = self::getAllByType(self::TYPE_ASK);
        $notRemoveTemplates = [];
        foreach($templates as $template){
            $savedDb = $allTemplates->first(function ($dbTemplate) use($template){
                return $dbTemplate->template ==  $template['message'] && $dbTemplate->send_minute == $template['time'];
            });
            if(!empty($savedDb)){
                $notRemoveTemplates[] = $savedDb->id;
            }else{
                self::newItem($template['message'],$template['time']);
            }
        }
        $removeTemplates = $allTemplates->filter(function ($dbTemplate) use($notRemoveTemplates){
            return !in_array($dbTemplate->id,$notRemoveTemplates);
        })->pluck('id')->toArray();
        if(!empty($removeTemplates)){
            self::query()->whereIn('id', $removeTemplates)->delete();
        }
    }
}
