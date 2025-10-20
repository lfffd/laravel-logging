<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DemoController;

Route::get('/', [DemoController::class, 'index'])->name('home');

Route::get('/demo/log', [DemoController::class, 'testLog'])->name('demo.log');

Route::get('/demo/error', [DemoController::class, 'testError'])->name('demo.error');

Route::get('/demo/context', [DemoController::class, 'testContext'])->name('demo.context');

Route::get('/demo/multiple', [DemoController::class, 'testMultiple'])->name('demo.multiple');

Route::get('/demo/json', [DemoController::class, 'testJson'])->name('demo.json');