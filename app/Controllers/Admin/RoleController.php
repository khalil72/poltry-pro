<?php
namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\NotFoundException;

class RoleController extends AdminController
{
    public function index(): void
    {
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id AND u.deleted_at IS NULL) user_count, (SELECT COUNT(*) FROM role_permissions p WHERE p.role_id=r.id) perm_count FROM roles r ORDER BY r.id');
        $this->view('admin/roles/index', ['title' => 'Roles & permissions', 'roles' => $roles]);
    }

    public function edit(string $id): void
    {
        $role = DB::one('SELECT * FROM roles WHERE id=?', [(int) $id]) ?? throw new NotFoundException();
        $perms = DB::all('SELECT * FROM permissions ORDER BY module, slug');
        $grouped = [];
        foreach ($perms as $p) {
            $grouped[$p['module']][] = $p;
        }
        $granted = array_flip(array_column(DB::all('SELECT permission_id FROM role_permissions WHERE role_id=?', [(int) $id]), 'permission_id'));
        $this->view('admin/roles/edit', ['title' => 'Permissions: ' . $role['name'], 'role' => $role, 'grouped' => $grouped, 'granted' => $granted]);
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        $role = DB::one('SELECT * FROM roles WHERE id=?', [$id]) ?? throw new NotFoundException();
        if ($role['slug'] === 'super_admin') {
            throw new BusinessRuleException('Super Admin always has full access and cannot be changed.');
        }
        $ids = array_map('intval', (array) ($_POST['permissions'] ?? []));
        DB::transaction(function () use ($id, $ids, $role) {
            DB::query('DELETE FROM role_permissions WHERE role_id=?', [$id]);
            foreach ($ids as $p) {
                DB::query('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE id=?', [$id, $p]);
            }
            DB::update('roles', ['all_farms' => isset($_POST['all_farms']) ? 1 : 0], $id);
            Activity::log('update', 'role', $id, $role['name']);
        });
        flash('success', 'Permissions updated.');
        redirect('admin/roles');
    }
}
