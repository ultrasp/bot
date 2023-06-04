<?php

namespace App\Http\Controllers;

use App\Managers\MessagePlanManager;
use App\Managers\MessageSendingManager;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;

class MessageController extends Controller
{

    public function makeSendings(){
        $isChanged = MessagePlanManager::updatePlans();
        MessageSendingManager::updateAndSend($isChanged);
    }

    public function sendQuestion(){
        MessageSending::send();
    }

    public function makeInit(){
        MessagePlan::makeSystemAsk();
        // Receiver::storeBots();
    }
}
