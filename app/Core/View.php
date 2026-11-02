<?php
namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);
        return $layout ? self::capture($layout, [...$data, 'content' => $content]) : $content;
    }

    public static function capture(string $__template, array $__data = []): string
    {
        $__file = BASE_PATH . '/resources/views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("View not found: $__template");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
