<?php

namespace App\Http\Controllers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;

class MessageController extends Controller
{

    public function makeSendings(){
        $isChanged = MessagePlan::updatePlans();

        $sendingTime = Setting::getItem(Setting::SENDING_CREATE_TIME);
        $isSendingsUpdated = false;
        if ((!empty($sendingTime->param_value) && date('Y-m-d', strtotime($sendingTime->param_value))) != date('Y-m-d') || $isChanged) {
            MessageSending::createSendings();
            $isSendingsUpdated = true;
        }
        if ($isSendingsUpdated) {
            $sendingTime->setVal(date('Y-m-d H:i:s'));
        }
        MessageSending::send();
    }

    public function sendQuestion(){
        MessageSending::send();
    }

    public function makeInit(){
        MessagePlan::makeSystemAsk();
        // Receiver::storeBots();
    }
}
