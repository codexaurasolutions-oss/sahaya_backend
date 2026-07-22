
<?php
use Illuminate\Support\Facades\Route;

// API routes handled separately in routes/api.php

Route::get('/{any}', function () {
  return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');