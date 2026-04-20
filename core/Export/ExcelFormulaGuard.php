<?php

/**
 * Защита от Excel/CSV formula injection.
 * Если значение начинается с опасного символа — префиксуем апострофом.
 */
final class ExcelFormulaGuard
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function sanitize(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        if (in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            return "'" . $value;
        }
        return $value;
    }
}
