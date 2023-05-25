<?php

namespace App\Http\Controllers;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
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

    public function check()
    {
        // $telegrams = TelegramUpdate::get();
        // foreach ($telegrams as $key => $telegram) {
        //     $data = json_decode($telegram->update_json);
        //     // if(!property_exists($data,'message')){
        //     //     dd($data);
        //     // }
        //     if(property_exists($data,'message') && property_exists($data->message,'text') && $data->message->text == '/register' && $data->message->from->username == 'Aliyev_VFX'){
        //         dd($telegram);
        //     }
        // }
        // dd($telegrams);
        $update = TelegramUpdate::where(['id' => 76])->first();
        $data = $update->update_json;
        // $data = '{"update_id":319789677,"message":{"message_id":154,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684568282,"text":"\/come_time","entities":[{"offset":0,"length":10,"type":"bot_command"}]}}';
        // $data = '{"update_id":319789691,"message":{"message_id":176,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684656933,"text":"Umidjon"}}';
        // dd($data);
        $this->handleMessage($data);
    }

    public function mlistener(Request $request)
    {
        $data = json_encode($request->all());
        // TelegramUpdate::storeData($data);
        $this->handleMessage($data);

    }

    public function handleManagerMessage($data)
    {
        try {
            // $data = '{"update_id":736985814,
            //     "message":{"message_id":2,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1685029690,"text":"/officeon","entities":[{"offset":0,"length":9,"type":"bot_command"}]}}';
            $message = json_decode($data);
            $service = new TelegramService();
            // dd($message);
            if (property_exists($message, 'message')) {
                $service->managerRequestHandle($message->message->text, $message->message->chat->id);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }


    }
    public function handleMessage($data)
    {
        try {
            $message = json_decode($data);
            $service = new TelegramService();
            // dd($data);
            if (!property_exists($message, 'message')) {
                return;
            }
            $canStoreMessage = false;
            $writer = null;
            $messagePlanId = 0;
            $sending = null;

            if ($message->message->chat->type == 'private' && !$message->message->from->is_bot) {
                $writer = Receiver::storeData($message->message->chat);
                $canStoreMessage = true;
                Setting::saveParam(Setting::MAKE_USER_LIST, 1);
                Setting::saveParam(Setting::SENDING_CREATE_TIME, null);
                // Receiver::writeToSheet();
            }

            if (property_exists($message->message, 'text')) { //income commands
                $messagePlanId = $service->getCommandPlanId($message->message->text);
                $command = $message->message->text;
                $isCommand = $messagePlanId > 0;
                Setting::saveParam(Setting::MAKE_SYSTEM_REPORT, 1);
            }
            // dd($message->message->text);
            if (!empty($writer) && $messagePlanId == 0) {
                $sending = MessageSending::getLatestSendByWorkerId($writer->id);
                // dd($sending);
                $command = $this->saveResponceCallback($sending, $writer, $message);
                $messagePlanId = $sending ? $sending->message_plan_id : null;
                $isCommand = !empty($command) ? true : false;
                if (!empty($sending) && empty($sending->answer_time)) {

                    $sending->answer_time = date('Y-m-d H:i:s');
                    $sending->save();


                }
            }


            if ($writer && $canStoreMessage) {
                IncomeMessage::storeData($message, $writer->id, $sending ? $sending->id : 0, $messagePlanId);
                Setting::saveParam(Setting::MAKE_REPORT, 1);
            }

            if (in_array(substr($message->message->text, 1), TelegramService::getManagerBotAsks()) && $message->message->chat->id == TelegramService::MANAGER_GROUP_ID) {
                $isCommand = true;
                $command = $message->message->text;
            }
            // dd($message->message->text);
            if ($isCommand) {
                $service->callbackCommand($command, $messagePlanId, $writer, $message->message->chat->id);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

    }

    public function saveResponceCallback($sending, $writer, $message)
    {
        $command = null;

        //handle register
        if ($sending && $sending->message_plan->template == "/" . TelegramService::COMMAND_REGISTER) {
            $command = $sending->message_plan->template;
            $isUpdated = false;
            if ($sending->step == 1 && property_exists($message->message, 'text')) {
                $writer->fullname = $message->message->text;
                $writer->save();
                $isUpdated = true;
            }
            $phone = '';
            if (property_exists($message->message, 'text')) {
                $phone = $message->message->text;
            }
            if (property_exists($message->message, 'contact')) {
                $phone = $message->message->contact->phone_number;
            }
            if ($sending->step == 2) {
                $writer->contact_phone = $phone;
                // $writer->user_type ==  Receiver::USER_TYPE_GUEST_FILLED;
                $writer->save();
                $isUpdated = true;
            }
            if ($isUpdated) {
                Setting::saveParam(Setting::MAKE_USER_LIST, 1);
            }

        }
        if ($message == "/" . TelegramService::COMMAND_REGISTER) {
            $command = $message;
        }
        return $command;
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