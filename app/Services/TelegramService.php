<?php

namespace App\Services;

use App\Models\MessagePlan;
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
        return json_decode($body);
    }

    const COMMAND_REGISTER = 'register';
    const COMMAND_COME_TIME = 'come_time';
    const COMMAND_LEAVE_WORK = 'leave_work';
    const COMMAND_LATE_REASON = 'late_reason';
    const COMMAND_WORK_PLAN = 'work_plan';

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
        if (in_array($text, $this->empCommands())) {
            $mplan = MessagePlan::getSystemAsk($text);
            if (!empty($mplan)) {
                return $mplan->id;
            }
        }
        return 0;
    }

    public function sharePhone($chat_id){
        $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/sendMessage', [
            'json' => [
                'chat_id' => $chat_id,
                "text" => 'Share you contact phone?',
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

    }
    public function callbackCommand($command, $chat_id)
    {
        if ("\\" . self::COMMAND_REGISTER == $command) {
            $this->sharePhone($chat_id);
            // $this->sendMessage('Please enter fullname', $chat_id);
            // $response = $this->client->request('POST', self::TELEGRAM_URL . self::BOTID . '/sendMessage', ['json' => $params]);

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