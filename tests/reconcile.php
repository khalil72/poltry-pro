<?php
use App\Core\Database as DB;

/** Each check returns the number of records that disagree. Every number should be zero. */
function reconcile(): array
{
    $count = fn (string $sql) => (int) DB::value("SELECT COUNT(*) FROM ($sql) x");
    return [
        ['bird balances equal their ledger', $count('SELECT i.id FROM bird_inventory i LEFT JOIN (SELECT batch_id, shed_id, SUM(quantity) q FROM bird_transactions GROUP BY batch_id, shed_id) t ON t.batch_id=i.batch_id AND t.shed_id=i.shed_id WHERE i.quantity<>COALESCE(t.q,0)')],
        ['shed counts equal bird balances', $count('SELECT s.id FROM sheds s LEFT JOIN (SELECT shed_id, SUM(quantity) q FROM bird_inventory GROUP BY shed_id) i ON i.shed_id=s.id WHERE s.current_bird_count<>COALESCE(i.q,0)')],
        ['no shed over capacity', $count('SELECT id FROM sheds WHERE current_bird_count>max_capacity')],
        ['no negative bird stock', $count('SELECT id FROM bird_inventory WHERE quantity<0')],
        ['feed balances equal their ledger', $count('SELECT i.id FROM feed_inventory i LEFT JOIN (SELECT farm_id, feed_product_id, SUM(quantity_kg) q FROM feed_transactions GROUP BY farm_id, feed_product_id) t ON t.farm_id=i.farm_id AND t.feed_product_id=i.feed_product_id WHERE ABS(i.quantity_kg-COALESCE(t.q,0))>0.002')],
        ['egg balances equal their ledger', $count('SELECT i.farm_id FROM egg_inventory i LEFT JOIN (SELECT farm_id, SUM(quantity) q FROM egg_transactions GROUP BY farm_id) t ON t.farm_id=i.farm_id WHERE i.quantity<>COALESCE(t.q,0)')],
        ['medicine lots equal their ledger', $count('SELECT l.id FROM medicine_lots l LEFT JOIN (SELECT lot_id, SUM(quantity) q FROM medicine_transactions GROUP BY lot_id) t ON t.lot_id=l.id WHERE ABS(l.quantity-COALESCE(t.q,0))>0.002')],
        ['sale paid amounts equal payments', $count("SELECT s.id FROM sales s LEFT JOIN (SELECT sale_id, SUM(amount) a FROM payments WHERE status='posted' GROUP BY sale_id) p ON p.sale_id=s.id WHERE ABS(s.paid_amount-COALESCE(p.a,0))>0.005")],
        ['purchase paid amounts equal payments', $count("SELECT s.id FROM purchases s LEFT JOIN (SELECT purchase_id, SUM(amount) a FROM payments WHERE status='posted' GROUP BY purchase_id) p ON p.purchase_id=s.id WHERE ABS(s.paid_amount-COALESCE(p.a,0))>0.005")],
        ['expense paid amounts equal payments', $count("SELECT s.id FROM expenses s LEFT JOIN (SELECT expense_id, SUM(amount) a FROM payments WHERE status='posted' GROUP BY expense_id) p ON p.expense_id=s.id WHERE ABS(s.paid_amount-COALESCE(p.a,0))>0.005")],
        ['every posted payment has one cash entry', $count("SELECT p.id FROM payments p LEFT JOIN (SELECT reference_id, SUM(amount) a FROM financial_transactions WHERE reference_type='payment' GROUP BY reference_id) f ON f.reference_id=p.id WHERE (p.status='posted' AND ABS(COALESCE(f.a,0)-p.amount)>0.005) OR (p.status='voided' AND ABS(COALESCE(f.a,0))>0.005)")],
        ['confirmed sales equal their revenue and tax entries', $count("SELECT s.id FROM sales s LEFT JOIN (SELECT reference_id, SUM(amount) a FROM financial_transactions WHERE reference_type='sale' AND entry_type IN ('revenue','tax_collected') GROUP BY reference_id) f ON f.reference_id=s.id WHERE s.status='confirmed' AND ABS(s.grand_total-COALESCE(f.a,0))>0.005")],
        ['cancelled sales net to zero', $count("SELECT s.id FROM sales s JOIN financial_transactions f ON f.reference_type='sale' AND f.reference_id=s.id WHERE s.status='cancelled' GROUP BY s.id, f.entry_type HAVING ABS(SUM(f.amount))>0.005")],
        ['confirmed purchases equal their entries', $count("SELECT s.id FROM purchases s LEFT JOIN (SELECT reference_id, SUM(amount) a FROM financial_transactions WHERE reference_type='purchase' GROUP BY reference_id) f ON f.reference_id=s.id WHERE s.status='confirmed' AND ABS(s.total_amount-COALESCE(f.a,0))>0.005")],
        ['cancelled purchases net to zero', $count("SELECT s.id FROM purchases s JOIN financial_transactions f ON f.reference_type='purchase' AND f.reference_id=s.id WHERE s.status='cancelled' GROUP BY s.id HAVING ABS(SUM(f.amount))>0.005")],
        ['drafts posted nothing', $count("SELECT s.id FROM sales s JOIN financial_transactions f ON f.reference_type='sale' AND f.reference_id=s.id WHERE s.status='draft'")],
        ['posted expenses equal their entries', $count("SELECT s.id FROM expenses s LEFT JOIN (SELECT reference_id, SUM(amount) a FROM financial_transactions WHERE reference_type='expense' GROUP BY reference_id) f ON f.reference_id=s.id WHERE s.status='posted' AND ABS(s.amount-COALESCE(f.a,0))>0.005")],
        ['a line never posts twice', $count("SELECT reference_id FROM bird_transactions WHERE reference_type='sale_item' AND reversal_of IS NULL GROUP BY reference_id HAVING COUNT(*)>1")],
        ['a reversal never happens twice', $count('SELECT reversal_of FROM financial_transactions WHERE reversal_of IS NOT NULL GROUP BY reversal_of HAVING COUNT(*)>1')],
        ['every egg record adds up', $count('SELECT id FROM egg_production WHERE total_eggs<>good_eggs+damaged_eggs+broken_eggs')],
    ];
}
