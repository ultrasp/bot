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

    public function send()
    {
        $service = new TelegramService();
        $service->sendMessage('test','2242981');
        // $service->setMyCommands();
    }

    public function listener(Request $request)
    {
        $data = $request->all();
        file_put_contents("meesages",json_encode($data),FILE_APPEND);
        // $data = json_decode('{"update_id":319789554,"message":{"message_id":56,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684279467,"text":"\/register","entities":[{"offset":0,"length":9,"type":"bot_command"}]}}');
        // dd($data);
        // var_dump($request->getContent());
        $message = json_decode(json_encode($data));
        // dd($message);
        $service = new TelegramService();

        $canStoreMessage = false;
        $writer = null;
        //get information from private chat
        if ($message->message->chat->type == 'private' && !$message->message->from->is_bot) {
            $writer = Receiver::storeData($message->message->chat);
            $canStoreMessage = true;
            Receiver::writeToSheet();
        }
        $messagePlanId = $service->getCommandPlanId($message->message->text);
        $command = $message->message->text;
        $isCommand = $messagePlanId > 0;

        $sending = null;
        if (!empty($writer) && $messagePlanId == 0) {
            $sending = MessageSending::getLatestSendByWorkerId($writer->id);
            if (!empty($sending) && empty($sending->answer_time)) {
                $sending->answer_time = date('Y-m-d H:i:s');
                $sending->save();
                $messagePlanId = $sending->message_plan_id;
                if($sending->message_plan->template == TelegramService::COMMAND_REGISTER){
                    $isCommand = true;
                    $command == TelegramService::COMMAND_REGISTER;
                    if($sending->step == 1){
                        $writer->fullname = $message->message->text;
                        $writer->save();
                    }
                    if($sending->step == 2){
                        $writer->contact_phone = $message->message->text;
                        $writer->save();
                    }
                }
            }
        }

        if ($writer && $canStoreMessage) {
            IncomeMessage::storeData($message, $writer->id, $sending ? $sending->id : 0, $messagePlanId);
            MessagePlan::writeToExcelDaily();
        }

        if ($isCommand) {
            $service->callbackCommand($command, $messagePlanId,$writer);
        }
    }

    public function setCert()
    {
        $service = new TelegramService();
        $service->setCert();
    }

    public function getIncomes()
    {
        $str = file_get_contents('income.json');
        echo $str;
    }

    //
}