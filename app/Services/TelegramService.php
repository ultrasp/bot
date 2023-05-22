<?php

namespace App\Services;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Setting;
use GuzzleHttp\Client;

class TelegramService
{

    private $client;

    const URL_LISTENER = 'https://bot.lets.uz/listener';

    const BOTID = '6128162329:AAHdm_brrZ7E10Svm-BMfDweXXrVKVNTyqI';

    const TELEGRAM_URL = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setCert()
    {
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/setWebhook', [
            'json' => [
                'url' => self::URL_LISTENER,
            ]
        ]);
        $body = $response->getBody()->getContents();
        var_dump($body);
    }

    public function deleteWebhook()
    {
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/deleteWebhook');
        $body = $response->getBody()->getContents();
        var_dump($body);
    }

    public function sendMessage($message, $chat_id, $reply_to_message_id = null)
    {
        $params = [
            'chat_id' => $chat_id,
            'text' => $message,
        ];
        if (!empty($reply_to_message_id)) {
            $params['reply_to_message_id'] = $reply_to_message_id;
        }
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/sendMessage', ['json' => $params]);

        $body = $response->getBody()->getContents();
        // var_dump($body);
        return json_decode($body);
    }

    const COMMAND_REGISTER = 'register';
    const COMMAND_COME_TIME = 'come_time';
    const COMMAND_LEAVE_WORK = 'leave_work';
    const COMMAND_LATE_REASON = 'late_reason';
    const COMMAND_WORK_PLAN = 'work_plan';

    const COMMAND_OFFICE_ON = 'officeon';
    const COMMAND_OFFICE_OFF = 'officeoff';
    const COMMAND_WORK_LIST = 'worklist';
    const COMMAND_REASON = 'reason';

    public static function getManagerBotAsks()
    {
        return [
            self::COMMAND_OFFICE_ON,
            self::COMMAND_OFFICE_OFF,
            self::COMMAND_WORK_LIST,
            self::COMMAND_REASON
        ];
    }

    public static function getAllCommands()
    {
        return [
            self::COMMAND_REGISTER,
            self::COMMAND_COME_TIME,
            self::COMMAND_LEAVE_WORK,
            self::COMMAND_LATE_REASON,
            self::COMMAND_WORK_PLAN
        ];
    }

    public static function getSystemBotAsks()
    {
        return [
            self::COMMAND_COME_TIME,
            self::COMMAND_LEAVE_WORK,
            self::COMMAND_LATE_REASON,
            self::COMMAND_WORK_PLAN
        ];
    }

    public function empCommands()
    {
        return [
            self::COMMAND_REGISTER,
            self::COMMAND_COME_TIME,
            self::COMMAND_LEAVE_WORK,
            self::COMMAND_LATE_REASON,
            self::COMMAND_WORK_PLAN
        ];
    }

    public function getCommandPlanId($text)
    {
        if (in_array(substr($text, 1), $this->empCommands())) {
            $mplan = MessagePlan::getSystemAsk($text);
            if (!empty($mplan)) {
                return $mplan->id;
            }
        }
        return 0;
    }

    public function sharePhone($message, $chat_id)
    {
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/sendMessage', [
            'json' => [
                'chat_id' => $chat_id,
                "text" => $message,
                'reply_markup' => array(
                    'keyboard' => array(
                        array(
                            array(
                                'text' => "SHOW PHONE",
                                'request_contact' => true
                            )
                        )
                    ),

                    'one_time_keyboard' => true,
                    'resize_keyboard' => true
                )
            ]
        ]);

        $body = $response->getBody()->getContents();
        return json_decode($body);
    }
    public function callbackCommand($inCommand, $message_plan_id, $writer, $messageChatId)
    {
        $commandText = substr($inCommand, 1);
        // dd(in_array(substr($inCommand,1),self::getAllCommands()));
        if (in_array($commandText, self::getSystemBotAsks())) {
            $send_time = date('Y-m-d H:i:s');
            $item = MessageSending::newItem($writer->id, $message_plan_id, $send_time, $inCommand, false);
            $item->is_fake = 1;
            // dd($item);
            $item->saveSendTime(-1);
            // dd($item);
        }
        // dd('aa');
        if (self::COMMAND_REGISTER == $commandText && $message_plan_id > 0) {
            $maxstep = MessageSending::where(['receiver_id' => $writer->id, 'message_plan_id' => $message_plan_id])->whereNotNull('answer_time')->max('step');
            $step = (empty($maxstep) ? 0 : $maxstep) + 1;
            if ($step == 4) {
                $text = 'You are already registered';
                $this->sendMessage($text, $writer->chat_id);
                return;
            }
            $text = '';
            $send_time = date('Y-m-d H:i:s');
            $responce = null;
            if ($step == 1) {
                $text = 'Please enter fullname';
                $responce = $this->sendMessage($text, $writer->chat_id);
            }
            if ($step == 2) {
                $text = 'Share you contact phone?';
                $responce = $this->sharePhone($text, $writer->chat_id);
            }
            if ($step == 3) {
                $text = 'Your sucessfully registered';
                $responce = $this->sendMessage($text, $writer->chat_id);
            }
            $item = MessageSending::newItem($writer->id, $message_plan_id, $send_time, $text, false);
            $item->step = $step;
            $item->is_fake = 1;
            $item->saveSendTime($responce->result->message_id);
        }

        if (in_array($commandText, self::getManagerBotAsks()) && $messageChatId == '-1001524098666') {

            if ($commandText == self::COMMAND_OFFICE_ON) {
                $mp = MessagePlan::where(['template' => "/" . self::COMMAND_COME_TIME])->first();
                if (!empty($mp)) {
                    $result = IncomeMessage::where(['message_plan_id' => $mp->id])->whereRaw('Date(created_at) = "' . date('Y-m-d') . '"')->where('sending_id', '>', '0')->get();
                    $messageText = [];
                    foreach ($result as $key => $message) {
                        $messageText[$message->writer_id] = $message->receiver->username . ' ' . $message->receiver->fullname;
                    }
                    if (!empty($messageText)) {
                        $incomers = implode("\n", $messageText);
                        $responce = $this->sendMessage($incomers, $writer->chat_id);
                    }
                }
            }

        }
    }

    public function setMyCommands()
    {
        $params = [
            'commands' => [
                [
                    'command' => self::COMMAND_REGISTER,
                    'description' => 'Register user'
                ],
                [
                    'command' => self::COMMAND_COME_TIME,
                    'description' => 'Come time to work'
                ],
                [
                    'command' => self::COMMAND_LEAVE_WORK,
                    'description' => 'Leave time'
                ],
                [
                    'command' => self::COMMAND_LATE_REASON,
                    'description' => 'Being late or absent reason'
                ],
                [
                    'command' => self::COMMAND_WORK_PLAN,
                    'description' => 'Todays work pplan'
                ],
            ],
        ];
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/setMyCommands', ['json' => $params]);

    }

}