<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BloodTypeController extends Controller
{
    public function index()
    {
        $bloodTypes = DB::table('blood_types')->get();
        
        return response()->json([
            'data' => $bloodTypes
        ]);
    }
}
