<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

/**
 * Type-safe conversion helpers for mixed values
 */
class TypeHelper
{
    /**
     * Safely convert mixed value to string
     *
     * @param mixed $value
     * @param string $default
     * @return string
     */
    public static function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value) || is_bool($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return $default;
    }

    /**
     * Safely convert mixed value to int
     *
     * @param mixed $value
     * @param int $default
     * @return int
     */
    public static function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $default;
    }

    /**
     * Safely convert mixed value to float
     *
     * @param mixed $value
     * @param float $default
     * @return float
     */
    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        return $default;
    }

    /**
     * Safely convert mixed value to array
     *
     * @param mixed $value
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public static function toArray(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Safely convert mixed value to valid Monolog level name
     *
     * @param mixed $value
     * @param 'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency' $default
     * @return 'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'
     */
    public static function toLogLevel(mixed $value, string $default = 'Debug'): string
    {
        $validLevels = [
            'DEBUG' => 'Debug',
            'INFO' => 'Info',
            'NOTICE' => 'Notice',
            'WARNING' => 'Warning',
            'ERROR' => 'Error',
            'CRITICAL' => 'Critical',
            'ALERT' => 'Alert',
            'EMERGENCY' => 'Emergency',
        ];

        if (!is_string($value)) {
            $value = self::toString($value);
        }

        $valueUpper = strtoupper($value);

        if (isset($validLevels[$valueUpper])) {
            /** @var 'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency' */
            return $validLevels[$valueUpper];
        }

        /** @var 'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency' */
        return $default;
    }

    /**
     * Safely convert mixed value to list of strings
     *
     * @param mixed $value
     * @return list<string>
     */
    public static function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $result[] = self::toString($item);
        }

        return $result;
    }
}
