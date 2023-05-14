<?php

namespace App\Http\Controllers;

use App\Models\MessageSending;
use App\Services\GoogleService;

class CronController extends Controller
{
    // public function run(){
    //     if( date( 'H') == 0 && date( 'i') == 0) {
    //         MessageSending::createSendings();
    //     }
    //     if(date( 'i') == 0){
    //         $service = new GoogleService();
    //         $service->readValues();
    //     }
    //     MessageSending::send();
    // }
}
