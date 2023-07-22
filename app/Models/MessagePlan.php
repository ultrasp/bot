<?php

namespace App\Models;

use App\Services\GoogleService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessagePlan extends Model
{
    use SoftDeletes;
    const TYPE_ASK = 1;
    const TYPE_SYSTEM = 2;
    const TYPE_SYSTEM_CALLBACK = 4;
    const TYPE_BOT_ANSWER = 3;
    const TYPE_CUSTOM_CALLBACK = 5;

    const CHASTOTA_DAILY = 1;
    const CHASTOTA_WORK_DAYS = 2;
    const CHASTOTA_RANGE_DAY = 3;

    const CHASTOTA_ONE_DAY = 4;

    const PARENT_ACTION_TYPE_RESPONCED = 1;
    const PARENT_ACTION_TYPE_NOT_ANSWER = 2;

    protected $fillable = ['template'];

    public function receivers(): MorphToMany
    {
        return $this->morphedByMany(Receiver::class, 'message_plan_receivable');
    }

    public function groups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'message_plan_receivable');
    }

    public static function newItem($text, $sendMinute, $type, $chastota = 0, $hide = 0)
    {
        $item = new self();
        $item->template = $text;
        $item->send_minute = $sendMinute;
        $item->type = $type;
        $item->chastota = $chastota;
        $item->hide = $hide;
        $item->save();
        return $item;
    }

    public static function newCommandItem($text, $type, $hide = 0)
    {
        $item = self::firstOrNew(['template' => $text]);
        $item->send_minute = 0;
        $item->type = $type;
        $item->chastota = 0;
        $item->hide = $hide;
        $item->save();
        return $item;
    }

    public static function getAllByType($type)
    {
        return self::where(['type' => $type])->get();
    }


    public function attachReceviers(?string $receviers)
    {
        $this->send_groups = $receviers;
        $this->save();
        $this->receivers()->sync([]);
        $this->groups()->sync([]);
        if (!empty($receviers)) {
            if (str_starts_with($receviers, '@')) {
                $receiver = Receiver::getByUsername(substr($receviers, 1));
                if(!empty($receiver) && $receiver->id){
                    // dd($receiver->id);
                    $this->receivers()->sync([$receiver->id]);
                }
            } else {
                $receiveGroupCodes = explode(",", $receviers);
                $receiverIds = [];
                foreach ($receiveGroupCodes as $groupCode) {
                    $group = Group::where('code', $groupCode)->first();
                    if (!empty($group)) {
                        $receiverIds[] = $group->id;
                    }
                }
                $this->groups()->sync($receiverIds);
            }
        }
    }

    public function setRange($startAt, $endAt)
    {
        $this->start_at = $startAt;
        $this->end_at = $endAt;
        $this->save();
    }

    public static function getDailyInfo($date)
    {
        return MessagePlan::withTrashed()
            ->where(['type' => self::TYPE_ASK])
            ->whereRaw('(deleted_at is null or (deleted_at is not null and exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) = "' . $date . '")))')
            ->orderBy('send_minute')
            ->get();
    }

    public static function getMonthlyInfo($date, $type = self::TYPE_ASK)
    {
        if (empty($type)) {
            $type = self::TYPE_ASK;
        }
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd = date('Y-m-t', strtotime($date));
        $query = MessagePlan::withTrashed()
            ->where(['type' => $type])
            ->whereRaw('(deleted_at is null or exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) >= "' . $monthStart . '" and DATE(send_time) <= "' . $monthEnd . '" ))')
            ->orderBy('send_minute');
        if($type == self::TYPE_ASK){
            $query->whereRaw(
                '(chastota in (1,2) or (chastota = 3 and start_at <= "'.$monthEnd.'" and  end_at >= "'.$monthStart.'") or (chastota = 4 and start_at between "'.$monthStart.'" and "'.$monthEnd.'" )) '
            );
        }
        return $query->get();
    }

    public function covertToString()
    {
        $hour = intval($this->send_minute / 60);
        return $hour . ':' . ($this->send_minute - $hour * 60);
    }

    public static function getSystemAsk($command)
    {
        return self::where(['template' => $command])->whereIn('type', [self::TYPE_SYSTEM, self::TYPE_SYSTEM_CALLBACK])->first();
    }

    public static function makeSystemAsk()
    {
        $commands = TelegramService::getAllCommands();
        foreach ($commands as $command) {
            $dbommand = '/' . $command;
            $mplan = self::getSystemAsk($dbommand);
            if (empty($mplan)) {
                self::newCommandItem(
                    $dbommand,
                    $command == TelegramService::COMMAND_REGISTER ? self::TYPE_SYSTEM_CALLBACK : self::TYPE_SYSTEM,
                    $command == TelegramService::COMMAND_TOTAL_WORK_TIME ? 1 : 0
                );
            }
        }
    }


    public function canSend($date = null)
    {
        if(empty($date)){
            $date = date('Y-m-d');
        }
        $weekDay = date('D',strtotime($date));
        $canSend = false;
        if ($this->chastota == MessagePlan::CHASTOTA_DAILY) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_WORK_DAYS && $weekDay != 'Sun') {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_RANGE_DAY && $this->start_at >= $date && $this->end_at <= $date) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_ONE_DAY && $this->start_at == $date) {
            $canSend = true;
        }
        return $canSend;
    }

    public function canSendReceiver(Receiver $receiver): bool
    {
        if (empty($this->send_groups)) {
            return true;
        }
        $receivers = $this->receivers->keyBy('id');
        if (!empty($receivers) && $receivers->has($receiver->id)) {
            return true;
        }
        $groups = $this->groups->pluck('id')->toArray();
        $receiverGroups = $receiver->groups->pluck('id')->toArray();
        if (!empty($groups) && !empty(array_intersect($groups, $receiverGroups))) {
            return true;
        }
        return false;
    }

    public function getCallbackMessage(string $command, string $actionType)
    {
        $command = self::where([
            'template' => $command
        ])->first();

        $messages = self::where([
            'parent_id' => $command->id,
            'parent_action_type' => $actionType
        ])->get();

        return $messages;
    }
}