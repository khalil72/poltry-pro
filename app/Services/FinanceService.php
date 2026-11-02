<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

/**
 * Writes financial_transactions, the single source for every money figure in the app.
 * Stock purchases are 'inventory_purchase' (not P&L); their cost reaches the P&L as 'cogs' when the
 * stock is consumed, sold or lost, so a purchase is never counted as an expense as well.
 */
class FinanceService
{
    public static function post(string $entryType, float $amount, string $date, string $refType, int $refId, string $key, array $o = []): void
    {
        DB::requireTransaction();
        $amount = round($amount, 2);
        if ($amount == 0.0) {
            return;
        }
        DB::insert('financial_transactions', [
            'txn_date' => $date, 'entry_type' => $entryType, 'sub_type' => $o['sub_type'] ?? null, 'amount' => $amount,
            'account_id' => $o['account_id'] ?? null, 'farm_id' => $o['farm_id'] ?? null, 'batch_id' => $o['batch_id'] ?? null,
            'expense_category_id' => $o['category_id'] ?? null, 'reference_type' => $refType, 'reference_id' => $refId,
            'source_key' => $key, 'description' => isset($o['description']) ? mb_substr($o['description'], 0, 255) : null, 'created_by' => Auth::id(),
        ]);
    }

    public static function reverse(string $refType, int $refId): void
    {
        DB::requireTransaction();
        $rows = DB::all(
            'SELECT t.* FROM financial_transactions t
             WHERE t.reference_type=? AND t.reference_id=? AND t.reversal_of IS NULL
               AND NOT EXISTS (SELECT 1 FROM financial_transactions r WHERE r.reversal_of=t.id)',
            [$refType, $refId]
        );
        foreach ($rows as $r) {
            DB::insert('financial_transactions', [
                'txn_date' => $r['txn_date'], 'entry_type' => $r['entry_type'], 'sub_type' => $r['sub_type'], 'amount' => -(float) $r['amount'],
                'account_id' => $r['account_id'], 'farm_id' => $r['farm_id'], 'batch_id' => $r['batch_id'], 'expense_category_id' => $r['expense_category_id'],
                'reference_type' => $refType, 'reference_id' => $refId, 'source_key' => "rev:{$r['id']}", 'reversal_of' => $r['id'],
                'description' => 'Reversal: ' . mb_substr((string) $r['description'], 0, 240), 'created_by' => Auth::id(),
            ]);
        }
    }

    public static function accountBalance(int $accountId, ?string $upTo = null): float
    {
        $sql = "SELECT a.opening_balance + COALESCE((SELECT SUM(CASE WHEN f.entry_type IN ('receipt','transfer_in') THEN f.amount ELSE -f.amount END)
                 FROM financial_transactions f WHERE f.account_id=a.id AND f.entry_type IN ('receipt','disbursement','transfer_in','transfer_out')"
            . ($upTo ? ' AND f.txn_date<=?' : '') . '),0) FROM accounts a WHERE a.id=?';
        return (float) DB::value($sql, $upTo ? [$upTo, $accountId] : [$accountId]);
    }

    public static function transferBetweenAccounts(array $d): int
    {
        if ((int) $d['from_account_id'] === (int) $d['to_account_id']) {
            throw new BusinessRuleException('Choose two different accounts.');
        }
        return DB::transaction(function () use ($d) {
            $id = DB::insert('account_transfers', [
                'from_account_id' => $d['from_account_id'], 'to_account_id' => $d['to_account_id'], 'transfer_date' => $d['transfer_date'],
                'amount' => $d['amount'], 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            self::post('transfer_out', (float) $d['amount'], $d['transfer_date'], 'account_transfer', $id, "account_transfer:$id:out", ['account_id' => $d['from_account_id'], 'description' => 'Transfer out']);
            self::post('transfer_in', (float) $d['amount'], $d['transfer_date'], 'account_transfer', $id, "account_transfer:$id:in", ['account_id' => $d['to_account_id'], 'description' => 'Transfer in']);
            return $id;
        });
    }

    public static function cancelTransfer(int $id): void
    {
        DB::transaction(function () use ($id) {
            $t = DB::one('SELECT * FROM account_transfers WHERE id=? FOR UPDATE', [$id]);
            if (!$t || $t['status'] !== 'posted') {
                throw new BusinessRuleException('This transfer is already cancelled.');
            }
            self::reverse('account_transfer', $id);
            DB::update('account_transfers', ['status' => 'cancelled'], $id);
        });
    }

    /** Profit and loss for a period. Inventory purchases and capital spend are shown but never deducted. */
    public static function profitAndLoss(?string $from, ?string $to, ?int $farmId = null): array
    {
        $where = ' WHERE 1=1';
        $p = [];
        if ($from) {
            $where .= ' AND f.txn_date>=?';
            $p[] = $from;
        }
        if ($to) {
            $where .= ' AND f.txn_date<=?';
            $p[] = $to;
        }
        if ($farmId) {
            $where .= ' AND f.farm_id=?';
            $p[] = $farmId;
        }
        $where .= Auth::farmSql('f.farm_id');

        $totals = array_column(DB::all("SELECT f.entry_type, SUM(f.amount) amt FROM financial_transactions f $where GROUP BY f.entry_type", $p), 'amt', 'entry_type');
        $cogs = DB::all("SELECT f.sub_type, SUM(f.amount) amt FROM financial_transactions f $where AND f.entry_type='cogs' GROUP BY f.sub_type HAVING amt<>0 ORDER BY amt DESC", $p);
        $opex = DB::all("SELECT COALESCE(c.name,'Other') name, SUM(f.amount) amt FROM financial_transactions f LEFT JOIN expense_categories c ON c.id=f.expense_category_id $where AND f.entry_type='opex' GROUP BY COALESCE(c.name,'Other') HAVING amt<>0 ORDER BY amt DESC", $p);

        $revenue = (float) ($totals['revenue'] ?? 0);
        $cost = (float) ($totals['cogs'] ?? 0);
        $operating = (float) ($totals['opex'] ?? 0);
        return [
            'revenue' => $revenue,
            'cogs' => $cost,
            'cogs_lines' => $cogs,
            'gross_profit' => $revenue - $cost,
            'operating_expenses' => $operating,
            'opex_lines' => $opex,
            'net_profit' => $revenue - $cost - $operating,
            'capex' => (float) ($totals['capex'] ?? 0),
            'inventory_purchases' => (float) ($totals['inventory_purchase'] ?? 0),
            'tax_collected' => (float) ($totals['tax_collected'] ?? 0),
            'receipts' => (float) ($totals['receipt'] ?? 0),
            'disbursements' => (float) ($totals['disbursement'] ?? 0),
        ];
    }

    public static function receivables(): float
    {
        return (float) DB::value("SELECT COALESCE(SUM(grand_total-paid_amount),0) FROM sales WHERE status='confirmed'" . Auth::farmSql('farm_id'));
    }

    public static function payables(): float
    {
        return (float) DB::value("SELECT COALESCE(SUM(total_amount-paid_amount),0) FROM purchases WHERE status='confirmed'" . Auth::farmSql('farm_id'));
    }

    public static function unpaidExpenses(): float
    {
        return (float) DB::value("SELECT COALESCE(SUM(amount-paid_amount),0) FROM expenses WHERE status='posted'" . Auth::farmSql('farm_id'));
    }
}
