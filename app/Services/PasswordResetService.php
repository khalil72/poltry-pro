<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

/**
 * Forgotten passwords. Only a hash of each token is stored, so a leaked database cannot be used
 * to reset anyone's password. Tokens are single-use and expire.
 */
class PasswordResetService
{
    private const MAX_PER_EMAIL = 3;
    private const MAX_PER_IP = 10;

    /** Returns the link when one was created, for local testing. Callers must never show it on a live site. */
    public static function request(string $email, string $ip): ?string
    {
        $now = date('Y-m-d H:i:s');
        $hourAgo = date('Y-m-d H:i:s', time() - 3600);
        DB::query('DELETE FROM password_resets WHERE requested_at<?', [date('Y-m-d H:i:s', time() - 86400)]);

        $byEmail = (int) DB::value('SELECT COUNT(*) FROM password_resets WHERE email=? AND requested_at>?', [$email, $hourAgo]);
        $byIp = (int) DB::value('SELECT COUNT(*) FROM password_resets WHERE ip_address=? AND requested_at>?', [$ip, $hourAgo]);
        if ($byEmail >= self::MAX_PER_EMAIL || $byIp >= self::MAX_PER_IP) {
            return null;
        }

        $minutes = (int) config('app.password_reset_minutes');
        $expires = date('Y-m-d H:i:s', time() + $minutes * 60);
        $row = ['email' => $email, 'ip_address' => $ip, 'requested_at' => $now, 'expires_at' => $expires];
        $user = DB::one("SELECT id, name FROM users WHERE email=? AND status='active' AND deleted_at IS NULL", [$email]);
        if (!$user) {
            DB::insert('password_resets', $row);
            return null;
        }

        $base = self::baseUrl();
        if ($base === null) {
            error_log('[' . date('c') . '] Password reset skipped: set APP_URL in .env.');
            return null;
        }
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($user, $row, $token, $now) {
            // A new request cancels any earlier link.
            DB::query('UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL', [$now, $user['id']]);
            DB::insert('password_resets', [...$row, 'user_id' => $user['id'], 'token_hash' => hash('sha256', $token)]);
        });
        $link = $base . '/admin/reset-password?token=' . $token;
        $company = setting('company_name', config('app.name'));
        MailService::send($email, "Reset your $company password", "Hello {$user['name']},\n\nUse this link to choose a new password:\n$link\n\nThe link works once and expires in $minutes minutes.\nIf you did not ask for this, you can ignore this email and your password stays the same.\n");
        Activity::log('password_reset_request', 'user', (int) $user['id']);
        return $link;
    }

    /**
     * The address used in emailed links. It comes from APP_URL because the request's Host header can be
     * forged, which would send a victim's reset link to an attacker's site.
     */
    private static function baseUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/');
        if ($url !== '') {
            return $url;
        }
        return config('app.env') === 'production' ? null : rtrim(absolute_url(''), '/');
    }

    public static function isValid(string $token): bool
    {
        return self::find($token) !== null;
    }

    private static function find(string $token, bool $lock = false): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::one(
            "SELECT r.id, r.user_id, r.email FROM password_resets r JOIN users u ON u.id=r.user_id
             WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>? AND u.status='active' AND u.deleted_at IS NULL" . ($lock ? ' FOR UPDATE' : ''),
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        );
    }

    public static function reset(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            $r = self::find($token, true);
            if (!$r) {
                throw new BusinessRuleException('This reset link has expired or was already used. Request a new one.');
            }
            $now = date('Y-m-d H:i:s');
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_changed_at' => $now], (int) $r['user_id']);
            DB::update('password_resets', ['used_at' => $now], (int) $r['id']);
            // Proving ownership of the email lifts any sign-in lockout.
            DB::query('DELETE FROM login_attempts WHERE email=?', [$r['email']]);
            Activity::log('password_reset', 'user', (int) $r['user_id']);
        });
    }
}
