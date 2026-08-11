<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Panel list filters come straight from the query string, where a crafted
 * ?q[]=x or ?from=garbage must mean "no filter", not a 500.
 */
trait ReadsQueryFilters
{
    /** Drop array-shaped query values so views echoing them cannot crash. */
    private function stripArrayQuery(Request $request): void
    {
        $request->query->replace(
            array_filter($request->query->all(), 'is_scalar'),
        );
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function queryDate(Request $request, string $key): ?Carbon
    {
        $value = $this->queryString($request, $key);

        try {
            return $value === null ? null : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
