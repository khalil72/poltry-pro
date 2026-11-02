<?php
use App\Controllers\Site\SiteController;

$router->get('/', [SiteController::class, 'home']);
$router->get('/about', [SiteController::class, 'about']);
$router->get('/farms', [SiteController::class, 'farms']);
$router->get('/products', [SiteController::class, 'products']);
$router->get('/products/{slug}', [SiteController::class, 'product']);
$router->get('/services', [SiteController::class, 'services']);
$router->get('/facilities', [SiteController::class, 'facilities']);
$router->get('/gallery', [SiteController::class, 'gallery']);
$router->get('/blog', [SiteController::class, 'blog']);
$router->get('/blog/{slug}', [SiteController::class, 'post']);
$router->get('/contact', [SiteController::class, 'contact']);
$router->get('/quote', [SiteController::class, 'quote']);
$router->post('/contact', [SiteController::class, 'sendMessage']);
$router->post('/newsletter', [SiteController::class, 'subscribe']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt', [SiteController::class, 'robots']);
