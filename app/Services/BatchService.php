<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

class BatchService
{
    /**
     * Places a new batch. With a supplier and a price the birds arrive through a confirmed purchase,
     * so the cost is recorded and the supplier is owed. Otherwise they are opening stock with no cost.
     */
    public static function create(array $d): int
    {
        $shed = DB::one('SELECT id, farm_id FROM sheds WHERE id=? AND deleted_at IS NULL', [$d['shed_id']]);
        if (!$shed) {
            throw new BusinessRuleException('Choose a shed.');
        }
        $farmId = (int) $shed['farm_id'];
        Auth::assertFarm($farmId);
        $price = (float) ($d['purchase_price'] ?? 0);
        if ($price > 0 && empty($d['supplier_id'])) {
            throw new BusinessRuleException('Choose the supplier when entering a purchase price, so the cost is recorded against them.');
        }

        return DB::transaction(function () use ($d, $farmId, $price) {
            $qty = (int) $d['initial_quantity'];
            $id = DB::insert('batches', [
                'batch_code' => 'TMP' . bin2hex(random_bytes(6)), 'name' => $d['name'], 'farm_id' => $farmId, 'shed_id' => $d['shed_id'],
                'breed' => $d['breed'] ?? null, 'batch_type' => $d['batch_type'], 'purchase_date' => $d['purchase_date'],
                'age_at_purchase_days' => (int) ($d['age_at_purchase_days'] ?? 0), 'initial_quantity' => $qty,
                'supplier_id' => ($d['supplier_id'] ?? null) ?: null, 'purchase_price' => $price,
                'expected_production_date' => ($d['expected_production_date'] ?? null) ?: null, 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            $code = 'B-' . date('ym', strtotime($d['purchase_date'])) . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            DB::update('batches', ['batch_code' => $code], $id);

            if ($price > 0) {
                $purchaseId = PurchaseService::saveDraft(
                    ['supplier_id' => $d['supplier_id'], 'farm_id' => $farmId, 'purchase_date' => $d['purchase_date'], 'notes' => "Birds for batch $code"],
                    [['item_type' => 'chick', 'ref_id' => $id, 'quantity' => $qty, 'unit_price' => $price]]
                );
                PurchaseService::confirm($purchaseId);
            } else {
                InventoryService::moveBirds($id, (int) $d['shed_id'], $qty, 'opening', $d['purchase_date'], 'batch', $id, "batch:$id:opening", 0);
            }
            VaccinationService::scheduleForBatch($id);
            Activity::log('create', 'batch', $id, "$code with $qty birds");
            return $id;
        });
    }

    /** Removes a batch that was entered by mistake. A batch with any real activity must be closed instead. */
    public static function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $b = DB::one('SELECT * FROM batches WHERE id=? FOR UPDATE', [$id]);
            if (!$b) {
                throw new BusinessRuleException('Batch not found.');
            }
            Auth::assertFarm((int) $b['farm_id']);
            $other = (int) DB::value("SELECT COUNT(*) FROM bird_transactions WHERE batch_id=? AND txn_type NOT IN ('opening')", [$id])
                + (int) DB::value("SELECT COUNT(*) FROM feed_consumption WHERE batch_id=? AND status='posted'", [$id])
                + (int) DB::value("SELECT COUNT(*) FROM egg_production WHERE batch_id=? AND status='posted'", [$id])
                + (int) DB::value("SELECT COUNT(*) FROM medicine_usage WHERE batch_id=? AND status='posted'", [$id]);
            if ($other > 0) {
                throw new BusinessRuleException('This batch has transactions, so it cannot be deleted. Set its status to Closed instead.');
            }
            InventoryService::reverseBirds('batch', $id);
            DB::query("UPDATE vaccination_records SET status='cancelled' WHERE batch_id=? AND status='scheduled'", [$id]);
            DB::update('batches', ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'cancelled'], $id);
            Activity::log('delete', 'batch', $id, $b['batch_code']);
        });
    }

    public static function ageDays(array $b): int
    {
        return max(0, (int) ((time() - strtotime($b['purchase_date'])) / 86400)) + (int) $b['age_at_purchase_days'];
    }

    /** Every figure comes from the ledgers. Bird cost here is what was paid for the batch, not the cost of birds sold, so nothing counts twice. */
    public static function performance(int $id): array
    {
        $b = DB::one('SELECT * FROM batches WHERE id=?', [$id]);
        $moves = array_column(DB::all('SELECT txn_type, SUM(quantity) q FROM bird_transactions WHERE batch_id=? GROUP BY txn_type', [$id]), 'q', 'txn_type');
        $get = fn ($k) => (int) ($moves[$k] ?? 0);
        $fin = fn ($type, $sub = null) => (float) DB::value(
            'SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE batch_id=? AND entry_type=?' . ($sub ? ' AND sub_type=?' : ''),
            $sub ? [$id, $type, $sub] : [$id, $type]
        );
        $dead = -$get('mortality');
        $current = (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE batch_id=?', [$id]);
        $revenue = $fin('revenue');
        $birdCost = $fin('inventory_purchase', 'chick');
        $feedCost = $fin('cogs', 'feed_consumed') + $fin('cogs', 'feed_wastage');
        $medCost = $fin('cogs', 'medicine');
        $otherCost = $fin('opex');
        $feedKg = (float) DB::value("SELECT COALESCE(SUM(quantity_kg),0) FROM feed_consumption WHERE batch_id=? AND status='posted'", [$id]);
        $eggs = DB::one("SELECT COALESCE(SUM(total_eggs),0) total, COALESCE(SUM(good_eggs),0) good FROM egg_production WHERE batch_id=? AND status='posted'", [$id]);
        return [
            'age_days' => self::ageDays($b),
            'opening' => $get('opening'), 'purchased' => $get('purchase'), 'transfers_in' => $get('transfer_in'), 'transfers_out' => -$get('transfer_out'),
            'dead' => $dead, 'sold' => -$get('sale'), 'adjust_in' => $get('adjustment_in'), 'reductions' => -$get('adjustment_out'),
            'current' => $current,
            'mortality_pct' => $b['initial_quantity'] > 0 ? round($dead / $b['initial_quantity'] * 100, 2) : 0,
            'feed_kg' => $feedKg, 'eggs_total' => (int) $eggs['total'], 'eggs_good' => (int) $eggs['good'],
            'revenue' => $revenue, 'bird_cost' => $birdCost, 'feed_cost' => $feedCost, 'medicine_cost' => $medCost, 'other_cost' => $otherCost,
            'profit' => $revenue - $birdCost - $feedCost - $medCost - $otherCost,
        ];
    }
}
