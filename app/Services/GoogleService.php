<?php

namespace App\Services;

use App\Models\MessagePlan;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ClearValuesRequest;

class GoogleService
{
    private $client;

    private $service;
    const SPREADSHEET_ID = '12iSvm7MCQND6rO_nG3ELr5uuhd3D-x85tPmacy1ZAZ8';
    const readSheet = 'main';

    function __construct()
    {
        $this->client = new Google_Client();
        $this->setClientParams();
        $this->service = new Google_Service_Sheets($this->client);
    }

    private function setClientParams()
    {
        $this->client->setApplicationName('Google Sheets API');
        $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $this->client->setAccessType('offline');
        $this->loadCredential();
    }
    private function loadCredential()
    {
        $path = app_path('data/credentials.json');
        $this->client->setAuthConfig($path);

    }

    public function readValues()
    {
        $startColumn = 1;
        $response = $this->service->spreadsheets_values->get(self::SPREADSHEET_ID, self::readSheet);
        $times = $response->getValues();

        $templates = [];
        foreach ($times as $key => $time) {
            if ($key == 0)
                continue;
            $timeData = explode(':', $time[$startColumn]);
            $minuteFromMidnight = $timeData[0] * 60 + $timeData[1];

            $message = $time[$startColumn + 1];
            if (!empty($message)) {
                $templates[] = [
                    'message' => $message,
                    'time' => $minuteFromMidnight
                ];

            }
        }
        if(!empty($templates)){
            MessagePlan::saveTemplates($templates);
        }
    }

    public function writeValues()
    {
        $newRow = [
            '456740',
            'Hellboy',
            'https://image.tmdb.org/t/p/w500/bk8LyaMqUtaQ9hUShuvFznQYQKR.jpg',
            "Hellboy comes to England, where he must defeat Nimue, Merlin's consort and the Blood Queen. But their battle will bring about the end of the world, a fate he desperately tries to turn away.",
            '1554944400',
            'Fantasy, Action'
        ];
        $rows = [$newRow]; // you can append several rows at once
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($rows);
        $options = ['valueInputOption' => 'USER_ENTERED'];
        $this->service->spreadsheets_values->append(self::SPREADSHEET_ID, self::readSheet, $valueRange, $options);
    }

    public function deleteRows()
    {
        $range = 'Sheet1!A23:F24'; // the range to clear, the 23th and 24th lines
        $clear = new Google_Service_Sheets_ClearValuesRequest();
        $this->service->spreadsheets_values->clear(self::SPREADSHEET_ID, self::readSheet, $clear);
    }
}