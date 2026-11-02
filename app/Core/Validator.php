<?php
namespace App\Core;

/**
 * Rules: required, email, url, phone, max:n, numeric, int, min:n, gt:n, date, month, in:a,b,
 * unique:table,column[,ignoreId]
 */
class Validator
{
    /** Throws ValidationException, or returns the input limited to the listed fields. */
    public static function check(array $input, array $rules, array $labels = []): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleset) {
            $rs = is_array($ruleset) ? $ruleset : array_filter(explode('|', $ruleset));
            $v = $input[$field] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            if (is_array($v)) {
                $errors[$field] = 'Invalid value.';
                continue;
            }
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $field)));
            $empty = $v === null || $v === '';
            foreach ($rs as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'required') {
                    if ($empty) {
                        $errors[$field] = "$label is required.";
                        break;
                    }
                    continue;
                }
                if ($empty) {
                    continue;
                }
                $err = self::rule($name, $arg, $v, $label);
                if ($err) {
                    $errors[$field] = $err;
                    break;
                }
            }
            $clean[$field] = $empty ? null : $v;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    private static function rule(string $name, ?string $arg, mixed $v, string $label): ?string
    {
        switch ($name) {
            case 'email':
                return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.";
            case 'url':
                return filter_var($v, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $v) ? null : "$label must be a full web address starting with http:// or https://.";
            case 'phone':
                return preg_match('/^[0-9+() .\-]{5,40}$/', $v) ? null : "$label must be a valid phone number.";
            case 'max':
                return mb_strlen((string) $v) <= (int) $arg ? null : "$label must be $arg characters or fewer.";
            case 'numeric':
                return is_numeric($v) ? null : "$label must be a number.";
            case 'int':
                return filter_var($v, FILTER_VALIDATE_INT) !== false ? null : "$label must be a whole number.";
            case 'min':
                return is_numeric($v) && $v >= (float) $arg ? null : "$label must be at least $arg.";
            case 'gt':
                return is_numeric($v) && $v > (float) $arg ? null : "$label must be greater than $arg.";
            case 'date':
                $d = \DateTime::createFromFormat('Y-m-d', (string) $v);
                return $d && $d->format('Y-m-d') === $v ? null : "$label must be a valid date.";
            case 'month':
                return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ? null : "$label must be a month like 2026-05.";
            case 'in':
                return in_array((string) $v, explode(',', (string) $arg), true) ? null : "$label has an invalid value.";
            case 'unique':
                [$table, $col, $ignore] = array_pad(explode(',', (string) $arg), 3, null);
                $sql = "SELECT COUNT(*) FROM `$table` WHERE `$col`=?";
                $p = [$v];
                if ($ignore) {
                    $sql .= ' AND id<>?';
                    $p[] = $ignore;
                }
                return Database::value($sql, $p) ? "This $label is already in use." : null;
        }
        throw new \LogicException("Unknown validation rule: $name");
    }
}
