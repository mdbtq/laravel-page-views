<?php

use Illuminate\Support\Facades\Artisan;
use Mdbtq\PageViews\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

/**
 * Run an artisan command and return its raw output, for asserting on JSON.
 */
function artisanOutput(string $command): string
{
    Artisan::call($command);

    return Artisan::output();
}
