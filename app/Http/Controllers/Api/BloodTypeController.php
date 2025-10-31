<?php

namespace App\Http\Controllers\Api;

use App\Models\BloodType;
use App\Http\Controllers\Controller;

class BloodTypeController extends Controller
{
    public function index()
    {
        $bloodTypes = BloodType::all();
        
        return response()->json([
            'data' => $bloodTypes
        ]);
    }
}
