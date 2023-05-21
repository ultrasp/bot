<?php

namespace App\Services;

use App\Models\MessagePlan;
use App\Models\Setting;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ClearValuesRequest;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
// https://www.nidup.io/blog/manipulate-google-sheets-in-php-with-api
class GoogleService
{
    private $client;

    private $service;
    const SPREADSHEET_ID = '12iSvm7MCQND6rO_nG3ELr5uuhd3D-x85tPmacy1ZAZ8';
    const readSheet = 'main';
    const usersSheet = 'users';

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

    public function readSheetValues($spreadSheetId,$readSheet){
        $response = $this->service->spreadsheets_values->get($spreadSheetId, $readSheet);
        return $response->getValues();
    }

    public function writeValues($sheet, $rows)
    {
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($rows);
        $range = $sheet . '!A2'; // where the replacement will start, here, first column and second line
        $options = ['valueInputOption' => 'USER_ENTERED'];
        $this->service->spreadsheets_values->update(self::SPREADSHEET_ID, $range, $valueRange, $options);
    }

    public function deleteRows($sheet, $diapazon = null)
    {
        // $range = 'Sheet1!A23:F24'; // the range to clear, the 23th and 24th lines
        $range = $sheet . ($diapazon ? '!' . $diapazon : '');
        $clear = new Google_Service_Sheets_ClearValuesRequest();
        $this->service->spreadsheets_values->clear(self::SPREADSHEET_ID, $range, $clear);
    }

    public function checkExistSheet($sheetName)
    {
        $sheetInfo = $this->service->spreadsheets->get(self::SPREADSHEET_ID);
        $allsheet_info = $sheetInfo['sheets'];
        $idCats = array_column($allsheet_info, 'properties');

        if (!$this->checkSheetArray($idCats, $sheetName)) {
            $this->addNewSheet($sheetName);
        }
    }

    function checkSheetArray(array $myArray, $word)
    {
        foreach ($myArray as $element) {
            if ($element->title == $word) {
                return true;
            }
        }
        return false;
    }

    public function addNewSheet($sheetName)
    {

        $body = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(
            [
                'requests' => [
                    'addSheet' => ['properties' => ['title' => $sheetName]]
                ]
            ]
        );

        $result = $this->service->spreadsheets->batchUpdate(self::SPREADSHEET_ID, $body);
    }

}