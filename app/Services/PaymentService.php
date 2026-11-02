<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

class PaymentService
{
    private const DOCS = [
        'sale' => ['table' => 'sales', 'total' => 'grand_total', 'live' => 'confirmed', 'direction' => 'in', 'no' => 'invoice_no'],
        'purchase' => ['table' => 'purchases', 'total' => 'total_amount', 'live' => 'confirmed', 'direction' => 'out', 'no' => 'purchase_no'],
        'expense' => ['table' => 'expenses', 'total' => 'amount', 'live' => 'posted', 'direction' => 'out', 'no' => null],
    ];

    /** @param array $d doc_type, doc_id, amount, account_id, payment_date, method, reference_no, notes */
    public static function record(array $d): int
    {
        $cfg = self::DOCS[$d['doc_type']] ?? throw new BusinessRuleException('Unknown document type.');
        $amount = round((float) $d['amount'], 2);
        if ($amount <= 0) {
            throw new BusinessRuleException('Payment amount must be greater than zero.');
        }
        return DB::transaction(function () use ($d, $cfg, $amount) {
            $docId = (int) $d['doc_id'];
            $doc = DB::one("SELECT * FROM `{$cfg['table']}` WHERE id=? FOR UPDATE", [$docId]);
            if (!$doc || $doc['status'] !== $cfg['live']) {
                throw new BusinessRuleException('Payments can only be recorded against a confirmed document.');
            }
            if ($doc['farm_id'] !== null) {
                Auth::assertFarm((int) $doc['farm_id']);
            }
            $due = round((float) $doc[$cfg['total']] - (float) $doc['paid_amount'], 2);
            if ($amount > $due) {
                throw new BusinessRuleException('Only ' . money($due) . ' is outstanding on this document.');
            }
            $account = DB::one("SELECT id FROM accounts WHERE id=? AND status='active' AND deleted_at IS NULL", [$d['account_id']]);
            if (!$account) {
                throw new BusinessRuleException('Choose an active account.');
            }
            $id = DB::insert('payments', [
                'payment_no' => 'TMP' . bin2hex(random_bytes(6)), 'direction' => $cfg['direction'],
                'party_type' => match ($d['doc_type']) { 'sale' => 'customer', 'purchase' => 'supplier', default => 'other' },
                'customer_id' => $doc['customer_id'] ?? null, 'supplier_id' => $doc['supplier_id'] ?? null,
                'sale_id' => $d['doc_type'] === 'sale' ? $docId : null, 'purchase_id' => $d['doc_type'] === 'purchase' ? $docId : null,
                'expense_id' => $d['doc_type'] === 'expense' ? $docId : null, 'account_id' => $d['account_id'], 'farm_id' => $doc['farm_id'],
                'payment_date' => $d['payment_date'], 'amount' => $amount, 'method' => $d['method'] ?? 'cash',
                'reference_no' => $d['reference_no'] ?? null, 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            $no = 'PAY-' . date('Y', strtotime($d['payment_date'])) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::update('payments', ['payment_no' => $no], $id);
            FinanceService::post(
                $cfg['direction'] === 'in' ? 'receipt' : 'disbursement',
                $amount, $d['payment_date'], 'payment', $id, "payment:$id",
                ['account_id' => $d['account_id'], 'farm_id' => $doc['farm_id'], 'sub_type' => $d['doc_type'], 'description' => "$no " . ($cfg['no'] ? $doc[$cfg['no']] : $doc['description'])]
            );
            self::syncDoc($d['doc_type'], $docId);
            Activity::log('payment', $d['doc_type'], $docId, "$no for " . money($amount));
            return $id;
        });
    }

    public static function void(int $paymentId, ?string $reason = null): void
    {
        DB::transaction(function () use ($paymentId, $reason) {
            $p = DB::one('SELECT * FROM payments WHERE id=? FOR UPDATE', [$paymentId]);
            if (!$p) {
                throw new BusinessRuleException('Payment not found.');
            }
            if ($p['status'] !== 'posted') {
                throw new BusinessRuleException('This payment is already voided.');
            }
            if ($p['farm_id'] !== null) {
                Auth::assertFarm((int) $p['farm_id']);
            }
            FinanceService::reverse('payment', $paymentId);
            DB::update('payments', ['status' => 'voided', 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => $reason], $paymentId);
            foreach (['sale' => 'sale_id', 'purchase' => 'purchase_id', 'expense' => 'expense_id'] as $type => $col) {
                if ($p[$col]) {
                    self::syncDoc($type, (int) $p[$col]);
                }
            }
            Activity::log('void', 'payment', $paymentId, $p['payment_no'] . ($reason ? ": $reason" : ''));
        });
    }

    /** Voids every live payment on a document that is being cancelled. */
    public static function voidAllFor(string $docType, int $docId, string $reason): void
    {
        $col = $docType . '_id';
        foreach (DB::all("SELECT id FROM payments WHERE `$col`=? AND status='posted'", [$docId]) as $p) {
            self::void((int) $p['id'], $reason);
        }
    }

    private static function syncDoc(string $type, int $id): void
    {
        $cfg = self::DOCS[$type];
        $col = $type . '_id';
        $paid = (float) DB::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE `$col`=? AND status='posted'", [$id]);
        $total = (float) DB::value("SELECT `{$cfg['total']}` FROM `{$cfg['table']}` WHERE id=?", [$id]);
        DB::update($cfg['table'], [
            'paid_amount' => $paid,
            'payment_status' => $paid <= 0 ? 'unpaid' : ($paid + 0.005 >= $total ? 'paid' : 'partial'),
        ], $id);
    }
}
