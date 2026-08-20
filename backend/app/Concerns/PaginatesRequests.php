<?php

namespace App\Concerns;

use Illuminate\Http\Request;

trait PaginatesRequests
{
    protected function perPageFrom(Request $request, int $default = 20, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}
