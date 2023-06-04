<?php

namespace App\Console\Commands;

use App\Managers\MessagePlanManager;
use App\Managers\MessageSendingManager;
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
        $isChanged = MessagePlanManager::updatePlans();
        MessageSendingManager::updateAndSend($isChanged);
    }
}