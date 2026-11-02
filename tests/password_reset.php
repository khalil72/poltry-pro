<?php
// Forgotten-password checks over HTTP. Uses a temporary user, removed at the end.
// Usage: php tests/password_reset.php [base-url]            with APP_ENV=local
//        php tests/password_reset.php [base-url] --live     with APP_ENV=production, APP_DEBUG=false, MAIL_DRIVER=log
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database as DB;

$live = in_array('--live', $argv, true);
$base = rtrim(($argv[1] ?? '') && !str_starts_with($argv[1], '--') ? $argv[1] : 'http://localhost/umar/PoultryPro', '/');
$pass = 0;
$fail = [];
function ok(bool $c, string $n): void
{
    global $pass, $fail;
    if ($c) {
        $pass++;
    } else {
        $fail[] = $n;
        echo "  FAIL: $n\n";
    }
}

class C
{
    public int $code = 0;
    public string $body = '';
    public string $loc = '';
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'pr');
    }

    public function go(string $path, ?array $post = null, array $headers = []): string
    {
        $c = curl_init(str_starts_with($path, 'http') ? $path : "$this->base/$path");
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $this->body = (string) curl_exec($c);
        $this->code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        $this->loc = (string) curl_getinfo($c, CURLINFO_REDIRECT_URL);
        curl_close($c);
        return $this->body;
    }

    public function token(): string
    {
        return preg_match('/name="_token" value="([a-f0-9]+)"/', $this->body, $m) ? $m[1] : '';
    }

    public function form(string $page, string $action, array $data, array $headers = []): string
    {
        $this->go($page, null, $headers);
        return $this->go($action, ['_token' => $this->token(), '_once' => bin2hex(random_bytes(8)), ...$data], ["Referer: $this->base/$page", ...$headers]);
    }

    public function login(string $email, string $pw): bool
    {
        $this->form('admin/login', 'admin/login', ['email' => $email, 'password' => $pw]);
        return str_contains($this->loc, 'dashboard');
    }

    public function forgot(string $email, array $headers = []): void
    {
        $this->form('admin/forgot-password', 'admin/forgot-password', ['email' => $email], $headers);
        $this->go('admin/forgot-password', null, $headers);
    }

    public function devLink(): ?string
    {
        return preg_match('#href="([^"]*reset-password\?token=[a-f0-9]{64})"#', $this->body, $m) ? html_entity_decode($m[1]) : null;
    }
}

$stamp = bin2hex(random_bytes(3));
$email = "reset$stamp@poultrypro.test";
$ghost = "ghost$stamp@poultrypro.test";
$off = "off$stamp@poultrypro.test";
$oldPw = 'OldPass1234';
$newPw = 'NewPass5678';
$role = (int) DB::value("SELECT id FROM roles WHERE slug='viewer'");
$uid = DB::insert('users', ['role_id' => $role, 'name' => 'Reset Test', 'email' => $email, 'password_hash' => password_hash($oldPw, PASSWORD_DEFAULT)]);
$offId = DB::insert('users', ['role_id' => $role, 'name' => 'Off', 'email' => $off, 'status' => 'inactive', 'password_hash' => password_hash($oldPw, PASSWORD_DEFAULT)]);
DB::query("DELETE FROM password_resets WHERE ip_address IN ('127.0.0.1','::1')");
$adminHash = DB::value("SELECT password_hash FROM users WHERE email='admin@poultrypro.test'");

$mailLog = BASE_PATH . '/storage/logs/mail.log';
$lastMailLink = function () use ($mailLog): ?string {
    preg_match_all('#https?://\S*reset-password\?token=[a-f0-9]{64}#', (string) @file_get_contents($mailLog), $m);
    return $m[0] ? end($m[0]) : null;
};

