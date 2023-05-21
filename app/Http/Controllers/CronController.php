<?php

namespace App\Http\Controllers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Services\GoogleService;

class CronController extends Controller
{
    public function run(){
        $isChanged = Receiver::readSheet();
        $setting = Setting::getItem(Setting::MAKE_USER_LIST);
        if(($setting->param_value == null && $setting->param_value == 1) || $isChanged){
            Receiver::writeToSheet();
            $setting->setVal(0);
        }
    }
}
