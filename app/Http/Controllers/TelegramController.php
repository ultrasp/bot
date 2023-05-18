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
        $data = $request->all();
        TelegramUpdate::storeData(json_encode($data));

        try {
            $message = json_decode(json_encode($data));
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
                    if ($sending->message_plan->template == TelegramService::COMMAND_REGISTER) {
                        $isCommand = true;
                        $command == TelegramService::COMMAND_REGISTER;
                        if ($sending->step == 1) {
                            $writer->fullname = $message->message->text;
                            $writer->save();
                        }
                        if ($sending->step == 2) {
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