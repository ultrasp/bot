<?php

namespace App\Http\Controllers;

use App\Managers\MessagePlanManager;
use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Services\GoogleService;

//https://www.nidup.io/blog/manipulate-google-sheets-in-php-with-api
class GoogleController extends Controller
{

    public function get()
    {
        $isChanged = MessagePlanManager::updatePlans();

        // $sendingTime = Setting::getItem(Setting::SENDING_CREATE_TIME);
        // $isSendingsUpdated = false;
        // if ((!empty($sendingTime->param_value) && date('Y-m-d', strtotime($sendingTime->param_value))) != date('Y-m-d') || $isChanged) {
        //     MessageSending::createSendings();
        //     $isSendingsUpdated = true;
        // }
        // if ($isSendingsUpdated) {
        //     $sendingTime->setVal(date('Y-m-d H:i:s'));
        // }
        // MessageSending::send();

    }

    public function addDailyData()
    {
        MessagePlanManager::writeToExcelDaily(true);
        // dd($plans);
    }


}