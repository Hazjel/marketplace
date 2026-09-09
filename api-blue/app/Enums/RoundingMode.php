<?php

namespace App\Enums;

/**
 * Rounding strategy for App\ValueObjects\Money::percentage().
 *
 * Only the one mode the domain needs today. Add cases (HALF_EVEN, …)
 * when a real calculation calls for them, not speculatively.
 */
enum RoundingMode
{
    /**
     * Ties away from zero, matching PHP's PHP_ROUND_HALF_UP.
     *
     *   1.5 -> 2      -1.5 -> -2
     *   1.4 -> 1      -1.4 -> -1
     */
    case HALF_UP;
}
