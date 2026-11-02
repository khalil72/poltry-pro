<?php
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CrudController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DocumentController;
use App\Controllers\Admin\FinanceController;
use App\Controllers\Admin\OperationsController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingsController;

// Public entry points to the admin area
$router->get('/admin/login', [AuthController::class, 'showLogin']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->get('/admin/forgot-password', [AuthController::class, 'showForgot']);
$router->post('/admin/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/admin/reset-password', [AuthController::class, 'showReset']);
$router->post('/admin/reset-password', [AuthController::class, 'resetPassword']);

$router->admin('/admin', function ($r) {
    $r->get('', fn () => redirect('admin/dashboard'), 'auth');
    $r->post('/logout', [AuthController::class, 'logout'], 'auth');
    $r->get('/profile', [AuthController::class, 'profile'], 'auth');
    $r->post('/profile', [AuthController::class, 'updateProfile'], 'auth');
    $r->post('/profile/password', [AuthController::class, 'changePassword'], 'auth');

    $r->get('/dashboard', [DashboardController::class, 'index'], 'dashboard.view');
    $r->get('/dashboard/data', [DashboardController::class, 'data'], 'dashboard.view');

    // Line-item documents (registered before the generic routes so they take priority)
    $r->get('/sales/create', [DocumentController::class, 'saleCreate'], 'sales.manage');
    $r->post('/sales/save', [DocumentController::class, 'saleStore'], 'sales.manage');
    $r->get('/sales/{id}', [DocumentController::class, 'saleShow'], 'sales.view');
    $r->get('/sales/{id}/edit', [DocumentController::class, 'saleEdit'], 'sales.manage');
    $r->post('/sales/{id}/save', [DocumentController::class, 'saleStore'], 'sales.manage');
    $r->post('/sales/{id}/confirm', [DocumentController::class, 'saleConfirm'], 'sales.manage');
    $r->get('/sales/{id}/print', [DocumentController::class, 'salePrint'], 'sales.view');
    $r->get('/sales/{id}/pdf', [DocumentController::class, 'salePdf'], 'sales.view');

    $r->get('/purchases/create', [DocumentController::class, 'purchaseCreate'], 'purchases.manage');
    $r->post('/purchases/save', [DocumentController::class, 'purchaseStore'], 'purchases.manage');
    $r->get('/purchases/{id}', [DocumentController::class, 'purchaseShow'], 'purchases.view');
    $r->get('/purchases/{id}/edit', [DocumentController::class, 'purchaseEdit'], 'purchases.manage');
    $r->post('/purchases/{id}/save', [DocumentController::class, 'purchaseStore'], 'purchases.manage');
    $r->post('/purchases/{id}/confirm', [DocumentController::class, 'purchaseConfirm'], 'purchases.manage');
    $r->get('/purchases/{id}/print', [DocumentController::class, 'purchasePrint'], 'purchases.view');
    $r->get('/purchases/{id}/pdf', [DocumentController::class, 'purchasePdf'], 'purchases.view');

    $r->post('/payments/record', [DocumentController::class, 'recordPayment'], 'payments.manage');
    $r->get('/receipts/{id}', [DocumentController::class, 'receipt'], 'expenses.view');

    $r->get('/vaccination-records/{id}/given', [OperationsController::class, 'vaccinationGivenForm'], 'vaccinations.manage');
    $r->post('/vaccination-records/{id}/given', [OperationsController::class, 'vaccinationGiven'], 'vaccinations.manage');
    $r->get('/attendance/mark', [OperationsController::class, 'attendanceForm'], 'employees.manage');
    $r->post('/attendance/mark', [OperationsController::class, 'attendanceSave'], 'employees.manage');

    $r->get('/finance', [FinanceController::class, 'overview'], 'finance.view');
    $r->get('/reports', [ReportController::class, 'index'], 'auth');
    $r->get('/reports/{key}', [ReportController::class, 'show'], 'auth');

    $r->get('/roles', [RoleController::class, 'index'], 'roles.view');
    $r->get('/roles/{id}/edit', [RoleController::class, 'edit'], 'roles.manage');
    $r->post('/roles/{id}', [RoleController::class, 'update'], 'roles.manage');

    $r->get('/website', [SettingsController::class, 'website'], 'cms.view');
    $r->post('/website', [SettingsController::class, 'saveWebsite'], 'cms.manage');
    $r->get('/settings', [SettingsController::class, 'system'], 'settings.view');
    $r->post('/settings', [SettingsController::class, 'saveSystem'], 'settings.manage');

    // Generic modules. Viewing needs <perm>.view, anything that writes needs <perm>.manage.
    foreach (config('resources') as $slug => $res) {
        $view = $res['perm'] . '.view';
        $manage = $res['perm'] . '.manage';
        $c = fn () => new CrudController();
        $r->get("/$slug", fn () => $c()->index($slug), $view);
        $r->get("/$slug/create", fn () => $c()->create($slug), $manage);
        $r->post("/$slug", fn () => $c()->store($slug), $manage);
        $r->get("/$slug/{id}", fn ($id) => $c()->show($slug, $id), $view);
        $r->get("/$slug/{id}/edit", fn ($id) => $c()->edit($slug, $id), $manage);
        $r->post("/$slug/{id}", fn ($id) => $c()->update($slug, $id), $manage);
        $r->post("/$slug/{id}/delete", fn ($id) => $c()->destroy($slug, $id), $manage);
        $r->post("/$slug/{id}/cancel", fn ($id) => $c()->cancel($slug, $id), $manage);
    }
});
