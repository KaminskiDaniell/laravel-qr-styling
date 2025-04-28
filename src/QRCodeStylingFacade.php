<?php

namespace kaminskidaniell\LaravelQRStyling;

use Illuminate\Support\Facades\Facade;

class QRCodeStylingFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'qr-styling';
    }
}
