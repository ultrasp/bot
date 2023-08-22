<?php

namespace App\Http\Controllers;

use App\Managers\TargetManager;
use Illuminate\Http\Request;

class TargetController extends Controller
{

    public function storeLid(Request $request)
    {
        $name = $request->name;
        $phone = $request->phone;
        TargetManager::writeLid($name,$phone);

    }

}