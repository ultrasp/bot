<?php

namespace App\Http\Controllers;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
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
        $message = json_decode(json_encode($data));
        
        $canStoreMessage = false;
        $writer = null;
        //get information from private chat
        if($message->message->chat->type == 'private' && !$message->message->from->is_bot){
            $writer = Receiver::storeData($message->message->chat);
            $canStoreMessage = true;
            Receiver::writeToSheet();
        }
        if(!empty($writer)){
            $sending = MessageSending::getLatestSendByWorkerId($writer->id);
            if(!empty($sending) && empty($sending->answer_time)){
                $sending->answer_time = date('Y-m-d H:i:s');
                $sending->save();
            }
        }
        if($writer && $canStoreMessage){
            IncomeMessage::storeData($message,$writer->id,$sending ? $sending->id : 0);            
            MessagePlan::writeToExcelDaily();
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
