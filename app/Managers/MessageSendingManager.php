<?php

namespace App\Managers;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Services\TelegramService;

class MessageSendingManager
{

    public static function updateAndSend($isChanged)
    {
        $sendingTime = Setting::getItem(Setting::SENDING_CREATE_TIME);
        $isSendingsUpdated = false;
        // if ($sendingTime->param_value == null || (!empty($sendingTime->param_value) && date('Y-m-d', strtotime($sendingTime->param_value)) != date('Y-m-d')) || $isChanged) {
            MessageSendingManager::createSendings();
            $isSendingsUpdated = true;
        // }
        if ($isSendingsUpdated) {
            $sendingTime->setVal(date('Y-m-d H:i:s'));
        }
        // MessageSendingManager::send();
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


}