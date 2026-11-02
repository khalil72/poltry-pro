<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

class SecurityMiddleware
{
    public static function handle(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (Request::method() === 'POST') {
            Csrf::verify();
        }

        if (!empty($_SESSION['user_id'])) {
            $idle = config('app.session_idle_minutes') * 60;
            if (time() - ($_SESSION['last_seen'] ?? 0) > $idle) {
                Auth::logout();
                Session::start();
                Session::flash('info', 'You were signed out after a period of inactivity.');
            } else {
                $_SESSION['last_seen'] = time();
            }
        }
    }
}
