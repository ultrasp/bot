<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{

    public function send(){
        $service = new TelegramService();
        $service->sendMessage('test','2242981');
    }

    public function listener(Request $request){
        $post = $request->input();
        $service = new TelegramService();
        var_dump($post);
        // $tel->saveUpdate($post);

    }

    public function setCert(){
        $service = new TelegramService();
        $service->setCert();
    }

    
    //
}
