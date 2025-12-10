<?php
/**
 * Reusable validation helpers for templates.
 */

if (!class_exists('TplValidationException')) {
    class TplValidationException extends InvalidArgumentException
    {
    }
}

if (!function_exists('tpl_validation_label')) {
    function tpl_validation_label(array $options, string $fallback): string
    {
        return $options['field'] ?? $fallback;
    }
}

if (!function_exists('tpl_validate_string')) {
    function tpl_validate_string($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'Değer');
        $allowEmpty = $options['allow_empty'] ?? false;
        $stripTags = $options['strip_tags'] ?? true;
        $value = is_string($value) ? $value : (string)$value;
        $value = trim($value);
        if ($stripTags) {
            $value = strip_tags($value);
        }

        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }

        $length = mb_strlen($value);
        if (isset($options['min']) && $length < (int)$options['min']) {
            throw new TplValidationException("$label en az {$options['min']} karakter olmalıdır.");
        }
        if (isset($options['max']) && $length > (int)$options['max']) {
            throw new TplValidationException("$label en fazla {$options['max']} karakter olabilir.");
        }
        if (isset($options['pattern']) && !preg_match($options['pattern'], $value)) {
            $message = $options['pattern_message'] ?? "$label geçersiz formattadır.";
            throw new TplValidationException($message);
        }

        return $value;
    }
}

if (!function_exists('tpl_validate_int')) {
    function tpl_validate_int($value, array $options = [])
    {
        $label = tpl_validation_label($options, 'Değer');
        $allowEmpty = $options['allow_empty'] ?? false;
        if ($value === null || $value === '') {
            if ($allowEmpty) {
                return null;
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new TplValidationException("$label geçerli bir sayı olmalıdır.");
        }
        $intValue = (int)$value;
        if (isset($options['min']) && $intValue < (int)$options['min']) {
            throw new TplValidationException("$label en az {$options['min']} olabilir.");
        }
        if (isset($options['max']) && $intValue > (int)$options['max']) {
            throw new TplValidationException("$label en fazla {$options['max']} olabilir.");
        }
        return $intValue;
    }
}

if (!function_exists('tpl_validate_float')) {
    function tpl_validate_float($value, array $options = [])
    {
        $label = tpl_validation_label($options, 'Değer');
        $allowEmpty = $options['allow_empty'] ?? false;
        if ($value === null || $value === '') {
            if ($allowEmpty) {
                return null;
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if (!is_numeric($value)) {
            throw new TplValidationException("$label geçerli bir sayı olmalıdır.");
        }
        $floatValue = (float)$value;
        if (isset($options['min']) && $floatValue < (float)$options['min']) {
            throw new TplValidationException("$label en az {$options['min']} olabilir.");
        }
        if (isset($options['max']) && $floatValue > (float)$options['max']) {
            throw new TplValidationException("$label en fazla {$options['max']} olabilir.");
        }
        return $floatValue;
    }
}

if (!function_exists('tpl_validate_email')) {
    function tpl_validate_email($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'E-posta');
        $allowEmpty = $options['allow_empty'] ?? false;
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new TplValidationException("$label geçerli bir e-posta adresi olmalıdır.");
        }
        return $value;
    }
}

if (!function_exists('tpl_validate_url')) {
    function tpl_validate_url($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'URL');
        $allowEmpty = $options['allow_empty'] ?? false;
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new TplValidationException("$label geçerli bir URL olmalıdır.");
        }
        return $value;
    }
}

if (!function_exists('tpl_validate_date')) {
    function tpl_validate_date($value, string $format = 'Y-m-d', array $options = []): string
    {
        $label = tpl_validation_label($options, 'Tarih');
        $allowEmpty = $options['allow_empty'] ?? false;
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        $dt = DateTime::createFromFormat($format, $value);
        if (!$dt || $dt->format($format) !== $value) {
            throw new TplValidationException("$label geçerli bir tarih olmalıdır.");
        }
        return $value;
    }
}

if (!function_exists('tpl_validate_time')) {
    function tpl_validate_time($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'Saat');
        $allowEmpty = $options['allow_empty'] ?? false;
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if (!preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $value)) {
            throw new TplValidationException("$label hh:mm formatında olmalıdır.");
        }
        return $value;
    }
}

if (!function_exists('tpl_validate_enum')) {
    function tpl_validate_enum($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'Değer');
        $allowed = $options['allowed'] ?? [];
        $allowEmpty = $options['allow_empty'] ?? false;
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return $options['default'] ?? '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if ($allowed && !in_array($value, $allowed, true)) {
            throw new TplValidationException("$label için geçersiz değer: $value");
        }
        return $value;
    }
}

if (!function_exists('tpl_validate_phone')) {
    function tpl_validate_phone($value, array $options = []): string
    {
        $label = tpl_validation_label($options, 'Telefon');
        $allowEmpty = $options['allow_empty'] ?? false;
        $pattern = $options['pattern'] ?? '/^[0-9+\-\s]{7,20}$/';
        $patternMessage = $options['pattern_message'] ?? "$label geçerli bir telefon numarası olmalıdır.";
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }
            throw new TplValidationException("$label alanı zorunludur.");
        }
        if (!preg_match($pattern, $value)) {
            throw new TplValidationException($patternMessage);
        }
        return $value;
    }
}

if (!function_exists('tpl_bool_flag')) {
    function tpl_bool_flag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}

if (!function_exists('tpl_whitelist_value')) {
    function tpl_whitelist_value($value, array $allowed, string $fallback, string $label = 'Değer'): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return $fallback;
        }
        if (!in_array($value, $allowed, true)) {
            return $fallback;
        }
        return $value;
    }
}

if (!function_exists('tpl_filter_slug')) {
    function tpl_filter_slug($value, string $default = '', array $options = []): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return $default;
        }

        $pattern = $options['pattern'] ?? '/^[a-z0-9_\-]+$/i';
        if (!preg_match($pattern, $value)) {
            return $default;
        }

        if (!empty($options['lowercase'])) {
            $value = strtolower($value);
        }

        return $value;
    }
}

