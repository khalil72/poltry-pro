<?php
namespace App\Controllers\Admin;

use App\Core\View;

abstract class AdminController
{
    protected function view(string $template, array $data = []): void
    {
        echo View::render($template, $data, 'layouts/admin');
    }
}
