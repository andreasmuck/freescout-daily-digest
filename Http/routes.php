<?php

Route::group(['middleware' => ['web', 'auth', 'roles'], 'roles' => ['admin']], function () {
    Route::get('/daily-digest/preview', '\Modules\DailyDigest\Http\Controllers\DigestController@preview')
        ->name('dailydigest.preview');
});
