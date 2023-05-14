<?php

namespace App\Http\Controllers;

use App\Models\IncomeMessage;
use App\Models\MessagePlan;
use App\Models\MessageSending;
use App\Models\Receiver;
use App\Services\GoogleService;

//https://www.nidup.io/blog/manipulate-google-sheets-in-php-with-api
class GoogleController extends Controller
{

    public function get()
    {
        $service = new GoogleService();
        $service->readValues();
    }

    public function addDailyData()
    {
        MessagePlan::writeToExcelDaily();
        // dd($plans);
    }


}