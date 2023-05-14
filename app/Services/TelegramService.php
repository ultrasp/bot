<?php

namespace App\Services;

use GuzzleHttp\Client;

class TelegramService
{

    private $client;

    const URL_LISTENER = 'https://bot.lets.uz/listener'; 

    const  BOTID = '6128162329:AAHdm_brrZ7E10Svm-BMfDweXXrVKVNTyqI';

    const TELEGRAM_URL =  'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setCert(){
        $response = $this->client->request('POST', self::TELEGRAM_URL.self::BOTID.'/setWebhook', ['json' => [
            'url' => self::URL_LISTENER,
        ]]);
        $body = $response->getBody()->getContents();
        var_dump($body);
    }

    public function sendMessage($message, $chat_id, $reply_to_message_id = null){
        $params = [
            'chat_id' => $chat_id,
            'text'  => $message,
        ];
        if(!empty($reply_to_message_id)){
            $params[ 'reply_to_message_id'] = $reply_to_message_id;
        }
        $response = $this->client->request('POST', self::TELEGRAM_URL.self::BOTID.'/sendMessage', ['json' => $params]);

        $body = $response->getBody()->getContents();
        return json_decode($body);
        // var_dump($body);    
    }

}
