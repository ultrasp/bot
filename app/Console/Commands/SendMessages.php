<?php

namespace App\Console\Commands;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Setting;
use App\Services\GoogleService;
use Illuminate\Console\Command;

class SendMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isChanged = MessagePlan::updatePlans();

        $sendingTime = Setting::getItem(Setting::SENDING_CREATE_TIME);
        $isSendingsUpdated = false;
        if ($sendingTime->param_value == null || (!empty($sendingTime->param_value) && date('Y-m-d', strtotime($sendingTime->param_value)) != date('Y-m-d')) || $isChanged) {
            MessageSending::createSendings();
            $isSendingsUpdated = true;
        }
        if ($isSendingsUpdated) {
            $sendingTime->setVal(date('Y-m-d H:i:s'));
        }
        MessageSending::send();
    }
}