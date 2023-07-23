<?php

namespace App\Managers;

use App\Models\Group;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Models\Setting;
use App\Models\WorkReport;
use App\Services\GoogleService;
use App\Services\TelegramService;

class ReceiverManager
{

    public static function updateReceiverData()
    {
        $isChanged = ReceiverManager::readSheet();
        $setting = Setting::getItem(Setting::MAKE_USER_LIST);
        if ($setting->param_value == null || $setting->param_value == 1 || $isChanged) {
            ReceiverManager::writeToSheet();
            $setting->setVal(0);
        }
    }
    public static function readSheet()
    {
        $gooleService = new GoogleService();
        $users = $gooleService->readSheetValues(GoogleService::SPREADSHEET_ID, GoogleService::usersSheet);

        $setting = Setting::getItem(Setting::USER_LIST);

        $encodeData = md5(serialize($users));

        if ($setting->param_value == $encodeData) {
            return 0;
        } else {
            $setting->param_value = $encodeData;
            $setting->save();
        }

        $userTypeColNumber = 3;

        $groupTitles = self::readGroups(isset($users[1]) ? $users[1] : [], $users[0]);

        foreach ($users as $row) {
            if (empty($row[0]) || $row[$userTypeColNumber] == '') {
                continue;
            }
            $user = Receiver::getByUsername($row[0]);
            if (empty($user->id))
                continue;
            $user->user_type = $row[$userTypeColNumber];
            $user->save();

            if (!empty($groupTitles)) {
                foreach ($groupTitles as $colIndex => $group) {
                    if (!empty($row[$colIndex])) {
                        $groupTitles[$colIndex]['users'][] = $user->id;
                        if (empty($groupTitles[$colIndex]['code'])) {
                            $groupTitles[$colIndex]['code'] = $row[$colIndex];
                        }
                    }
                }
            }

        }

        // dd($groupTitles);
        Group::saveItems($groupTitles);

        return 1;
    }

    public static function readGroups(array $row, array $markRow)
    {
        // dd($markRow);
        $groupStartColumn = 8;
        $groupTitles = [];
        if (count($row) >= $groupStartColumn) {
            for ($i = $groupStartColumn; $i < count($row); $i++) {
                if (!empty($row[$i])) {
                    $groupTitles[$i] = [
                        'title' => $row[$i],
                        'users' => [],
                        'code' => null,
                        'isShow' => (isset($markRow[$i]) && $markRow[$i] == 1 ? 1 : 0)
                    ];
                }
            }
        }
        return $groupTitles;
    }


    public static function writeToSheet()
    {
        $all = Receiver::with('groups')->get();
        $firstRow =
            [
                'username',
                'last_name',
                'first_name',
                'user_type',
                'last_answer_time',
                'message_cnt',
                'fullname',
                'contact_phone'
            ];
        
        $markRow = [];
        for ($i=0; $i < count($firstRow) ; $i++) { 
            $markRow[] = '';
        }
        $groups = Group::get();
        foreach ($groups as $group) {
            $firstRow[] = $group->title;
            $markRow[] = ($group->isShow == 1 ? '1' : '');
        }
        $data[] = $markRow;
        $data[] = $firstRow;

        foreach ($all as $user) {
            $row = [
                $user->username,
                $user->lastname ?? '',
                $user->firstname ?? '',
                $user->user_type,
                $user->last_answer_time ? date('Y-m-d H:i:s', strtotime($user->last_answer_time)) : '',
                $user->message_cnt,
                $user->fullname ?? '',
                $user->contact_phone ?? ''
            ];
            foreach ($groups as $group) {
                $inGroups = $user->groups->keyBy('id');
                if ($inGroups->has($group->id)) {
                    $row[] = $group->code;
                } else {
                    $row[] = '';
                }
            }
            $data[] = $row;
        }
        $data = array_values($data);
        $sheet = GoogleService::usersSheet;
        $service = new GoogleService();
        $service->deleteRows($sheet);
        $service->writeValues($sheet, $data,'!A1');
    }

}