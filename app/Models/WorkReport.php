<?php

namespace App\Models;

use App\Services\GoogleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkReport extends Model
{
    // use SoftDeletes;
    protected $fillable = ['receiver_id','date'];
    
    const TYPE_WORK_HOUR = 1;
    const TYPE_WORK_BREAK = 1;
    public static function getReceiverDailyReport(string $receiver_id,string $date)
    {
        return self::firstOrNew(['receiver_id' => $receiver_id,'date' => $date]);
    }

    public function setTotal(){
        if(!empty($this->end_hour + $this->end_minute) && !empty($this->start_hour + $this->start_minute)){
            $this->total = ($this->end_hour * 60 + $this->end_minute) - ( $this->start_hour * 60 + $this->start_minute);
        }
    }

    public static function getMonthlyInfo($date)
    {
        return self::query()
            ->whereRaw('date >= "' . date('Y-m-01',strtotime($date)) . '" and date <= "'.date('Y-m-t',strtotime($date)).'"')
            ->get();
    }

}