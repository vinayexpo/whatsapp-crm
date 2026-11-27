<?php

namespace App\Services\Commerce;

class CommerceMoney
{
    public static function format(int $minor, string $currency = 'INR'): string
    {
        return $currency.' '.number_format($minor / 100, 2, '.', ',');
    }
}
