<?php

namespace App\Console\Commands;

use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Setting;
use App\Services\GoogleService;
use Illuminate\Console\Command;

class MakeReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:report';

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
        $setting = Setting::getItem(Setting::MAKE_REPORT);
        if($setting->param_value == null || $setting->param_value == 1){
            MessagePlan::writeToExcelDaily();
            $setting->setVal(0);
        }
    }
}