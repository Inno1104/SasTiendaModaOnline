<?php

namespace App\Support;

final class SaleLimits
{
    /** Maximum positive value supported by DECIMAL(10, 2). */
    public const MAX_MONEY = 99_999_999.99;

    public const MAX_ITEMS = 100;

    public const MAX_ITEM_QUANTITY = 9_999;

    private function __construct() {}
}
