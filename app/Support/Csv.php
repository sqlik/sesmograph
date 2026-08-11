<?php

namespace App\Support;

class Csv
{
    /**
     * Neutralize spreadsheet formula injection: Excel and Sheets execute
     * cells starting with = + - @ or a tab, so prefix them with a quote.
     */
    public static function sanitize(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
