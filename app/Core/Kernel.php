<?php
namespace App\Core;

use App\Middleware\SecurityMiddleware;

class Kernel
{
    public static function handle(): void
    {
        try {
            Session::start();
            $GLOBALS['_old_input'] = Session::pull('_old', []);
            $GLOBALS['_form_errors'] = Session::pull('_errors', []);
            SecurityMiddleware::handle();
            $router = new Router();
            require BASE_PATH . '/routes/web.php';
            require BASE_PATH . '/routes/admin.php';
            $router->dispatch(Request::method(), Request::path());
        } catch (ValidationException $e) {
            self::backWithInput($e->errors, reset($e->errors) ?: $e->getMessage());
        } catch (BusinessRuleException $e) {
            self::backWithInput([], $e->getMessage());
        } catch (ForbiddenException $e) {
            self::error(403, $e->getMessage());
        } catch (NotFoundException $e) {
            self::error(404, 'That page does not exist.');
        } catch (\Throwable $e) {
            error_log(sprintf("[%s] %s in %s:%d\n%s", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
            self::error(500, config('app.debug')
                ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'Something went wrong on our side. The error has been logged.');
        }
    }

    private static function backWithInput(array $errors, string $message): void
    {
        if (Request::wantsJson()) {
            http_response_code(422);
            json_out(['ok' => false, 'message' => $message, 'errors' => $errors]);
        }
        if (Request::method() === 'POST') {
            $old = $_POST;
            unset($old['_token'], $old['_once'], $old['password'], $old['password_confirm'], $old['current_password']);
            Session::put('_old', $old);
        }
        Session::put('_errors', $errors);
        Session::flash('error', $message);
        // Only ever redirect back to this site.
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $same = $ref && parse_url($ref, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        header('Location: ' . ($same ? $ref : url('')));
        exit;
    }

    private static function error(int $code, string $message): void
    {
        http_response_code($code);
        if (Request::wantsJson()) {
            json_out(['ok' => false, 'message' => $message]);
        }
        echo View::render('errors/error', compact('code', 'message'));
    }
}
