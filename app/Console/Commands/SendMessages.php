<?php

namespace App\Console\Commands;

use App\Models\MessageSending;
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
        echo date('Y-m-d H:i:s');
        if( date( 'H') == 0 && date( 'i') == 0) {
            MessageSending::createSendings();
        }
        if(date( 'i') == 0){
            $service = new GoogleService();
            $service->readValues();
        }
        MessageSending::send();
    }
}
