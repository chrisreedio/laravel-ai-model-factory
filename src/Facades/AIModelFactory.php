<?php

namespace ChrisReedIO\AIModelFactory\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ChrisReedIO\AIModelFactory\AIModelFactory
 */
class AIModelFactory extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ChrisReedIO\AIModelFactory\AIModelFactory::class;
    }
}
