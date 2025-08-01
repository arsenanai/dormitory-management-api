<?php

use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('API is running', 200);
});

// Route::get('/mailable-preview', function () {
//     $token = 'exampletoken';
//     $email = 'user@example.com';
//     return (new PasswordResetMail($token, $email))->render();
// });