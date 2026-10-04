<?php

use App\Http\Controllers\Web\VisitorWebActionController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('visitors/{visitor}/action/{action}', [VisitorWebActionController::class, 'handle'])
    ->name('visitor.action')
    ->whereIn('action', ['approve', 'reject']);

