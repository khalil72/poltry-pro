<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

/**
 * The only code that changes stock. Every change writes one ledger row and updates the balance
 * in the same transaction. The UNIQUE source_key on each ledger is what makes "exactly once" hold.
 */
class InventoryService
{
    /** Birds. Returns the per-bird cost used for this movement. */
    public static function moveBirds(int $batchId, int $shedId, int $delta, string $type, string $date, string $refType, int $refId, string $key, ?float $unitCost = null, ?string $notes = null, ?int $reversalOf = null): float
    {
        DB::requireTransaction();
        if ($delta === 0) {
            throw new BusinessRuleException('Quantity must not be zero.');
        }
        $batch = DB::one('SELECT id, batch_code, avg_bird_cost FROM batches WHERE id=? FOR UPDATE', [$batchId]);
        $shed = DB::one('SELECT id, farm_id, name, status, max_capacity, current_bird_count FROM sheds WHERE id=? FOR UPDATE', [$shedId]);
        if (!$batch || !$shed) {
            throw new BusinessRuleException('That batch or shed no longer exists.');
        }
        DB::query('INSERT IGNORE INTO bird_inventory (batch_id, shed_id, farm_id, quantity) VALUES (?,?,?,0)', [$batchId, $shedId, $shed['farm_id']]);
        $current = (int) DB::value('SELECT quantity FROM bird_inventory WHERE batch_id=? AND shed_id=? FOR UPDATE', [$batchId, $shedId]);
        $new = $current + $delta;
        if ($new < 0) {
            throw new BusinessRuleException("Only $current birds of batch {$batch['batch_code']} are in {$shed['name']}, so " . abs($delta) . ' cannot be removed.');
        }
        if ($delta > 0) {
            $free = $shed['max_capacity'] - $shed['current_bird_count'];
            if ($delta > $free) {
                throw new BusinessRuleException("{$shed['name']} has room for $free more birds (capacity {$shed['max_capacity']}), so $delta cannot be added.");
            }
            if ($shed['status'] !== 'active' && !$reversalOf) {
                throw new BusinessRuleException("{$shed['name']} is not active, so birds cannot be placed in it.");
            }
        }

        $stockBefore = (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE batch_id=?', [$batchId]);
        $avg = (float) $batch['avg_bird_cost'];
        $cost = $unitCost ?? $avg;
        $revalues = $type === 'purchase' || ($reversalOf && in_array($type, ['sale', 'mortality', 'adjustment_out'], true));
        if ($revalues) {
            DB::update('batches', ['avg_bird_cost' => self::movingAverage($stockBefore, $avg, $delta, $cost)], $batchId);
        }

        DB::query('UPDATE bird_inventory SET quantity=? WHERE batch_id=? AND shed_id=?', [$new, $batchId, $shedId]);
        DB::query('UPDATE sheds SET current_bird_count=current_bird_count+? WHERE id=?', [$delta, $shedId]);
        DB::insert('bird_transactions', [
            'batch_id' => $batchId, 'shed_id' => $shedId, 'farm_id' => $shed['farm_id'], 'txn_date' => $date, 'txn_type' => $type,
            'quantity' => $delta, 'balance_after' => $new, 'unit_cost' => $cost, 'reference_type' => $refType, 'reference_id' => $refId,
            'source_key' => $key, 'reversal_of' => $reversalOf, 'notes' => $notes, 'created_by' => Auth::id(),
        ]);
        return $cost;
    }

    public static function reverseBirds(string $refType, int $refId): void
    {
        foreach (self::unreversed('bird_transactions', $refType, $refId) as $r) {
            self::moveBirds((int) $r['batch_id'], (int) $r['shed_id'], -(int) $r['quantity'], $r['txn_type'], $r['txn_date'], $refType, $refId, "rev:{$r['id']}", (float) $r['unit_cost'], 'Reversal', (int) $r['id']);
        }
    }

    /** Feed, held per farm in kilograms. Returns the cost per kg used. */
    public static function moveFeed(int $farmId, int $productId, float $deltaKg, string $type, string $date, string $refType, int $refId, string $key, ?float $unitCost = null, ?string $notes = null, ?int $reversalOf = null): float
    {
        DB::requireTransaction();
        $deltaKg = round($deltaKg, 3);
        DB::query('INSERT IGNORE INTO feed_inventory (farm_id, feed_product_id, quantity_kg, avg_cost_kg) VALUES (?,?,0,0)', [$farmId, $productId]);
        $inv = DB::one('SELECT id, quantity_kg, avg_cost_kg FROM feed_inventory WHERE farm_id=? AND feed_product_id=? FOR UPDATE', [$farmId, $productId]);
        $stock = (float) $inv['quantity_kg'];
        $new = round($stock + $deltaKg, 3);
        if ($new < 0) {
            $name = DB::value('SELECT name FROM feed_products WHERE id=?', [$productId]);
            throw new BusinessRuleException("Only " . num($stock, 2) . " kg of $name is in stock at this farm, so " . num(abs($deltaKg), 2) . ' kg cannot be removed.');
        }
        $avg = (float) $inv['avg_cost_kg'];
        $cost = $unitCost ?? $avg;
        if ($type === 'purchase' || ($reversalOf && $deltaKg > 0)) {
            $avg = self::movingAverage($stock, $avg, $deltaKg, $cost);
        }
        DB::update('feed_inventory', ['quantity_kg' => $new, 'avg_cost_kg' => $avg], (int) $inv['id']);
        DB::insert('feed_transactions', [
            'farm_id' => $farmId, 'feed_product_id' => $productId, 'txn_date' => $date, 'txn_type' => $type, 'quantity_kg' => $deltaKg,
            'balance_after_kg' => $new, 'unit_cost' => $cost, 'reference_type' => $refType, 'reference_id' => $refId, 'source_key' => $key,
            'reversal_of' => $reversalOf, 'notes' => $notes, 'created_by' => Auth::id(),
        ]);
        return $cost;
    }

    public static function reverseFeed(string $refType, int $refId): void
    {
        foreach (self::unreversed('feed_transactions', $refType, $refId) as $r) {
            self::moveFeed((int) $r['farm_id'], (int) $r['feed_product_id'], -(float) $r['quantity_kg'], $r['txn_type'], $r['txn_date'], $refType, $refId, "rev:{$r['id']}", (float) $r['unit_cost'], 'Reversal', (int) $r['id']);
        }
    }

    /** Saleable good eggs, held per farm. */
    public static function moveEggs(int $farmId, int $delta, string $type, string $date, string $refType, int $refId, string $key, ?string $notes = null, ?int $reversalOf = null): void
    {
        DB::requireTransaction();
        DB::query('INSERT IGNORE INTO egg_inventory (farm_id, quantity) VALUES (?,0)', [$farmId]);
        $stock = (int) DB::value('SELECT quantity FROM egg_inventory WHERE farm_id=? FOR UPDATE', [$farmId]);
        $new = $stock + $delta;
        if ($new < 0) {
            throw new BusinessRuleException("Only " . num($stock) . ' good eggs are in stock at this farm, so ' . num(abs($delta)) . ' cannot be removed.');
        }
        DB::query('UPDATE egg_inventory SET quantity=? WHERE farm_id=?', [$new, $farmId]);
        DB::insert('egg_transactions', [
            'farm_id' => $farmId, 'txn_date' => $date, 'txn_type' => $type, 'quantity' => $delta, 'balance_after' => $new,
            'reference_type' => $refType, 'reference_id' => $refId, 'source_key' => $key, 'reversal_of' => $reversalOf,
            'notes' => $notes, 'created_by' => Auth::id(),
        ]);
    }

    public static function reverseEggs(string $refType, int $refId): void
    {
        foreach (self::unreversed('egg_transactions', $refType, $refId) as $r) {
            self::moveEggs((int) $r['farm_id'], -(int) $r['quantity'], $r['txn_type'], $r['txn_date'], $refType, $refId, "rev:{$r['id']}", 'Reversal', (int) $r['id']);
        }
    }

    /** Catalogue products with stock tracking switched on. Returns the unit cost used. */
    public static function moveProduct(int $productId, float $delta, string $type, string $date, string $refType, int $refId, string $key, ?float $unitCost = null, ?int $reversalOf = null): float
    {
        DB::requireTransaction();
        $p = DB::one('SELECT id, name, stock_quantity, avg_cost FROM products WHERE id=? FOR UPDATE', [$productId]);
        $stock = (float) $p['stock_quantity'];
        $new = round($stock + $delta, 3);
        if ($new < 0) {
            throw new BusinessRuleException("Only " . num($stock, 2) . " of {$p['name']} are in stock, so " . num(abs($delta), 2) . ' cannot be removed.');
        }
        $avg = (float) $p['avg_cost'];
        $cost = $unitCost ?? $avg;
        if ($type === 'purchase' || ($reversalOf && $delta > 0)) {
            $avg = self::movingAverage($stock, $avg, $delta, $cost);
        }
        DB::update('products', ['stock_quantity' => $new, 'avg_cost' => $avg], $productId);
        DB::insert('product_transactions', [
            'product_id' => $productId, 'txn_date' => $date, 'txn_type' => $type, 'quantity' => $delta, 'balance_after' => $new,
            'unit_cost' => $cost, 'reference_type' => $refType, 'reference_id' => $refId, 'source_key' => $key,
            'reversal_of' => $reversalOf, 'created_by' => Auth::id(),
        ]);
        return $cost;
    }

    public static function reverseProducts(string $refType, int $refId): void
    {
        foreach (self::unreversed('product_transactions', $refType, $refId) as $r) {
            self::moveProduct((int) $r['product_id'], -(float) $r['quantity'], $r['txn_type'], $r['txn_date'], $refType, $refId, "rev:{$r['id']}", (float) $r['unit_cost'], (int) $r['id']);
        }
    }

    /** Receives medicine into a new lot. */
    public static function addMedicineLot(int $farmId, int $medicineId, float $qty, float $unitCost, ?string $lotNo, ?string $expiry, string $date, string $refType, int $refId, string $key): void
    {
        DB::requireTransaction();
        $lotId = DB::insert('medicine_lots', [
            'farm_id' => $farmId, 'medicine_id' => $medicineId, 'lot_no' => $lotNo, 'expiry_date' => $expiry, 'quantity' => $qty, 'unit_cost' => $unitCost,
        ]);
        DB::insert('medicine_transactions', [
            'lot_id' => $lotId, 'farm_id' => $farmId, 'medicine_id' => $medicineId, 'txn_date' => $date, 'txn_type' => 'purchase', 'quantity' => $qty,
            'unit_cost' => $unitCost, 'reference_type' => $refType, 'reference_id' => $refId, 'source_key' => $key, 'created_by' => Auth::id(),
        ]);
    }

    /** Uses medicine from the lots that expire first, skipping expired ones. Returns the total cost. */
    public static function consumeMedicine(int $farmId, int $medicineId, float $qty, string $date, string $refType, int $refId): float
    {
        DB::requireTransaction();
        $lots = DB::all(
            'SELECT id, quantity, unit_cost FROM medicine_lots WHERE farm_id=? AND medicine_id=? AND quantity>0 AND (expiry_date IS NULL OR expiry_date>=?)
             ORDER BY expiry_date IS NULL, expiry_date, id FOR UPDATE',
            [$farmId, $medicineId, $date]
        );
        $available = array_sum(array_column($lots, 'quantity'));
        if ($available + 0.0005 < $qty) {
            $name = DB::value('SELECT name FROM medicines WHERE id=?', [$medicineId]);
            throw new BusinessRuleException('Only ' . num($available, 2) . " of $name (not expired) is in stock at this farm, so " . num($qty, 2) . ' cannot be used.');
        }
        $remaining = $qty;
        $cost = 0.0;
        foreach ($lots as $lot) {
            if ($remaining <= 0.0005) {
                break;
            }
            $take = min($remaining, (float) $lot['quantity']);
            DB::query('UPDATE medicine_lots SET quantity=quantity-? WHERE id=?', [$take, $lot['id']]);
            DB::insert('medicine_transactions', [
                'lot_id' => $lot['id'], 'farm_id' => $farmId, 'medicine_id' => $medicineId, 'txn_date' => $date, 'txn_type' => 'usage', 'quantity' => -$take,
                'unit_cost' => $lot['unit_cost'], 'reference_type' => $refType, 'reference_id' => $refId,
                'source_key' => "$refType:$refId:lot:{$lot['id']}", 'created_by' => Auth::id(),
            ]);
            $cost += $take * (float) $lot['unit_cost'];
            $remaining -= $take;
        }
        return round($cost, 2);
    }

    public static function reverseMedicine(string $refType, int $refId): void
    {
        DB::requireTransaction();
        foreach (self::unreversed('medicine_transactions', $refType, $refId) as $r) {
            $lot = DB::one('SELECT quantity FROM medicine_lots WHERE id=? FOR UPDATE', [$r['lot_id']]);
            $new = (float) $lot['quantity'] - (float) $r['quantity'];
            if ($new < -0.0005) {
                throw new BusinessRuleException('Some of this medicine has already been used, so the receipt cannot be reversed.');
            }
            DB::update('medicine_lots', ['quantity' => max(0, $new)], (int) $r['lot_id']);
            DB::insert('medicine_transactions', [
                'lot_id' => $r['lot_id'], 'farm_id' => $r['farm_id'], 'medicine_id' => $r['medicine_id'], 'txn_date' => $r['txn_date'], 'txn_type' => $r['txn_type'],
                'quantity' => -(float) $r['quantity'], 'unit_cost' => $r['unit_cost'], 'reference_type' => $refType, 'reference_id' => $refId,
                'source_key' => "rev:{$r['id']}", 'reversal_of' => $r['id'], 'created_by' => Auth::id(),
            ]);
        }
    }

    /** Ledger rows for a document that have not been reversed yet, newest first. */
    private static function unreversed(string $table, string $refType, int $refId): array
    {
        return DB::all(
            "SELECT t.* FROM `$table` t
             WHERE t.reference_type=? AND t.reference_id=? AND t.reversal_of IS NULL
               AND NOT EXISTS (SELECT 1 FROM `$table` r WHERE r.reversal_of=t.id)
             ORDER BY t.id DESC",
            [$refType, $refId]
        );
    }

    private static function movingAverage(float $stock, float $avg, float $delta, float $cost): float
    {
        $newStock = $stock + $delta;
        if ($newStock <= 0.0005) {
            return $delta < 0 ? $avg : $cost;
        }
        return max(0, round(($stock * $avg + $delta * $cost) / $newStock, 4));
    }
}
