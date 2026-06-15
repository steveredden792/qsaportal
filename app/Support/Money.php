<?php

namespace App\Support;

class Money
{
    public static function format(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }
}
