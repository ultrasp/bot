<?php

namespace App\Http\Controllers;

use App\Managers\ReceiverManager;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Services\GoogleService;

class CronController extends Controller
{
    public function run(){
        ReceiverManager::updateReceiverData();
    }
}
