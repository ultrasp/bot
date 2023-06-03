<?php

namespace App\Dto;


class TelegramMessageDto
{

    public $chat_id;
    public $message;

    public $reply_to_message_id;

    public $keyboard = [];

    public $isInline = false;

    public $method = 'sendMessage';
}