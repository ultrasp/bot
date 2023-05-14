<?php

namespace App\Http\Controllers;

use App\Models\IncomeMessage;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{

    public function send(){
        $service = new TelegramService();
        $service->sendMessage('test','2242981');
    }

    public function listener(Request $request){
        $data = $request->all();
        // var_dump($request->getContent());
        // $service = new TelegramService();
        // $data = '{"update_id":319789370,"message":{"message_id":14,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684038570,"text":"asdas"}}';
        $message = json_decode(json_encode($data));
        
        $canStoreMessage = false;
        $writer = null;
        //get information from private chat
        if($message->message->chat->type == 'private' && !$message->message->from->is_bot){
            $writer = Receiver::storeData($message->message->chat);
            $canStoreMessage = true;
        }
        if(!empty($writer)){
            $sending = MessageSending::getLatestSendByWorkerId($writer->id);
        }
        if($writer && $canStoreMessage){
            IncomeMessage::storeData($message,$writer->id,$sending ? $sending->id : 0);            
        }
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
