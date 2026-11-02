<?php
namespace App\Core;

class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;
    private static ?array $farmIds = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if ($id) {
                self::load((int) $id);
                // A password change or reset ends every session opened with the old password.
                if (self::$user && (string) self::$user['password_changed_at'] !== (string) ($_SESSION['pw_at'] ?? '')) {
                    self::$user = null;
                    unset($_SESSION['user_id'], $_SESSION['pw_at']);
                }
            }
        }
        return self::$user;
    }

    private static function load(int $id): void
    {
        self::$user = Database::one(
            "SELECT u.*, r.name role_name, r.slug role_slug, r.all_farms FROM users u JOIN roles r ON r.id=u.role_id
             WHERE u.id=? AND u.status='active' AND u.deleted_at IS NULL",
            [$id]
        );
        self::$permissions = null;
        self::$farmIds = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    /** Used by the CLI seeder and tests, which have no session. */
    public static function actAs(int $id): void
    {
        self::$loaded = true;
        self::load($id);
    }

    public static function login(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['last_seen'] = time();
        $_SESSION['pw_at'] = (string) Database::value('SELECT password_changed_at FROM users WHERE id=?', [$id]);
        self::$loaded = false;
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], $id);
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    public static function permissions(): array
    {
        if (self::$permissions === null) {
            $u = self::user();
            self::$permissions = !$u ? [] : array_flip(array_column(Database::all(
                'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=?',
                [$u['role_id']]
            ), 'slug'));
        }
        return self::$permissions;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role_slug'] === 'super_admin' || isset(self::permissions()[$perm]);
    }

    public static function authorize(string $perm): void
    {
        if (!self::can($perm)) {
            throw new ForbiddenException('You do not have permission to do this.');
        }
    }

    /** Farms this user may touch. null means every farm. */
    public static function farmIds(): ?array
    {
        $u = self::user();
        if (!$u) {
            return [];
        }
        if ($u['all_farms']) {
            return null;
        }
        if (self::$farmIds === null) {
            self::$farmIds = array_map('intval', array_column(
                Database::all('SELECT farm_id FROM user_farms WHERE user_id=?', [$u['id']]),
                'farm_id'
            ));
        }
        return self::$farmIds;
    }

    public static function assertFarm(?int $farmId): void
    {
        $ids = self::farmIds();
        if ($ids === null) {
            return;
        }
        if ($farmId === null || !in_array($farmId, $ids, true)) {
            throw new ForbiddenException('That farm is not assigned to you.');
        }
    }

    /** SQL fragment limiting a farm column. Values are cast ints, so this is safe to inline. */
    public static function farmSql(string $column): string
    {
        $ids = self::farmIds();
        if ($ids === null) {
            return '';
        }
        return $ids ? " AND $column IN (" . implode(',', $ids) . ')' : ' AND 1=0';
    }
}
