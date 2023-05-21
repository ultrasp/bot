<?php

namespace App\Models;

use App\Services\GoogleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receiver extends Model
{
    use SoftDeletes;
    protected $fillable = ['username'];
    public static function getByUsername(string $username)
    {
        return self::firstOrNew(['username' => $username]);
    }

    const USER_TYPE_EMPLOYEE = 2;
    const USER_TYPE_GUEST = 0;
    const USER_TYPE_GUEST_FILLED = 1;
    const USER_TYPE_BOT = 3;

    const BOT_USERNAME = 'UsHelperBot';

    public static function getEmployees()
    {
        return self::where(['user_type' => self::USER_TYPE_EMPLOYEE])->get();
    }
    public static function storeData($chatData, $userType = self::USER_TYPE_GUEST)
    {
        $user = self::getByUsername($chatData->username);
        if (empty($user->id)) {
            $user = self::newItem($chatData->username, $chatData->last_name, $chatData->first_name, $chatData->id, $userType);
        }
        $user->last_answer_time = date('Y-m-d H:i:s');
        $user->message_cnt += 1;
        $user->save();
        return $user;
    }
    //

    public static function writeToSheet()
    {
        $all = self::get();
        $data = [
            [
                'username',
                'last_name',
                'first_name',
                'user_type',
                'last_answer_time',
                'message_cnt',
                'fullname',
                'contact_phone'
            ]
        ];
        foreach ($all as $key => $user) {
            $data[] = [
                $user->username,
                $user->lastname ?? '',
                $user->firstname ?? '',
                $user->user_type,
                $user->last_answer_time ? date('Y-m-d H:i:s', strtotime($user->last_answer_time)) : '',
                $user->message_cnt,
                $user->fullname,
                $user->contact_phone
            ];
        }
        $sheet = 'users';
        $service = new GoogleService();
        // dd($data);
        $service->deleteRows($sheet);
        $service->writeValues($sheet, $data);
    }

    public static function readSheet()
    {
        $gooleService = new GoogleService();
        $users = $gooleService->readSheetValues(GoogleService::SPREADSHEET_ID, GoogleService::usersSheet);
        // dd($users);

        $setting = Setting::getItem(Setting::USER_LIST);

        $encodeData = md5(serialize($users));

        // dd($setting);
        if ($setting->param_value == $encodeData) {
            return 0;
        } else {
            $setting->param_value = $encodeData;
            $setting->save();
        }

        $userTypeColNumber = 3;
        foreach ($users as $row) {
            if (empty($row[0]) || $row[$userTypeColNumber] == '') {
                continue;
            }
            $user = self::getByUsername($row[0]);
            if (empty($user->id))
                continue;
            $user->user_type = $row[$userTypeColNumber];
            $user->save();
        }
        return 1;
    }

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