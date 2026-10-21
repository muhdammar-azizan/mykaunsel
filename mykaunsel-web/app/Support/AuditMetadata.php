<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Cleans metadata before it goes into the audit log: no secrets, no chat/complaint text, bounded size. */
class AuditMetadata
{
    public static function sanitize(array $metadata): array
    {
        return self::clean($metadata, 1);
    }

    private static function clean(array $data, int $depth): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isBlockedKey($key)) {
                continue;
            }

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif ($value instanceof \Stringable) {
                $value = (string) $value;
            }

            if (is_array($value)) {
                if ($depth >= (int) config('audit.metadata_max_depth', 5)) {
                    $out[$key] = '[too deep]';
                } else {
                    $out[$key] = self::clean($value, $depth + 1);
                }
            } elseif (is_string($value)) {
                $out[$key] = Str::limit($value, (int) config('audit.metadata_max_string', 2000), '…');
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
            // objects / resources that are not scalar-like are dropped
        }

        return $out;
    }

    private static function isBlockedKey(string $key): bool
    {
        $key = strtolower($key);
        $segments = preg_split('/[^a-z0-9]+/', $key, -1, PREG_SPLIT_NO_EMPTY);

        foreach ([...config('audit.sensitive_key_patterns', []), ...config('audit.content_key_patterns', [])] as $pattern) {
            // Long patterns match anywhere in the key; short ones ("otp", "text", "body") only as a whole word,
            // so "footprint" or "context" are not caught by accident.
            if (strlen($pattern) >= 5 ? str_contains($key, $pattern) : in_array($pattern, $segments, true)) {
                return true;
            }
        }

        return false;
    }
}
