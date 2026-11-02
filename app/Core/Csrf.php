<?php
namespace App\Core;

class Csrf
{
    private const USED_KEEP = 300;

    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Includes a one-time token so a double click or a replayed form cannot post twice. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">'
            . '<input type="hidden" name="_once" value="' . bin2hex(random_bytes(12)) . '">';
    }

    public static function verify(): void
    {
        $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            throw new BusinessRuleException('This page expired. Reload it and try again.');
        }
        $once = $_POST['_once'] ?? null;
        if (is_string($once) && $once !== '') {
            $used = $_SESSION['_used_once'] ?? [];
            if (in_array($once, $used, true)) {
                throw new BusinessRuleException('This form was already submitted, so it was not saved again.');
            }
            $used[] = $once;
            $_SESSION['_used_once'] = array_slice($used, -self::USED_KEEP);
        }
    }
}
