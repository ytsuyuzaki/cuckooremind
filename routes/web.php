<?php

use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\TopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('setup', [SetupController::class, 'create'])->name('setup.create');
Route::post('setup', [SetupController::class, 'store'])->name('setup.store');

Route::get('setup/user', [SetupController::class, 'userCreate'])->name('setup.user.create');
Route::post('setup/user', [SetupController::class, 'userStore'])->name('setup.user.store');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/', [TopController::class, 'index'])->name('top.index');
    Route::get('settings', [SettingController::class, 'edit'])->name('setting.edit');
    Route::post('settings', [SettingController::class, 'update'])->name('setting.update');

    Route::get('reminders/json/export', [ReminderController::class, 'export'])->name('reminders.export');
    Route::post('reminders/json/import', [ReminderController::class, 'import'])->name('reminders.import');
    Route::resource('reminders', ReminderController::class);

    Route::middleware('system-admin')->prefix('system/updates')->name('system-updates.')->group(function () {
        Route::get('/', [SystemUpdateController::class, 'index'])->name('index');
        Route::post('refresh', [SystemUpdateController::class, 'refresh'])->middleware('throttle:6,1')->name('refresh');
        Route::get('status', [SystemUpdateController::class, 'status'])->name('status');
        Route::post('/', [SystemUpdateController::class, 'update'])
            ->middleware('throttle:2,1')
            ->name('update');
    });
});
