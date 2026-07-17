<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Scan. Pack. Ship.');
})->purpose('Motivational quote');
