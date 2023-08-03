<?php

namespace App\Managers;

use App\Models\BotSending;
use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\TelegramService;
use App\Utils\BotCommandUtil;

class MessageSendingManager
{

    public static function updateAndSend($isChanged)
    {
        $sendingTime = Setting::getItem(Setting::SENDING_CREATE_TIME);
        $isSendingsUpdated = false;
        if ($sendingTime->param_value == null || (!empty($sendingTime->param_value) && date('Y-m-d', strtotime($sendingTime->param_value)) != date('Y-m-d')) || $isChanged) {
            MessageSendingManager::createSendings();
            $isSendingsUpdated = true;
        }
        if ($isSendingsUpdated) {
            $sendingTime->setVal(date('Y-m-d H:i:s'));
        }
        MessageSendingManager::send();
        MessageSendingManager::sendCommandCallbacks();
    }
    public static function createSendings()
    {
        $receivers = Receiver::getEmployees(['groups']);
        $templates = MessagePlan::getAllByType(MessagePlan::TYPE_ASK);
        if (!empty($receivers) && !empty($templates)) {
            foreach ($receivers as $receiver) {
                foreach ($templates as $template) {
                    $send_time = date('Y-m-d H:i:s', strtotime('midnight +' . $template->send_minute . ' minutes'));
                    // dd($template->canSend());
                    if ($template->canSend() && $template->canSendReceiver($receiver) && $send_time >= date('Y-m-d H:i:s')) {
                        $dbTemplate = MessageSending::where([
                            'receiver_id' => $receiver->id,
                            'message_plan_id' => $template->id,
                            'is_fake' => 0,
                            'send_plan_time' => $send_time
                        ])
                            ->first();
                        if (empty($dbTemplate)) {
                            $item = MessageSending::newItem($receiver->id, $template->id, $send_time, $template->template);
                        }
                    }
                }
            }
        }
    }

    public static function send()
    {
        // $date = date('Y-m-d H:i:00');
        // echo $date;
        $sendings = MessageSending::where([
            'send_time' => null,
            'is_fake' => 0
        ])
            // ->where('send_plan_time', $date)
            // ->whereRaw('send_plan_time between "'.date('Y-m-d H:i:s',strtotime('+5 minutes')).'" and "'.date('Y-m-d H:i:s').'"')
            ->whereRaw('send_plan_time <=  "' . date('Y-m-d H:i:s') . '"')
            ->get();
        // var_dump($sendings);
        $service = new TelegramService();
        foreach ($sendings as $sending) {
            if (!empty($sending->receiver)) {
                $status = $service->sendMessage($sending->message, $sending->receiver->chat_id);

                // $writer = Receiver::getBot($sending->receiver->id,);
                // $item = self::newItem($receiver->id,$template->id,$send_time,$template->template);
                // IncomeMessage::storeData($sending->message, $writer->id, $sending ? $sending->id : 0, $sending->message_plan_id,1);
                $sending->saveSendTime($status->ok ? $status->result->message_id : -1);
            }
        }
    }


    public static function sendCommandCallbacks()
    {
        $curMinute = date('H') * 60 + date('i');
        $date = date('Y-m-d');
        $customMessagePlans = MessagePlan::where([
            'type' => MessagePlan::TYPE_CUSTOM_CALLBACK,
            'send_minute' => $curMinute
        ])
            // ->whereRaw('send_minute =  "' . $curMinute . '"')
            ->whereRaw('exists (select 1 from message_plans p where p.id = message_plans.parent_id and type = ' . MessagePlan::TYPE_SYSTEM . ')')
            ->get();

        if (empty($customMessagePlans)) {
            return;
        }
        $service = new TelegramService();
        $receivers = Receiver::getEmployees(['groups']);
        foreach ($customMessagePlans as $customMPlan) {

            $parentPlan = MessagePlan::where('id',$customMPlan->parent_id)->first();
            if( BotCommandUtil::isInCommands($parentPlan->template,[TelegramService::COMMAND_COME_TIME,TelegramService::COMMAND_LEAVE_WORK])){
                $incomes = WorkReport::where([
                    'date' => $date,
                    'type' => 1 
                    ])
                    ->get();
                $incomes =  $incomes->filter(function ($income) use($parentPlan) {
                        if( BotCommandUtil::isEqualCommand($parentPlan->template, TelegramService::COMMAND_COME_TIME) && $income->start_hour + $income->start_minute > 0){
                            return true;
                        }
                        if( BotCommandUtil::isEqualCommand($parentPlan->template, TelegramService::COMMAND_LEAVE_WORK) && $income->end_hour + $income->end_minute > 0){
                            return true;
                        }
                        return false;
                });
                $incomes = $incomes->groupBy('receiver_id');
            }else{
                $incomes = IncomeMessage::where([
                    'message_plan_id' => $customMPlan->parent_id,
                ])
                    ->where('sending_id', '!=', 0)
                    ->get()
                    ->groupBy('writer_id');
    
            }

            $senders = [];
            foreach ($receivers as $receiver) {
                $canSend = false;

                if ($customMPlan->parent_action_type == MessagePlan::PARENT_ACTION_TYPE_NOT_ANSWER && !$incomes->has($receiver->id)) {
                    $canSend = true;
                }

                if ($customMPlan->parent_action_type == MessagePlan::PARENT_ACTION_TYPE_RESPONCED && $incomes->has($receiver->id)) {
                    $canSend = true;
                }

                if ($canSend && $customMPlan->canSend($date) && $customMPlan->canSendReceiver($receiver)) {
                    $senders[] = $receiver;
                }
            }

            if(!empty($senders)){
                foreach ($senders as $sender) 
                {
                    try {
                        $botMessage = BotSending::storeData($sender->id,$customMPlan->template,$customMPlan->id);
                        $update = $service->sendMessage($customMPlan->template, $sender->chat_id);
                        $botMessage->storeUpdate(json_encode($update));
                    } catch (\Throwable $th) {
                    }
                }
            }
        }
    }
}