<?php

namespace App\Http\Controllers;

use App\Managers\MessageSendingManager;
use App\Managers\TelegramManager;
use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\TelegramUpdate;
use App\Services\GoogleService;
use App\Services\TelegramService;
use App\Utils\BotCommandUtil;
use Illuminate\Http\Request;

class TelegramController extends Controller
{

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
        $update = TelegramUpdate::where(['id' => 5379])->first();
        $data = $update->update_json;
        // $data = '{"update_id":319791533,"message":{"message_id":2479,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1686152394,"photo":[{"file_id":"AgACAgIAAxkBAAIJr2SApMpiTjQmNJrl_aF66H0YhfWaAAIMzDEbQ14ISDuiiVl2XmIhAQADAgADcwADLwQ","file_unique_id":"AQADDMwxG0NeCEh4","file_size":994,"width":90,"height":51},{"file_id":"AgACAgIAAxkBAAIJr2SApMpiTjQmNJrl_aF66H0YhfWaAAIMzDEbQ14ISDuiiVl2XmIhAQADAgADbQADLwQ","file_unique_id":"AQADDMwxG0NeCEhy","file_size":14226,"width":320,"height":180},{"file_id":"AgACAgIAAxkBAAIJr2SApMpiTjQmNJrl_aF66H0YhfWaAAIMzDEbQ14ISDuiiVl2XmIhAQADAgADeAADLwQ","file_unique_id":"AQADDMwxG0NeCEh9","file_size":66357,"width":800,"height":450},{"file_id":"AgACAgIAAxkBAAIJr2SApMpiTjQmNJrl_aF66H0YhfWaAAIMzDEbQ14ISDuiiVl2XmIhAQADAgADeQADLwQ","file_unique_id":"AQADDMwxG0NeCEh-","file_size":134407,"width":1280,"height":720}]}}';
        // $data = '{"update_id":319789677,"message":{"message_id":154,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684568282,"text":"\/come_time","entities":[{"offset":0,"length":10,"type":"bot_command"}]}}';
        // $data = '{"update_id":319789691,"message":{"message_id":176,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1684656933,"text":"/come_time"}}';
        // dd($data);
        $this->handleMessage($data);
    }

    public function mlistener(Request $request)
    {
        $data = json_encode($request->all());
        TelegramUpdate::storeData($data);
        $this->handleManagerMessage($data);

    }

    public function handleManagerMessage($data = null)
    {
        try {
            TelegramUpdate::storeData($data);
            // $data = '{"update_id":736985814,"message":{"message_id":2,"from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"chat":{"id":-1001524098666,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1685029690,"text":"\/Daylies","entities":[{"offset":0,"length":9,"type":"bot_command"}]}}';
            // $data = '{"update_id":736986342,"callback_query":{"id":"9633533135766579","from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"message":{"message_id":1348,"from":{"id":6027276819,"is_bot":true,"first_name":"UsHelperBotManager","username":"UsHelperManagerbot"},"chat":{"id":-1001524098666,"title":"Test bot","type":"supergroup"},"date":1690125137,"text":"Guruhni tanlang","reply_markup":{"inline_keyboard":[[{"text":"Shimkent","callback_data":"callbackDailyGroup_2"}]]}},"chat_instance":"-3621411919573543680","data":"callbackDailyGroup_2"}}';
            $message = json_decode($data);
            $service = new TelegramService();
            // dd($message);
            if (property_exists($message, 'message')) {
                $service->managerRequestHandle($message->message->text, $message->message->chat->id);
            }
            if (property_exists($message, 'callback_query')) {
                $service->managerRequestCallbackHandle($message);
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
            $tgManager = new TelegramManager();
            // dd($data);
            if ($tgManager->isCallbackQuery($message)) {
                $tgManager->handleCallbackQuery($message);
                return;
            }

            if (!property_exists($message, 'message')) {
                return;
            }
            $isTextMessage = property_exists($message->message, 'text');
            $isContactMessage = property_exists($message->message, 'contact');
            $isFileMessage = $tgManager->isFileMessage($message);

            if (!$isTextMessage && !$isFileMessage && !$isContactMessage) {
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

            if ($isTextMessage) {
                if (BotCommandUtil::isWorkTimeCommand($message->message->text)) {
                    $messagePlanId = $service->getCommandPlanId($message->message->text);
                    IncomeMessage::storeData($message->message->text, $message, $writer->id, 0, $messagePlanId);
                    $service->saveFakeSeding($writer, $$messagePlanId, $message->message->text);
                    $tgManager->sendWorkTime($message->message->text, $writer->chat_id);
                    return;
                }
            }

            if ($isTextMessage) { //income commands
                $messagePlanId = $service->getCommandPlanId($message->message->text);
                $command = $message->message->text;
                $isCommand = $messagePlanId > 0;
                Setting::saveParam(Setting::MAKE_SYSTEM_REPORT, 1);
                if (!empty($writer) && $message->message->text == '/commands') {
                    $service->sendMessage('Please choose commands', $writer->chat_id, $service->getEmpKeyboard());
                }
            }

            if (!empty($writer) && $messagePlanId == 0) {
                $sending = MessageSending::getLatestSendByWorkerId($writer->id);
                if(BotCommandUtil::isWorkTimeCommand($sending->message)){
                    $sending = null;
                }
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
                // $tgManager->makeCustomRespone($messagePlanId, $writer);
                $messageText = $tgManager->getTgMessage($message);
                if (!empty($messageText)) {
                    IncomeMessage::storeData($messageText, $message, $writer->id, $sending ? $sending->id : 0, $messagePlanId);
                    Setting::saveParam(Setting::MAKE_REPORT, 1);
                }
            }

            // if (in_array(substr($message->message->text, 1), TelegramService::getManagerBotAsks()) && $message->message->chat->id == TelegramService::MANAGER_GROUP_ID) {
            //     $isCommand = true;
            //     $command = $message->message->text;
            // }
            // dd($message->message->text);
            if ($isCommand) {
                $inMessage = '';
                if (property_exists($message->message, 'text')) {
                    $inMessage = $message->message->text;
                }
                // dd($inMessage);
                $service->callbackCommand($command, $messagePlanId, $writer, $inMessage);
            }
        } catch (\Throwable $th) {
            echo $th->getMessage();
            //throw $th;
        }

    }

    public function saveResponceCallback($sending, $writer, $message)
    {
        $command = null;

        //handle register
        if ($sending && BotCommandUtil::isEqualCommand($sending->message_plan->template, TelegramService::COMMAND_REGISTER)) {
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
        if (property_exists($message->message, 'text') && BotCommandUtil::isEqualCommand($message->message->text, TelegramService::COMMAND_REGISTER)) {
            $command = $message->message->text;
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

    public function test()
    {
        $tgManager = new TelegramManager();
        $tgManager->forwardMessage();
        // MessageSendingManager::sendCommandCallbacks();

        // $tgManager = new TelegramManager();
        // $tgManager->sendHour(2242981, TelegramManager::WORK_START);

        // $respHour = '{"update_id":319790904,"callback_query":{"id":"9633531428984238","from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"message":{"message_id":1612,"from":{"id":6128162329,"is_bot":true,"first_name":"Lena bot","username":"UsHelperBot"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1685636529,"text":"Ishga kelgan soatizni tanlang","reply_markup":{"inline_keyboard":[[{"text":"1","callback_data":"ct_hour_workStart_2023-06-01_1"},{"text":"2","callback_data":"ct_hour_workStart_2023-06-01_2"},{"text":"3","callback_data":"ct_hour_workStart_2023-06-01_3"},{"text":"4","callback_data":"ct_hour_workStart_2023-06-01_4"},{"text":"5","callback_data":"ct_hour_workStart_2023-06-01_5"},{"text":"6","callback_data":"ct_hour_workStart_2023-06-01_6"}],[{"text":"7","callback_data":"ct_hour_workStart_2023-06-01_7"},{"text":"8","callback_data":"ct_hour_workStart_2023-06-01_8"},{"text":"9","callback_data":"ct_hour_workStart_2023-06-01_9"},{"text":"10","callback_data":"ct_hour_workStart_2023-06-01_10"},{"text":"11","callback_data":"ct_hour_workStart_2023-06-01_11"},{"text":"12","callback_data":"ct_hour_workStart_2023-06-01_12"}],[{"text":"13","callback_data":"ct_hour_workStart_2023-06-01_13"},{"text":"14","callback_data":"ct_hour_workStart_2023-06-01_14"},{"text":"15","callback_data":"ct_hour_workStart_2023-06-01_15"},{"text":"16","callback_data":"ct_hour_workStart_2023-06-01_16"},{"text":"17","callback_data":"ct_hour_workStart_2023-06-01_17"},{"text":"18","callback_data":"ct_hour_workStart_2023-06-01_18"}],[{"text":"19","callback_data":"ct_hour_workStart_2023-06-01_19"},{"text":"20","callback_data":"ct_hour_workStart_2023-06-01_20"},{"text":"21","callback_data":"ct_hour_workStart_2023-06-01_21"},{"text":"22","callback_data":"ct_hour_workStart_2023-06-01_22"},{"text":"23","callback_data":"ct_hour_workStart_2023-06-01_23"},{"text":"24","callback_data":"ct_hour_workStart_2023-06-01_24"}]]}},"chat_instance":"5520022277978860643","data":"ct_hour_workStart_2023-06-01_8"}}';
        // $resp = '{"update_id":319790808,"callback_query":{"id":"9633532709402750","from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"message":{"message_id":1452,"from":{"id":6128162329,"is_bot":true,"first_name":"Lena bot","username":"UsHelperBot"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1685581669,"text":"Ishga kelgan soatizni tanlang","reply_markup":{"inline_keyboard":[[{"text":"1","callback_data":"ct_hour_2023-06-01_1"},{"text":"2","callback_data":"ct_hour_2023-06-01_2"},{"text":"3","callback_data":"ct_hour_2023-06-01_3"},{"text":"4","callback_data":"ct_hour_2023-06-01_4"},{"text":"5","callback_data":"ct_hour_2023-06-01_5"},{"text":"6","callback_data":"ct_hour_2023-06-01_6"}],[{"text":"7","callback_data":"ct_hour_2023-06-01_7"},{"text":"8","callback_data":"ct_hour_2023-06-01_8"},{"text":"9","callback_data":"ct_hour_2023-06-01_9"},{"text":"10","callback_data":"ct_hour_2023-06-01_10"},{"text":"11","callback_data":"ct_hour_2023-06-01_11"},{"text":"12","callback_data":"ct_hour_2023-06-01_12"}],[{"text":"13","callback_data":"ct_hour_2023-06-01_13"},{"text":"14","callback_data":"ct_hour_2023-06-01_14"},{"text":"15","callback_data":"ct_hour_2023-06-01_15"},{"text":"16","callback_data":"ct_hour_2023-06-01_16"},{"text":"17","callback_data":"ct_hour_2023-06-01_17"},{"text":"18","callback_data":"ct_hour_2023-06-01_18"}],[{"text":"19","callback_data":"ct_hour_2023-06-01_19"},{"text":"20","callback_data":"ct_hour_2023-06-01_20"},{"text":"21","callback_data":"ct_hour_2023-06-01_21"},{"text":"22","callback_data":"ct_hour_2023-06-01_22"},{"text":"23","callback_data":"ct_hour_2023-06-01_23"},{"text":"24","callback_data":"ct_hour_2023-06-01_24"}]]}},"chat_instance":"5520022277978860643","data":"ct_hour_2023-06-01_10"}}';
        // $respMinute = '{"update_id":319790905,"callback_query":{"id":"9633532476215069","from":{"id":2242981,"is_bot":false,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","language_code":"en"},"message":{"message_id":1612,"from":{"id":6128162329,"is_bot":true,"first_name":"Lena bot","username":"UsHelperBot"},"chat":{"id":2242981,"first_name":"Umid","last_name":"Hamidov","username":"Samirchik03","type":"private"},"date":1685636529,"edit_date":1685636590,"text":"Ishga kelgan daqiqangizni tanlang (2023-06-01 8)","reply_markup":{"inline_keyboard":[[{"text":"00","callback_data":"ct_minute_workStart_2023-06-01_8_0"},{"text":"05","callback_data":"ct_minute_workStart_2023-06-01_8_5"},{"text":"10","callback_data":"ct_minute_workStart_2023-06-01_8_10"}],[{"text":"15","callback_data":"ct_minute_workStart_2023-06-01_8_15"},{"text":"20","callback_data":"ct_minute_workStart_2023-06-01_8_20"},{"text":"25","callback_data":"ct_minute_workStart_2023-06-01_8_25"}],[{"text":"30","callback_data":"ct_minute_workStart_2023-06-01_8_30"},{"text":"35","callback_data":"ct_minute_workStart_2023-06-01_8_35"},{"text":"40","callback_data":"ct_minute_workStart_2023-06-01_8_40"}],[{"text":"45","callback_data":"ct_minute_workStart_2023-06-01_8_45"},{"text":"50","callback_data":"ct_minute_workStart_2023-06-01_8_50"},{"text":"55","callback_data":"ct_minute_workStart_2023-06-01_8_55"}],[{"text":"Orqaga","callback_data":"ct_minute_workStart_2023-06-01_8_back"}]]}},"chat_instance":"5520022277978860643","data":"ct_minute_workStart_2023-06-01_8_20"}}';
        // $tgManager->handleCallbackQuery(json_decode($respMinute));
    }

    //
}