<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Downloads the Postman API collection. Hitting this route in a browser
 * triggers a file download (Content-Disposition: attachment).
 */
Route::get('/postman-collection', function () {
    return response()->download(
        public_path('downloads/postman_collection.json'),
        'translation-management-service.postman_collection.json',
    );
})->name('postman.collection');
