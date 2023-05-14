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
        $post = $request->all();
        var_dump($request->getContent());
        $service = new TelegramService();
        var_dump($post);
        file_put_contents('income.json', json_encode($post));
        // $tel->saveUpdate($post);

    }

    public function setCert(){
        $service = new TelegramService();
        $service->setCert();
    }

    public function getIncomes(){
        $str = file_get_contents('income.json');
        echo $str;
    }
    
    //
}
