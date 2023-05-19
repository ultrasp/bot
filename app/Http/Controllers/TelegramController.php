<?php

namespace App\Http\Controllers;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\TelegramUpdate;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{

    public function send()
    {
        $service = new TelegramService();
        $service->sendMessage('test', '2242981');
        // $service->setMyCommands();
    }

    public function listener(Request $request)
    {
        $data = json_encode($request->all());
        TelegramUpdate::storeData($data);
        $this->handleMessage($data);

    }

    public function check(){
        // $update = TelegramUpdate::where(['id' => 5])->first();
        // $data = $update->update_json;
        $data = '{"update_id":319789625,"message":{"message_id":102,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684455575,"reply_to_message":{"message_id":101,"from":{"id":6128162329,"is_bot":true,"first_name":"us_helper_bot","username":"UsHelperBot"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684455538,"text":"Share you contact phone?"},"contact":{"phone_number":"+998903566022","first_name":"Umid","last_name":"Hamidov","user_id":2242981}}}';
        // dd($data);
        $this->handleMessage($data);
    }
    public function handleMessage($data){
        try {
            $message = json_decode($data);
            $service = new TelegramService();

            $canStoreMessage = false;
            $writer = null;
            $messagePlanId = 0;
            $sending = null;

            if ($message->message->chat->type == 'private' && !$message->message->from->is_bot) {
                $writer = Receiver::storeData($message->message->chat);
                $canStoreMessage = true;
                Receiver::writeToSheet();
            }

            if(property_exists($message->message,'text')){
                $messagePlanId = $service->getCommandPlanId($message->message->text);
                $command = $message->message->text;
                $isCommand = $messagePlanId > 0;
            }
            if (!empty($writer) && $messagePlanId == 0) {
                $sending = MessageSending::getLatestSendByWorkerId($writer->id);
                // dd($sending);
                if (!empty($sending) && empty($sending->answer_time)) {
                    $sending->answer_time = date('Y-m-d H:i:s');
                    $sending->save();
                    $messagePlanId = $sending->message_plan_id;
                    if ($sending->message_plan->template == "/".TelegramService::COMMAND_REGISTER) {
                        $isCommand = true;
                        $command = $sending->message_plan->template;
                        if ($sending->step == 1 && $message->message->text) {
                            $writer->fullname = $message->message->text;
                            $writer->save();
                        }
                        if ($sending->step == 2 && $message->message->contact) {
                            $writer->contact_phone = $message->message->contact->phone_number;
                            // $writer->user_type ==  Receiver::USER_TYPE_GUEST_FILLED;
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
                $service->callbackCommand($command, $messagePlanId, $writer);
            }
        } catch (\Throwable $th) {
            //throw $th;
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