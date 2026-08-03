<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()
        ->view('cipi::welcome')
        ->header('X-Robots-Tag', 'noindex, nofollow');
});

Route::get('/robots.txt', function () {
    $content = implode("\n", [
        'User-agent: *',
        'Disallow: /',
        '',
    ]);

    return response($content, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
});

Route::get('/docs', function () {
    return view('cipi::docs');
});
