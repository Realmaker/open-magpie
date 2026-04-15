<?php

declare(strict_types=1);

namespace App\Helpers;

class QueryHelper
{
    /**
     * Escape LIKE wildcards in user input to prevent unintended broad matches.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
