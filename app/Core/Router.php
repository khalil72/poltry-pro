<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    private bool $inAdmin = false;
    private string $prefix = '';

    public function get(string $path, callable|array $handler, ?string $perm = null): void
    {
        $this->add('GET', $path, $handler, $perm);
    }

    public function post(string $path, callable|array $handler, ?string $perm = null): void
    {
        $this->add('POST', $path, $handler, $perm);
    }

    /** Admin group: every route must name a permission, or the literal "auth" for any signed-in user. */
    public function admin(string $prefix, callable $define): void
    {
        $this->prefix = $prefix;
        $this->inAdmin = true;
        $define($this);
        $this->prefix = '';
        $this->inAdmin = false;
    }

    private function add(string $method, string $path, callable|array $handler, ?string $perm): void
    {
        $full = rtrim($this->prefix . $path, '/') ?: '/';
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $full) . '$#';
        if ($this->inAdmin && $perm === null) {
            throw new \LogicException("Admin route $full has no permission.");
        }
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'perm' => $perm, 'admin' => $this->inAdmin];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method || !preg_match($r['regex'], $path, $m)) {
                continue;
            }
            if ($r['admin']) {
                if (!Auth::check()) {
                    if (Request::wantsJson()) {
                        throw new ForbiddenException('Sign in to continue.');
                    }
                    if ($method === 'GET') {
                        Session::put('_intended', Request::path());
                    }
                    redirect('admin/login');
                }
                if ($r['perm'] !== 'auth') {
                    Auth::authorize($r['perm']);
                }
            }
            $args = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $h = $r['handler'];
            is_array($h) ? (new $h[0])->{$h[1]}(...$args) : $h(...$args);
            return;
        }
        throw new NotFoundException();
    }
}
