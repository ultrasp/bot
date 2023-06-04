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

    const CHASTOTA_DAILY = 1;
    const CHASTOTA_WORK_DAYS = 2;
    const CHASTOTA_RANGE_DAY = 3;

    const CHASTOTA_ONE_DAY = 4;

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

    public static function saveTemplates($templates)
    {
        $allTemplates = self::getAllByType(self::TYPE_ASK);
        $notRemoveTemplates = [];
        foreach ($templates as $template) {
            $savedDb = $allTemplates->first(function ($dbTemplate) use ($template) {
                return $dbTemplate->template == $template['message']
                    && $dbTemplate->send_minute == $template['time']
                    && $dbTemplate->chastota == $template['chastota'];
                // 'start_at' => $time[$startColumn + 3],
                // 'end_at' => $time[$startColumn + 4],

            });
            if (!empty($savedDb)) {
                $notRemoveTemplates[] = $savedDb->id;
            } else {
                try {
                    $savedDb = self::newItem($template['message'], $template['time'], self::TYPE_ASK, $template['chastota']);
                    if ($savedDb->chastota == self::CHASTOTA_RANGE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $end = Carbon::createFromFormat('d.m.Y', $template['end_at']);
                        $savedDb->setRange($start->format('Y-m-d'), $end->format('Y-m-d'));
                    }
                    if ($savedDb->chastota == self::CHASTOTA_ONE_DAY) {
                        $start = Carbon::createFromFormat('d.m.Y', $template['start_at']);
                        $savedDb->setRange($start->format('Y-m-d'), null);
                    }
                } catch (\Throwable $th) {

                }
            }
            if(!empty($savedDb)){
                $savedDb->attachReceviers($template['groups']);
            }
        }
        foreach ($allTemplates as $dbTemplate) {
            if (!in_array($dbTemplate->id, $notRemoveTemplates)) {
                $dbTemplate->delete();
                MessageSending::removeUnsendedSendings($dbTemplate->id);
            }
        }
    }

    public function attachReceviers(?string $receviers){
        $this->send_groups = $receviers;
        $this->save();
        $this->receivers()->sync([]);
        $this->groups()->sync([]);
        if(!empty($receviers)){
            if(str_starts_with($receviers,'@')){
                $receiver = Receiver::getByUsername(substr($receviers,1));
                $this->receivers()->sync([$receiver->id]);
            }else{
                $receiveGroupCodes = explode(",",$receviers);
                $receiverIds = [];
                foreach($receiveGroupCodes as $groupCode){
                    $group = Group::where('code',$groupCode)->first();
                    if(!empty($group)){
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
        return MessagePlan::withTrashed()
            ->where(['type' => $type])
            ->whereRaw('(deleted_at is null or exists (select 1 from message_sendings s where s.message_plan_id = message_plans.id and send_time is not null and DATE(send_time) >= "' . date('Y-m-01', strtotime($date)) . '" and DATE(send_time) <= "' . date('Y-m-t', strtotime($date)) . '" ))')
            ->orderBy('send_minute')
            ->get();
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


    public function canSend()
    {
        $canSend = false;
        if ($this->chastota == MessagePlan::CHASTOTA_DAILY) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_WORK_DAYS && date('D') != 'Sun') {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_RANGE_DAY && $this->start_at >= date('Y-m-d') && $this->end_at <= date('Y-m-d')) {
            $canSend = true;
        }
        if ($this->chastota == MessagePlan::CHASTOTA_ONE_DAY && $this->start_at == date('Y-m-d')) {
            $canSend = true;
        }
        return $canSend;
    }

    public function canSendReceiver(Receiver $receiver):bool{
        if(empty($this->send_groups)){
            return true;
        }
        $receivers = $this->receivers->keyBy('id'); 
        if(!empty($receivers) && $receivers->has($receiver->id)){
            return true;
        }
        $groups = $this->groups->pluck('id')->toArray();
        $receiverGroups = $receiver->groups->pluck('id')->toArray();
        if(!empty($groups) && !empty(array_intersect($groups,$receiverGroups))){
            return true;
        }
        return false; 
        // $receiver->groups->pluck()
    }
}