try {
    if ($live) {
        echo "Live mode\n";
        $c = new C($base);
        $strip = fn ($h) => preg_replace('/name="_(token|once)" value="[a-f0-9]+"/', '', $h);
        $c->forgot($ghost);
        $unknown = $strip($c->body);
        $c->forgot($email);
        ok($strip($c->body) === $unknown, 'known and unknown emails get an identical page');
        ok($c->devLink() === null, 'the reset link is never shown on the page');
        $link = $lastMailLink();
        ok($link && str_starts_with($link, config('app.url')), 'the emailed link uses APP_URL');
        DB::query('DELETE FROM password_resets WHERE email=?', [$email]);
        $c2 = new C($base);
        $c2->forgot($email, ['Host: evil.test']);
        ok((int) DB::value('SELECT COUNT(*) FROM password_resets WHERE email=? AND token_hash IS NOT NULL', [$email]) === 1, 'a link was really issued for the forged request');
        ok(!str_contains((string) $lastMailLink(), 'evil.test'), 'a forged Host header cannot poison the emailed link');
    } else {
        echo "Pages\n";
        $c = new C($base);
        $c->go('admin/login');
        ok(str_contains($c->body, 'admin/forgot-password') && str_contains($c->body, 'Forgot password?'), 'login page has a forgot password link');
        ok(str_contains($c->body, 'password-toggle.js'), 'login page loads the eye icon');
        $c->go('admin/forgot-password');
        ok($c->code === 200, 'forgot password page opens without signing in');

        echo "Requesting a link\n";
        $c->forgot('not-an-email');
        ok(str_contains($c->body, 'valid email'), 'an invalid address is refused');
        $c->forgot($ghost);
        ok(str_contains($c->body, 'If that email belongs') && !$c->devLink(), 'unknown email gets the standard message and no link');
        $c->forgot($off);
        ok(!$c->devLink() && !DB::value('SELECT COUNT(*) FROM password_resets WHERE user_id=?', [$offId]), 'an inactive account gets no link');
        $c->forgot($email);
        ok(str_contains($c->body, 'If that email belongs'), 'known email gets the same standard message');
        $link = $c->devLink();
        ok((bool) $link, 'local test mode shows the link');
        ok($link === $lastMailLink(), 'the email is written to the mail log');
        $token = substr($link, -64);
        $stored = DB::value('SELECT token_hash FROM password_resets WHERE user_id=? ORDER BY id DESC LIMIT 1', [$uid]);
        ok($stored === hash('sha256', $token) && $stored !== $token, 'only a hash of the token is stored');

        echo "Using the link\n";
        $session = new C($base);
        ok($session->login($email, $oldPw), 'old password works before the reset');
        $c->go($link);
        ok($c->code === 200 && str_contains($c->body, 'name="password_confirm"'), 'a valid link opens the form');
        $relative = 'admin/reset-password?token=' . $token;
        $post = fn ($t, $a, $b) => $c->form($relative, 'admin/reset-password', ['token' => $t, 'password' => $a, 'password_confirm' => $b]);
        $post($token, 'short', 'short');
        $c->go($relative);
        ok(str_contains($c->body, 'at least 8') && str_contains($c->body, 'name="password_confirm"'), 'a short password is refused and the link still works');
        $post($token, $newPw, 'Different999');
        $c->go($relative);
        ok(str_contains($c->body, 'do not match'), 'mismatched passwords are refused');
        $post(str_repeat('a', 64), $newPw, $newPw);
        ok(password_verify($oldPw, DB::value('SELECT password_hash FROM users WHERE id=?', [$uid])), 'a made-up token changes nothing');
        $post("' OR 1=1 -- ", $newPw, $newPw);
        ok(password_verify($oldPw, DB::value('SELECT password_hash FROM users WHERE id=?', [$uid])), 'SQL in the token changes nothing');
        for ($i = 0; $i < 5; $i++) {
            (new C($base))->login($email, 'wrong-guess');
        }
        ok(!(new C($base))->login($email, $oldPw), 'account is locked after failed sign-ins');
        $post($token, $newPw, $newPw);
        ok(str_contains($c->loc, 'admin/login'), 'a good password returns you to sign in');
        $c->go('admin/login');
        ok(str_contains($c->body, 'Sign in with your new password'), 'a success message is shown');
        ok(str_starts_with(DB::value('SELECT password_hash FROM users WHERE id=?', [$uid]), '$2y$'), 'the new password is hashed');
        ok((new C($base))->login($email, $newPw), 'new password works, and the lockout is lifted');
        ok(!(new C($base))->login($email, $oldPw), 'old password no longer works');
        $session->go('admin/dashboard');
        ok($session->code === 302 && str_contains($session->loc, 'admin/login'), 'a session opened before the reset is signed out');
        $c->go($relative);
        ok(str_contains($c->body, 'expired or was already used') && !str_contains($c->body, 'name="password_confirm"'), 'a used link no longer opens the form');
        DB::query('UPDATE password_resets SET used_at=NULL WHERE user_id=? ORDER BY id DESC LIMIT 1', [$uid]);
        DB::query('UPDATE password_resets SET expires_at=? WHERE user_id=?', [date('Y-m-d H:i:s', time() - 5), $uid]);
        $c->go($relative);
        ok(str_contains($c->body, 'expired or was already used'), 'an expired link is refused');

        echo "Limits\n";
        DB::query('DELETE FROM password_resets WHERE user_id=?', [$uid]);
        $c->forgot($email);
        $first = $c->devLink();
        $c->forgot($email);
        $second = $c->devLink();
        $c->go(str_replace($base . '/', '', $first));
        ok(str_contains($c->body, 'expired or was already used'), 'a newer request cancels the earlier link');
        $c->go(str_replace($base . '/', '', $second));
        ok(str_contains($c->body, 'name="password_confirm"'), 'the newest link works');
        $c->forgot($email);
        $c->forgot($email);
        ok($c->devLink() === null, 'a fourth request within the hour creates no link');
        ok(DB::value("SELECT password_hash FROM users WHERE email='admin@poultrypro.test'") === $adminHash, 'other accounts were not touched');
    }
} finally {
    DB::query('DELETE FROM activity_logs WHERE entity_type=? AND entity_id IN (?,?)', ['user', $uid, $offId]);
    DB::query('DELETE FROM users WHERE id IN (?,?)', [$uid, $offId]);
    DB::query('DELETE FROM password_resets WHERE email IN (?,?,?)', [$email, $ghost, $off]);
    DB::query('DELETE FROM login_attempts WHERE email IN (?,?)', [$email, $off]);
}
echo "\n$pass passed, " . count($fail) . " failed\n";
exit($fail ? 1 : 0);
