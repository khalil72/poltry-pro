<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

class ExpenseService
{
    /** Operational categories hit the P&L; capital categories are recorded as capex and do not. */
    public static function create(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $cat = DB::one('SELECT * FROM expense_categories WHERE id=? AND deleted_at IS NULL', [$d['category_id']]);
            if (!$cat) {
                throw new BusinessRuleException('Choose a category.');
            }
            $farm = ($d['farm_id'] ?? null) ?: null;
            $batch = ($d['batch_id'] ?? null) ?: null;
            if ($batch) {
                $bf = (int) DB::value('SELECT farm_id FROM batches WHERE id=?', [$batch]);
                if ($farm && (int) $farm !== $bf) {
                    throw new BusinessRuleException('That batch belongs to a different farm.');
                }
                $farm = $bf;
            }
            if ($farm !== null || Auth::farmIds() !== null) {
                Auth::assertFarm($farm === null ? null : (int) $farm);
            }
            $amount = round((float) $d['amount'], 2);
            $paid = round((float) ($d['paid_amount'] ?? 0), 2);
            if ($paid > $amount) {
                throw new BusinessRuleException('The amount paid cannot be more than the expense.');
            }
            $id = DB::insert('expenses', [
                'expense_date' => $d['expense_date'], 'category_id' => $d['category_id'], 'farm_id' => $farm, 'batch_id' => $batch,
                'description' => $d['description'], 'amount' => $amount, 'receipt' => $d['receipt'] ?? null, 'created_by' => Auth::id(),
            ]);
            FinanceService::post(
                $cat['expense_type'] === 'capital' ? 'capex' : 'opex',
                $amount, $d['expense_date'], 'expense', $id, "expense:$id",
                ['farm_id' => $farm, 'batch_id' => $batch, 'category_id' => $cat['id'], 'sub_type' => 'expense', 'description' => $d['description']]
            );
            if ($paid > 0) {
                if (empty($d['account_id'])) {
                    throw new BusinessRuleException('Choose the account the expense was paid from.');
                }
                PaymentService::record([
                    'doc_type' => 'expense', 'doc_id' => $id, 'amount' => $paid, 'account_id' => $d['account_id'],
                    'payment_date' => $d['expense_date'], 'method' => $d['method'] ?? 'cash',
                ]);
            }
            Activity::log('create', 'expense', $id, $d['description'] . ' ' . money($amount));
            return $id;
        });
    }

    public static function cancel(int $id, ?string $reason = null, bool $fromSalary = false): void
    {
        DB::transaction(function () use ($id, $reason, $fromSalary) {
            $x = DB::one('SELECT * FROM expenses WHERE id=? FOR UPDATE', [$id]);
            if (!$x || $x['status'] !== 'posted') {
                throw new BusinessRuleException('This expense is already cancelled.');
            }
            if ($x['farm_id'] !== null || Auth::farmIds() !== null) {
                Auth::assertFarm($x['farm_id'] === null ? null : (int) $x['farm_id']);
            }
            if (!$fromSalary && DB::value("SELECT COUNT(*) FROM salaries WHERE expense_id=? AND status='posted'", [$id])) {
                throw new BusinessRuleException('This expense comes from a salary payment. Cancel it from Salaries.');
            }
            PaymentService::voidAllFor('expense', $id, 'Expense cancelled');
            FinanceService::reverse('expense', $id);
            DB::update('expenses', ['status' => 'cancelled', 'cancel_reason' => $reason], $id);
            Activity::log('cancel', 'expense', $id, $reason);
        });
    }

    /** Paying a salary creates its expense and payment, so it appears once in reports and in the account. */
    public static function paySalary(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $emp = DB::one('SELECT * FROM employees WHERE id=? AND deleted_at IS NULL', [$d['employee_id']]);
            if (!$emp) {
                throw new BusinessRuleException('Choose an employee.');
            }
            $activeKey = "{$emp['id']}:{$d['period']}";
            if (DB::value('SELECT COUNT(*) FROM salaries WHERE active_key=?', [$activeKey])) {
                throw new BusinessRuleException("{$emp['name']} has already been paid for {$d['period']}.");
            }
            $basic = (float) $d['basic_amount'];
            $allow = (float) ($d['allowances'] ?? 0);
            $deduct = (float) ($d['deductions'] ?? 0);
            $net = round($basic + $allow - $deduct, 2);
            if ($net <= 0) {
                throw new BusinessRuleException('Net salary must be greater than zero.');
            }
            $cat = DB::value("SELECT id FROM expense_categories WHERE name='Employee Salaries'");
            if (!$cat) {
                throw new BusinessRuleException('Add an expense category named Employee Salaries first.');
            }
            $expenseId = self::create([
                'expense_date' => $d['pay_date'], 'category_id' => $cat, 'farm_id' => $emp['farm_id'],
                'description' => "Salary {$d['period']}: {$emp['name']}", 'amount' => $net, 'paid_amount' => $net,
                'account_id' => $d['account_id'], 'method' => $d['method'] ?? 'cash',
            ]);
            return DB::insert('salaries', [
                'employee_id' => $emp['id'], 'period' => $d['period'], 'basic_amount' => $basic, 'allowances' => $allow, 'deductions' => $deduct,
                'net_amount' => $net, 'pay_date' => $d['pay_date'], 'expense_id' => $expenseId, 'notes' => $d['notes'] ?? null,
                'active_key' => $activeKey, 'created_by' => Auth::id(),
            ]);
        });
    }

    public static function cancelSalary(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $s = DB::one('SELECT * FROM salaries WHERE id=? FOR UPDATE', [$id]);
            if (!$s || $s['status'] !== 'posted') {
                throw new BusinessRuleException('This salary payment is already cancelled.');
            }
            self::cancel((int) $s['expense_id'], $reason, true);
            DB::update('salaries', ['status' => 'cancelled', 'active_key' => null], $id);
            Activity::log('cancel', 'salary', $id, $reason);
        });
    }
}
