<?php
declare(strict_types=1);

namespace Matatirosoln\SqlToOdata\Support;

class ValueCaster
{
    public static function cast(string $raw): mixed
    {
        $raw = trim($raw);

        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            return stripslashes(substr($raw, 1, -1));
        }

        if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
            return stripslashes(substr($raw, 1, -1));
        }

        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return match (strtolower($raw)) {
            'null'  => null,
            'true'  => true,
            'false' => false,
            default => $raw,
        };
    }
}
