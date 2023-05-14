<?php

namespace App\Http\Controllers;

use App\Models\MessageSending;

class CronController extends Controller
{
    public function run(){
        if( date( 'H') == 0 && date( 'i') == 0) {
            MessageSending::createSendings();
        }
        MessageSending::send();
    }
}
