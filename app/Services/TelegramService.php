<?php

namespace App\Services;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use GuzzleHttp\Client;

class TelegramService
{

    private $client;

    const URL_LISTENER = 'https://bot.lets.uz/listener';
    const URL_MANAGER_LISTENER = 'https://bot.lets.uz/mlistener';

    const BOTID = '6128162329:AAHdm_brrZ7E10Svm-BMfDweXXrVKVNTyqI';

    const MANAGER_BOT_ID = '6027276819:AAFOkVYWxQmxNHIJnRbM1Yw6EvLo9FNaPWM';

    const TELEGRAM_URL = 'https://api.telegram.org/bot';

    const FILES_CHAT_ID = '-1001810312998';
    public function __construct()
    {
        $this->client = new Client();
        $this->sendBotId = self::BOTID;
    }

    public $sendBotId;

    public function setCert()
    {
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/setWebhook', [
            'json' => [
                'url' => self::URL_LISTENER,
            ]
        ]);
        $body = $response->getBody()->getContents();


        $response = $this->client->request('POST', self::TELEGRAM_URL . self::MANAGER_BOT_ID . '/setWebhook', [
            'json' => [
                'url' => self::URL_MANAGER_LISTENER,
            ]
        ]);
        // $body = $response->getBody()->getContents();
        // var_dump($body);
    }

    public function deleteWebhook()
    {
        $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/deleteWebhook');
        $body = $response->getBody()->getContents();
        var_dump($body);
    }

    public function sendMessage($message, $chat_id, $keyboard = [], $isInline = false, $reply_to_message_id = null)
    {
        $params = [
            'chat_id' => $chat_id,
            'text' => $message,
        ];
        if (!empty($reply_to_message_id)) {
            $params['reply_to_message_id'] = $reply_to_message_id;
        }
        if (!empty($keyboard)) {
            $keyboardParams = [];
            if ($isInline) {
                $keyboardParams = ['inline_keyboard' => $keyboard];
            } else {
                $keyboardParams = [
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ];
            }
            $params['reply_markup'] = $keyboardParams;
        }
        $response = $this->client->request('POST', self::TELEGRAM_URL . $this->sendBotId . '/sendMessage', ['json' => $params]);

        $body = $response->getBody()->getContents();
        // var_dump($body);
        return json_decode($body);
    }

    public function editsendedMessage($messageId, $chat_id, $message, $keyboard = [])
    {
        $params = [
            'message_id' => $messageId,
            'chat_id' => $chat_id,
            'text' => $message,
        ];
        if (!empty($reply_to_message_id)) {
            $params['reply_to_message_id'] = $reply_to_message_id;
        }
        if (!empty($keyboard)) {
            $keyboardParams = ['inline_keyboard' => $keyboard];
            $params['reply_markup'] = $keyboardParams;
        }
        $response = $this->client->request('POST', self::TELEGRAM_URL . $this->sendBotId . '/editMessageText', ['json' => $params]);

        $body = $response->getBody()->getContents();
        return json_decode($body);
    }
    public function forwardMessage($chat_id, $from_chat_id, $message_id , $protect_content = false)
    {
        $params = [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id,
            'protect_content' => $protect_content,
        ];
        $response = $this->client->request('POST', self::TELEGRAM_URL . $this->sendBotId . '/forwardMessage', ['json' => $params]);

        $body = $response->getBody()->getContents();
        // var_dump($body);
        return json_decode($body);
    }

    const COMMAND_REGISTER = 'register';
    const COMMAND_TOTAL_WORK_TIME = 'total_work_time';
    const COMMAND_COME_TIME = 'come_time';
    const COMMAND_LEAVE_WORK = 'leave_work';
    const COMMAND_LATE_REASON = 'late_reason';
    const COMMAND_WORK_PLAN = 'work_plan';

    const COMMAND_OFFICE_ON = 'officeon';
    const COMMAND_OFFICE_OFF = 'officeoff';
    const COMMAND_WORK_LIST = 'worklist';
    const COMMAND_REASON = 'reason';

    const MANAGER_GROUP_ID = '-1001524098666';
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
            self::COMMAND_TOTAL_WORK_TIME,
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

    public function getRespText($command)
    {
        $text = '';
        switch ($command) {
            case self::COMMAND_COME_TIME:
                $text = 'Bugun ish boshlagan vaqtingizni kiriting';
                break;
            case self::COMMAND_LEAVE_WORK:
                $text = 'Ishxonadan ketgan vaqtingizni kiriting';
                break;
            case self::COMMAND_LATE_REASON:
                $text = 'Kech qolishingiz  yoki kelmasligingiz sababini yozing';
                break;
            case self::COMMAND_WORK_PLAN:
                $text = "Bugun qilmoqchi bo'lgan ishlaringizni yozing";
                break;
        }
        return $text;
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
    public function callbackCommand($inCommand, $message_plan_id, $writer, $inMessage)
    {
        $commandText = substr($inCommand, 1);
        // dd(in_array(substr($inCommand,1),self::getAllCommands()));
        $empKeyboards = $this->getEmpKeyboard();
        if (in_array($commandText, self::getSystemBotAsks())) {
            $send_time = date('Y-m-d H:i:s');
            $item = MessageSending::newItem($writer->id, $message_plan_id, $send_time, $inCommand, false);
            $item->is_fake = 1;
            $item->saveSendTime(-1);
        }
        // dd($inMessage);
        $inMessage = substr($inMessage, 1);
        if (in_array($inMessage, self::getSystemBotAsks()) && !empty($this->getRespText($inMessage))) {
            $this->sendMessage($this->getRespText($inMessage), $writer->chat_id, $empKeyboards);
        }
        if (self::COMMAND_REGISTER == $commandText && $message_plan_id > 0) {
            $maxstep = MessageSending::where(['receiver_id' => $writer->id, 'message_plan_id' => $message_plan_id])->whereNotNull('answer_time')->max('step');
            $step = (empty($maxstep) ? 0 : $maxstep) + 1;
            if ($step == 4) {
                $text = 'You are already registered';
                $this->sendMessage($text, $writer->chat_id, $empKeyboards);
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
                $responce = $this->sendMessage($text, $writer->chat_id, $empKeyboards);
            }
            $item = MessageSending::newItem($writer->id, $message_plan_id, $send_time, $text, false);
            $item->step = $step;
            $item->is_fake = 1;
            $item->saveSendTime($responce->result->message_id);
        }

    }

    public function getManagerKeyboard()
    {
        return [
            [
                [
                    "text" => "/" . self::COMMAND_OFFICE_ON,
                    "callback_data" => "/" . self::COMMAND_OFFICE_ON
                ],
                [
                    "text" => "/" . self::COMMAND_OFFICE_OFF,
                    "callback_data" => "/" . self::COMMAND_OFFICE_OFF
                ],
                [
                    "text" => "/" . self::COMMAND_WORK_LIST,
                    "callback_data" => "/" . self::COMMAND_WORK_LIST
                ],
                [
                    "text" => "/" . self::COMMAND_REASON,
                    "callback_data" => "/" . self::COMMAND_REASON
                ]
            ]
        ];
    }

    public function getEmpKeyboard()
    {
        $keyboards = [];
        foreach ($this->getSystemBotAsks() as $key => $botCommand) {
            $keyboards[] = [
                "text" => "/" . $botCommand,
                "callback_data" => "/" . $botCommand
            ];
        }
        return [
            $keyboards
        ];
    }

    public function managerRequestHandle($inCommand, $messageChatId)
    {
        $commandText = substr($inCommand, 1);
        $keyboard = $this->getManagerKeyboard();
        $this->sendBotId = self::MANAGER_BOT_ID;
        $emptyText = "Ma'lumot topilmadi";
        // dd($inCommand);
        if (in_array($commandText, self::getManagerBotAsks()) && $messageChatId == self::MANAGER_GROUP_ID) {

            $inCommand = '';
            if($commandText == self::COMMAND_OFFICE_ON){
                $this->handleOfficeCome();
                return;
            }
            if($commandText == self::COMMAND_OFFICE_OFF){
                $this->handleOfficeCome(false);
                return;
            }
            if ($commandText == self::COMMAND_WORK_LIST) {
                $inCommand = self::COMMAND_WORK_PLAN;
            }
            if ($commandText == self::COMMAND_REASON) {
                $inCommand = self::COMMAND_LATE_REASON;
            }

            $mp = MessagePlan::where(['template' => "/" . $inCommand])->first();

            if (!empty($mp)) {

                $result = IncomeMessage::where(['message_plan_id' => $mp->id])->whereRaw('Date(created_at) = "' . date('Y-m-d') . '"')->where('sending_id', '>', '0')->get();

                $messageText = [];

                foreach ($result as $key => $message) {
                    if (!isset($messageText[$message->writer_id])) {
                        $messageText[$message->writer_id] = '@' . $message->receiver->username . ' ' . $message->receiver->fullname;
                    }
                    if ($commandText != self::COMMAND_OFFICE_ON || $commandText != self::COMMAND_OFFICE_OFF) {
                        $messageText[$message->writer_id] .= "\n " . $message->message;
                    }
                }

                // dd($messageText);
                if ($commandText != self::COMMAND_OFFICE_OFF) {
                    $mesageData = implode("\n", $messageText);
                    // dd($incomers);
                } else {
                    $receivers = Receiver::getEmployees()->keyBy('id');
                    // dd($receivers);
                    foreach ($receivers as $key => $receiver) {
                        if (!isset($messageText[$receiver->id])) {
                            $absents[$receiver->id] = '@' . $receiver->username . ' ' . $receiver->fullname;

                        }
                    }
                    $mesageData = implode("\n", $absents);
                }
                if (empty($mesageData)) {
                    $mesageData = $emptyText;
                }
                // dd($mesageData);
                $responce = $this->sendMessage($mesageData, self::MANAGER_GROUP_ID, $keyboard);
            }
        }
        // else{
        // $responce = $this->sendMessage('test', self::MANAGER_GROUP_ID, $keyboard);
        // }
    }

    public function handleOfficeCome($isCome = true){
        $keyboard = $this->getManagerKeyboard();
        $emptyText = "Ma'lumot topilmadi";
        $reports = WorkReport::where([
            'date' => date('Y-m-d'),
            'type' => 1 
        ])
        ->get();

        $messageText = [];

        $givedReportWorkers = [];
        foreach ($reports as $key => $report) {
            $isOk = false;
            $givedReportWorkers[$report->receiver_id] = $report->receiver_id;
            if($isCome && ($report->start_hour + $report->start_minute) > 0 && ($report->end_hour + $report->end_minute) == 0){
                $isOk = true;
            }
            if(!$isCome && ( $report->end_hour + $report->end_minute) > 0){
                $isOk = true;
            }
            if(!$isOk){
                continue;
            }
            if (!isset($messageText[$report->receiver_id])) {
                $messageText[$report->receiver_id] = '@' . $report->receiver->username . ' ' .  $report->receiver->fullname.' ';
                $messageText[$report->receiver_id] .= $isCome ? str_pad($report->start_hour,2,"0",STR_PAD_LEFT) .':'. str_pad($report->start_minute,2,"0",STR_PAD_LEFT) : str_pad($report->end_hour,2,"0",STR_PAD_LEFT) .':'. str_pad($report->end_minute,2,"0",STR_PAD_LEFT);
            }
        }


        if(!$isCome){
            $receivers = Receiver::getEmployees()->keyBy('id');
            foreach ($receivers as $key => $receiver) {
                if (!isset($givedReportWorkers[$receiver->id])) {
                    $messageText[$receiver->id] = '@' . $receiver->username . ' ' . $receiver->fullname;
    
                }
            }
        }

        $mesageData = implode("\n", $messageText);
        if (empty($mesageData)) {
            $mesageData = $emptyText;
        }

        // dd($mesageData);
        $responce = $this->sendMessage($mesageData, self::MANAGER_GROUP_ID, $keyboard);
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