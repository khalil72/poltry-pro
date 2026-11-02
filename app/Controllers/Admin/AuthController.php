<?php
namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\MailService;
use App\Services\PasswordResetService;

class AuthController extends AdminController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('admin/dashboard');
        }
        echo View::render('admin/auth/login', ['title' => 'Sign in', 'heading' => 'Sign in to manage your farms'], 'layouts/auth');
    }

    public function showForgot(): void
    {
        if (Auth::check()) {
            redirect('admin/profile');
        }
        $devLink = Session::pull('_dev_reset_link');
        echo View::render('admin/auth/forgot', ['title' => 'Forgot password', 'heading' => 'Forgot your password?', 'devLink' => $devLink], 'layouts/auth');
    }

    public function sendResetLink(): void
    {
        $d = Validator::check($_POST, ['email' => 'required|email|max:160'], ['email' => 'Email']);
        $link = PasswordResetService::request(strtolower($d['email']), Request::ip());
        // The on-screen link is only for a local install with no mail server.
        if ($link && config('app.env') === 'local' && config('app.debug') && MailService::usesLog()) {
            Session::put('_dev_reset_link', $link);
        }
        // The same reply every time, so this form cannot be used to find out who has an account.
        flash('success', 'If that email belongs to an active account, a reset link is on its way. It works for ' . config('app.password_reset_minutes') . ' minutes.');
        redirect('admin/forgot-password');
    }

    public function showReset(): void
    {
        header('Cache-Control: no-store');
        $token = (string) Request::query('token', '');
        echo View::render('admin/auth/reset', ['title' => 'Choose a new password', 'heading' => 'Choose a new password', 'token' => $token, 'valid' => PasswordResetService::isValid($token)], 'layouts/auth');
    }

    public function resetPassword(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($password) < 8) {
            throw new BusinessRuleException('Passwords must be at least 8 characters.');
        }
        if ($password !== ($_POST['password_confirm'] ?? '')) {
            throw new BusinessRuleException('The new passwords do not match.');
        }
        PasswordResetService::reset($token, $password);
        flash('success', 'Your password was changed. Sign in with your new password.');
        redirect('admin/login');
    }

    public function login(): void
    {
        $email = strtolower(trim((string) Request::input('email', '')));
        $password = (string) ($_POST['password'] ?? '');
        $ip = Request::ip();
        $since = date('Y-m-d H:i:s', time() - config('app.login_lock_minutes') * 60);

        // Limits guesses per account and per address, so neither one account nor many can be brute-forced.
        $byEmail = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE email=? AND attempted_at>?', [$email, $since]);
        $byIp = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at>?', [$ip, $since]);
        if ($byEmail >= config('app.login_max_attempts') || $byIp >= config('app.login_max_attempts') * 4) {
            throw new BusinessRuleException('Too many failed sign-ins. Try again in ' . config('app.login_lock_minutes') . ' minutes.');
        }

        $user = DB::one("SELECT id, password_hash FROM users WHERE email=? AND status='active' AND deleted_at IS NULL", [$email]);
        // Verifies against a dummy hash when the user is unknown, so timing does not reveal which emails exist.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsalt$0000000000000000000000000000000';
        if (!password_verify($password, $hash) || !$user) {
            DB::insert('login_attempts', ['email' => mb_substr($email, 0, 160), 'ip_address' => $ip, 'attempted_at' => date('Y-m-d H:i:s')]);
            throw new BusinessRuleException('That email or password is not correct.');
        }

        DB::query('DELETE FROM login_attempts WHERE email=?', [$email]);
        Auth::login((int) $user['id']);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], (int) $user['id']);
        }
        Activity::log('login', 'user', (int) $user['id']);
        $intended = Session::pull('_intended', '/admin/dashboard');
        redirect(str_starts_with($intended, '/admin') ? $intended : 'admin/dashboard');
    }

    public function logout(): void
    {
        Activity::log('logout', 'user', Auth::id());
        Auth::logout();
        redirect('admin/login');
    }

    public function profile(): void
    {
        $this->view('admin/auth/profile', ['title' => 'My profile', 'user' => Auth::user()]);
    }

    public function updateProfile(): void
    {
        $id = Auth::id();
        $d = Validator::check($_POST, ['name' => 'required|max:120', 'phone' => 'phone'], []);
        DB::update('users', $d, $id);
        flash('success', 'Profile updated.');
        redirect('admin/profile');
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            throw new BusinessRuleException('Your current password is not correct.');
        }
        if (mb_strlen($new) < 8) {
            throw new BusinessRuleException('Passwords must be at least 8 characters.');
        }
        if ($new !== ($_POST['password_confirm'] ?? '')) {
            throw new BusinessRuleException('The new passwords do not match.');
        }
        $now = date('Y-m-d H:i:s');
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_changed_at' => $now], (int) $user['id']);
        session_regenerate_id(true);
        $_SESSION['pw_at'] = $now;
        Activity::log('password_change', 'user', (int) $user['id']);
        flash('success', 'Password changed.');
        redirect('admin/profile');
    }
}
