<?php
namespace App\Models;

use App\Core\Database as DB;

/** Customer and supplier ledgers. */
class Party
{
    /** Chronological rows with a running balance; a positive balance means money is still owed. */
    public static function customerLedger(int $id, ?string $from = null, ?string $to = null): array
    {
        $rows = DB::all(
            "SELECT sale_date d, invoice_no ref, 'Invoice' kind, grand_total debit, 0 credit, id doc_id, created_at ts FROM sales WHERE customer_id=? AND status='confirmed'
             UNION ALL
             SELECT payment_date, payment_no, 'Payment', 0, amount, sale_id, created_at FROM payments WHERE customer_id=? AND status='posted'
             ORDER BY d, ts",
            [$id, $id]
        );
        return self::running($rows, $from, $to);
    }

    public static function supplierLedger(int $id, ?string $from = null, ?string $to = null): array
    {
        $rows = DB::all(
            "SELECT purchase_date d, purchase_no ref, 'Purchase' kind, total_amount debit, 0 credit, id doc_id, created_at ts FROM purchases WHERE supplier_id=? AND status='confirmed'
             UNION ALL
             SELECT payment_date, payment_no, 'Payment', 0, amount, purchase_id, created_at FROM payments WHERE supplier_id=? AND status='posted'
             ORDER BY d, ts",
            [$id, $id]
        );
        return self::running($rows, $from, $to);
    }

    private static function running(array $rows, ?string $from, ?string $to): array
    {
        $bal = 0.0;
        $out = [];
        $opening = 0.0;
        foreach ($rows as $r) {
            $bal += (float) $r['debit'] - (float) $r['credit'];
            if ($from && $r['d'] < $from) {
                $opening = $bal;
                continue;
            }
            if ($to && $r['d'] > $to) {
                continue;
            }
            $r['balance'] = $bal;
            $out[] = $r;
        }
        return ['opening' => $opening, 'rows' => $out, 'closing' => $out ? end($out)['balance'] : $opening];
    }
}
