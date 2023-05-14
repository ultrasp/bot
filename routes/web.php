<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test','TelegramController@send');

Route::post('/listener','TelegramController@listener');

Route::get('/setcert','TelegramController@setCert');
Route::get('/read','TelegramController@getIncomes');

Route::get('/google','GoogleController@get');

//every day
Route::get('/make','MessageController@makeSendings');

//every minute
Route::get('/send','MessageController@sendQuestion');

//
Route::get('/cron','CronController@run');
Route::get('/daily','GoogleController@addDailyData');
