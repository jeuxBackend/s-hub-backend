<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthenticationController;


Route::get('/', function () {
    return view('welcome');
});
