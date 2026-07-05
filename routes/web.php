<?php

use Illuminate\Support\Facades\Route;

// The site root: hand visitors to the web app when one is deployed
// (public/app/), otherwise to the admin panel.
Route::get('/', function () {
    return is_file(public_path('app/index.html'))
        ? redirect('/app/')
        : redirect('/admin');
});
