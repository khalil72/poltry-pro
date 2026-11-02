<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Models\Farm;
use App\Models\Party;
use App\Models\Shed;
use App\Services\BatchService;
use App\Services\FinanceService;

/** Module-specific behaviour referenced from config/resources.php. */
class Hooks
{
    public static function shedBeforeSave(array $d, ?int $id): array
    {
        if ($id) {
            $cur = DB::one('SELECT farm_id, current_bird_count FROM sheds WHERE id=?', [$id]);
            if ((int) $d['max_capacity'] < (int) $cur['current_bird_count']) {
                throw new BusinessRuleException("This shed holds {$cur['current_bird_count']} birds, so its capacity cannot be set lower than that.");
            }
            if ((int) $d['farm_id'] !== (int) $cur['farm_id'] && $cur['current_bird_count'] > 0) {
                throw new BusinessRuleException('Move the birds out before assigning this shed to another farm.');
            }
        }
        return $d;
    }

    public static function farmShow(array $row): array
    {
        $id = (int) $row['id'];
        $from = date('Y-m-01');
        return [
            'stats' => Farm::stats($id),
            'sheds' => DB::all('SELECT * FROM sheds WHERE farm_id=? AND deleted_at IS NULL ORDER BY shed_number', [$id]),
            'pnl' => can('finance.view') ? FinanceService::profitAndLoss($from, date('Y-m-d'), $id) : null,
        ];
    }

    public static function shedShow(array $row): array
    {
        return Shed::performance((int) $row['id']);
    }

    public static function batchShow(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'perf' => BatchService::performance($id),
            'flocks' => DB::all('SELECT i.quantity, s.id shed_id, s.name shed_name FROM bird_inventory i JOIN sheds s ON s.id=i.shed_id WHERE i.batch_id=? AND i.quantity>0', [$id]),
            'history' => DB::all('SELECT t.*, s.name shed_name FROM bird_transactions t JOIN sheds s ON s.id=t.shed_id WHERE t.batch_id=? ORDER BY t.txn_date DESC, t.id DESC LIMIT 25', [$id]),
            'vaccinations' => DB::all('SELECT r.*, v.name vaccine_name FROM vaccination_records r JOIN vaccinations v ON v.id=r.vaccination_id WHERE r.batch_id=? ORDER BY r.scheduled_date', [$id]),
        ];
    }

    public static function customerShow(array $row): array
    {
        $id = (int) $row['id'];
        return ['kind' => 'customer', 'ledger' => Party::customerLedger($id), 'outstanding' => $row['outstanding'], 'report' => 'customer-ledger'];
    }

    public static function supplierShow(array $row): array
    {
        $id = (int) $row['id'];
        return ['kind' => 'supplier', 'ledger' => Party::supplierLedger($id), 'outstanding' => $row['outstanding'], 'report' => 'supplier-ledger'];
    }

    public static function employeeShow(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'attendance' => DB::all('SELECT attendance_date, status, notes FROM attendance WHERE employee_id=? ORDER BY attendance_date DESC LIMIT 15', [$id]),
            'month' => array_column(DB::all("SELECT status, COUNT(*) c FROM attendance WHERE employee_id=? AND attendance_date>=? GROUP BY status", [$id, date('Y-m-01')]), 'c', 'status'),
            'salaries' => can('salaries.view') ? DB::all('SELECT * FROM salaries WHERE employee_id=? ORDER BY period DESC LIMIT 12', [$id]) : [],
        ];
    }

    public static function accountShow(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'balance' => FinanceService::accountBalance($id),
            'rows' => DB::all("SELECT * FROM financial_transactions WHERE account_id=? ORDER BY txn_date DESC, id DESC LIMIT 100", [$id]),
        ];
    }

    public static function userBeforeSave(array $d, ?int $id): array
    {
        $password = (string) ($d['password'] ?? '');
        if (!$id && $password === '') {
            throw new BusinessRuleException('Enter a password for the new user.');
        }
        if ($password !== '') {
            if (mb_strlen($password) < 8) {
                throw new BusinessRuleException('Passwords must be at least 8 characters.');
            }
            $d['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $d['password_changed_at'] = date('Y-m-d H:i:s');
            if ($id === Auth::id()) {
                $_SESSION['pw_at'] = $d['password_changed_at'];
            }
        }
        unset($d['password']);
        if ($id === Auth::id() && ($d['status'] ?? '') !== 'active') {
            throw new BusinessRuleException('You cannot deactivate your own account.');
        }
        if ($id === Auth::id() && (int) $d['role_id'] !== (int) Auth::user()['role_id']) {
            throw new BusinessRuleException('You cannot change your own role.');
        }
        return $d;
    }

    public static function userAfterSave(int $id, array $d): void
    {
        DB::query('DELETE FROM user_farms WHERE user_id=?', [$id]);
        foreach ((array) ($d['farm_ids'] ?? []) as $farm) {
            DB::query('INSERT IGNORE INTO user_farms (user_id, farm_id) VALUES (?,?)', [$id, (int) $farm]);
        }
    }

    public static function userLoadExtra(array $row): array
    {
        $row['farm_ids'] = array_column(DB::all('SELECT farm_id FROM user_farms WHERE user_id=?', [$row['id']]), 'farm_id');
        return $row;
    }

    public static function userBeforeDelete(array $row): void
    {
        if ((int) $row['id'] === Auth::id()) {
            throw new BusinessRuleException('You cannot delete your own account.');
        }
    }

    public static function galleryBeforeSave(array $d, ?int $id): array
    {
        if ($d['media_type'] === 'video' && !youtube_id($d['video_url'] ?? null)) {
            throw new BusinessRuleException('Enter a valid YouTube link for a video.');
        }
        return $d;
    }

    public static function blogBeforeSave(array $d, ?int $id): array
    {
        $d['body'] = sanitize_html((string) $d['body']);
        if (!$id) {
            $d['author_id'] = Auth::id();
        }
        if ($d['status'] === 'published') {
            $cur = $id ? DB::value('SELECT published_at FROM blog_posts WHERE id=?', [$id]) : null;
            $d['published_at'] = $cur ?: date('Y-m-d H:i:s');
        }
        return $d;
    }

    public static function messageRead(array $row): void
    {
        DB::query('UPDATE contact_messages SET is_read=1 WHERE id=?', [$row['id']]);
    }
}
