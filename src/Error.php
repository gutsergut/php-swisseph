<?php

namespace Swisseph;

/**
 * Unified error codes and helpers for the PHP port.
 */
final class ErrorCodes
{
    public const OK = 0;
    public const INVALID_CALENDAR = 1001;
    public const INVALID_DATE = 1002;
    public const INVALID_ARG = 1003;
    public const OUT_OF_RANGE = 1004;
    public const UNSUPPORTED = 1005;
    public const INTERNAL = 1999;

    /**
     * Short name for the code.
     */
    public static function name(int $code): string
    {
        switch ($code) {
            case self::OK: return 'OK';
            case self::INVALID_CALENDAR: return 'INVALID_CALENDAR';
            case self::INVALID_DATE: return 'INVALID_DATE';
            case self::INVALID_ARG: return 'INVALID_ARG';
            case self::OUT_OF_RANGE: return 'OUT_OF_RANGE';
            case self::UNSUPPORTED: return 'UNSUPPORTED';
            case self::INTERNAL: return 'INTERNAL';
            default: return 'UNKNOWN';
        }
    }

    /**
     * Human-readable message for the code (without details).
     */
    public static function message(int $code): string
    {
        switch ($code) {
            case self::OK: return 'No error';
            case self::INVALID_CALENDAR: return 'Invalid calendar flag (use 0=Julian or 1=Gregorian)';
            case self::INVALID_DATE: return 'Invalid date components';
            case self::INVALID_ARG: return 'Invalid argument';
            case self::OUT_OF_RANGE: return 'Value out of supported range';
            case self::UNSUPPORTED: return 'Operation not supported in this port';
            case self::INTERNAL: return 'Internal error';
            default: return 'Unknown error';
        }
    }

    /**
     * Compose a standard error string: "E<code> <NAME>: <message>[ - <details>]".
     */
    public static function compose(int $code, string $details = ''): string
    {
        $name = self::name($code);
        $msg = self::message($code);
        $base = "E{$code} {$name}: {$msg}";
        return $details !== '' ? $base . ' - ' . $details : $base;
    }
}
