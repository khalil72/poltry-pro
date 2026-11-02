<?php
namespace App\Services;

/**
 * Sends email with PHP's mail(), or writes it to storage/logs/mail.log when MAIL_DRIVER=log.
 * Callers pass fixed subjects and addresses read from the database, never raw request input.
 */
class MailService
{
    public static function usesLog(): bool
    {
        return config('app.mail_driver') === 'log';
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $to = str_replace(["\r", "\n"], '', $to);
        if (self::usesLog()) {
            $entry = sprintf("[%s]\nTo: %s\nSubject: %s\n\n%s\n%s\n", date('c'), $to, $subject, $body, str_repeat('-', 60));
            return file_put_contents(BASE_PATH . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
        }
        $headers = 'From: ' . config('app.mail_from') . "\r\nContent-Type: text/plain; charset=UTF-8";
        $sent = @mail($to, $subject, $body, $headers);
        if (!$sent) {
            error_log(sprintf('[%s] Could not send "%s" to %s. Check the server mail settings.', date('c'), $subject, $to));
        }
        return $sent;
    }
}
