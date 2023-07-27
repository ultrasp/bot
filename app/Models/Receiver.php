<?php

namespace App\Models;

use App\Services\GoogleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receiver extends Model
{
    use SoftDeletes;
    protected $fillable = ['username'];
    public static function getByUsername(string $username)
    {
        return self::firstOrNew(['username' => $username]);
    }

    public static function getByChatId(string $chatId)
    {
        return self::firstOrNew(['chat_id' => $chatId]);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }


    const USER_TYPE_EMPLOYEE = 2;
    const USER_TYPE_GUEST = 0;
    const USER_TYPE_GUEST_FILLED = 1;
    const USER_TYPE_BOT = 3;

    const BOT_USERNAME = 'UsHelperBot';

    public static function getEmployees($with = [])
    {
        $query = self::where(['user_type' => self::USER_TYPE_EMPLOYEE]);
        if(!empty($with)){
            $query->with($with);
        }
        return $query->get();
    }
    public static function storeData($chatData, $userType = self::USER_TYPE_GUEST)
    {
        $user = self::getByChatId($chatData->id);
        if (empty($user->id)) {
            $user = self::newItem($chatData->username, property_exists($chatData,'last_name') ? $chatData->last_name : null, $chatData->first_name, $chatData->id, $userType);
        }
        $user->username = $chatData->username; 
        $user->last_answer_time = date('Y-m-d H:i:s');
        $user->message_cnt += 1;
        $user->save();
        return $user;
    }
    //

    public static function storeBots()
    {
        $user = self::getByUsername(self::BOT_USERNAME);
        if (empty($user->id)) {
            $user = self::newItem(self::BOT_USERNAME, 'us_helper_bot', 'us_helper_bot', 0, self::USER_TYPE_BOT);
        }
    }

    public function getBot()
    {
        return self::getByUsername(self::BOT_USERNAME);
    }

    public static function newItem($username, $lastname, $firstName, $chatid, $userType)
    {
        $user = new self();

        $user->username = $username;
        $user->lastname = $lastname;
        $user->firstname = $firstName;
        $user->chat_id = $chatid;
        $user->user_type = $userType;
        $user->save();
        return $user;
    }
}