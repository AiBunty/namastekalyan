<?php

declare(strict_types=1);

namespace NK\Support;

class Validator
{
    /** 10-digit phone number. */
    public static function phone(string $phone): bool
    {
        return (bool) preg_match('/^\d{10}$/', $phone);
    }

    /** Non-empty after trim. */
    public static function nonEmpty(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    /** String meets minimum length. */
    public static function minLength(string $value, int $min): bool
    {
        $len = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        return $len >= $min;
    }

    /** Value is in an allowed list (strict). */
    public static function inArray(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /** Positive integer (supports string numerics). */
    public static function isPositiveInt(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }
        return ctype_digit((string) $value) && (int) $value > 0;
    }

    /** Valid email address. */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize a string to digits only and optionally enforce length.
     */
    public static function digitsOnly(string $value, int $maxLen = 0): string
    {
        $digits = preg_replace('/\D/', '', $value);
        return $maxLen > 0 ? substr($digits, 0, $maxLen) : $digits;
    }
}
