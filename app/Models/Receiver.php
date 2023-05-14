<?php

namespace App\Models;

use App\Services\GoogleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receiver extends Model
{
    use  SoftDeletes;
    protected $fillable = ['username'];
    public static function getByUsername(string $username){
        return self::firstOrNew(['username' =>  $username]);
    }

    const USER_TYPE_EMPLOYEE = 1;

    public static function getEmployees(){
        return self::where(['user_type' => self::USER_TYPE_EMPLOYEE])->get();
    }
    public static function storeData($chatData){
        $user = self::getByUsername($chatData->username);
        if(empty($user->id)){
            $user->lastname = $chatData->last_name;
            $user->firstname = $chatData->first_name;
            $user->chat_id = $chatData->id;
            $user->lastname = $chatData->last_name;
            $user->user_type = self::USER_TYPE_EMPLOYEE;
        }
        $user->last_answer_time = date('Y-m-d H:i:s');
        $user->message_cnt += 1;
        $user->save();
        return $user;
    }
    //

    public static function writeToSheet(){
        $all =  self::get();
        $data = [
            [
                'username', 'last_name','first_name','user_type','last_answer_time','message_cnt'
            ]
        ];
        foreach ($all as $key => $user) {
            $data[] = [
                $user->username,
                $user->lastname ?? '',
                $user->firstname ?? '',
                $user->user_type,
                $user->last_answer_time ? date('Y-m-d H:i:s',strtotime($user->last_answer_time)) : '',
                $user->message_cnt
            ];
        }
        $sheet = 'users';
        $service = new GoogleService();
        // dd($data);
        $service->deleteRows($sheet);
        $service->writeValues($sheet,$data);
    }
}
