<?php

namespace MarceliTo\Wiretap\Facades;

use Illuminate\Support\Facades\Facade;

class Wiretap extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'wiretap';
    }
}
