<?php

namespace App\Http\Controllers;

use App\Models\MessageSending;
use App\Models\Receiver;

class MessageController extends Controller
{

    public function makeSendings(){
        MessageSending::createSendings();
    }

    public function sendQuestion(){
        MessageSending::send();
    }
}
