<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

/** Daily farm entries. Entries are never edited: cancel and re-enter, so the history stays honest. */
class OperationsService
{
    /** Splits a "batch:shed" flock value and checks access and the entry date. */
    private static function flock(string $value, string $date): array
    {
        [$batchId, $shedId] = array_map('intval', array_pad(explode(':', $value), 2, 0));
        $f = DB::one('SELECT i.farm_id, i.quantity, b.purchase_date, b.batch_code, b.batch_type, b.status FROM bird_inventory i JOIN batches b ON b.id=i.batch_id WHERE i.batch_id=? AND i.shed_id=?', [$batchId, $shedId]);
        if (!$f) {
            throw new BusinessRuleException('Choose a flock.');
        }
        Auth::assertFarm((int) $f['farm_id']);
        if ($date < $f['purchase_date']) {
            throw new BusinessRuleException("The date is before batch {$f['batch_code']} arrived (" . fdate($f['purchase_date']) . ').');
        }
        if ($date > date('Y-m-d')) {
            throw new BusinessRuleException('The date cannot be in the future.');
        }
        return [...$f, 'batch_id' => $batchId, 'shed_id' => $shedId, 'farm_id' => (int) $f['farm_id']];
    }

    // ------------------------------------------------------------------ Mortality
    public static function recordMortality(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['record_date']);
            $qty = (int) $d['quantity'];
            $id = DB::insert('mortality_records', [
                'record_date' => $d['record_date'], 'farm_id' => $f['farm_id'], 'shed_id' => $f['shed_id'], 'batch_id' => $f['batch_id'],
                'reason_id' => ($d['reason_id'] ?? null) ?: null, 'quantity' => $qty, 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            $cost = InventoryService::moveBirds($f['batch_id'], $f['shed_id'], -$qty, 'mortality', $d['record_date'], 'mortality', $id, "mortality:$id");
            FinanceService::post('cogs', $qty * $cost, $d['record_date'], 'mortality', $id, "mortality:$id:cogs", ['sub_type' => 'bird_mortality', 'farm_id' => $f['farm_id'], 'batch_id' => $f['batch_id'], 'description' => "$qty birds died, {$f['batch_code']}"]);
            Activity::log('create', 'mortality', $id, "$qty birds in {$f['batch_code']}");
            return $id;
        });
    }

    public static function cancelMortality(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $r = self::lockPosted('mortality_records', $id);
            InventoryService::reverseBirds('mortality', $id);
            FinanceService::reverse('mortality', $id);
            DB::update('mortality_records', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'mortality', $id, $reason);
        });
    }

    // ------------------------------------------------------------------ Transfers
    public static function transferBirds(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['transfer_date']);
            $to = DB::one('SELECT id, farm_id FROM sheds WHERE id=? AND deleted_at IS NULL', [$d['to_shed_id']]);
            if (!$to) {
                throw new BusinessRuleException('Choose the destination shed.');
            }
            Auth::assertFarm((int) $to['farm_id']);
            if ((int) $to['id'] === $f['shed_id']) {
                throw new BusinessRuleException('Choose a different destination shed.');
            }
            $qty = (int) $d['quantity'];
            $id = DB::insert('bird_transfers', [
                'batch_id' => $f['batch_id'], 'from_shed_id' => $f['shed_id'], 'to_shed_id' => $to['id'], 'quantity' => $qty,
                'transfer_date' => $d['transfer_date'], 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            InventoryService::moveBirds($f['batch_id'], $f['shed_id'], -$qty, 'transfer_out', $d['transfer_date'], 'transfer', $id, "transfer:$id:out");
            InventoryService::moveBirds($f['batch_id'], (int) $to['id'], $qty, 'transfer_in', $d['transfer_date'], 'transfer', $id, "transfer:$id:in");
            Activity::log('create', 'transfer', $id, "$qty birds of {$f['batch_code']}");
            return $id;
        });
    }

    public static function cancelTransfer(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $t = DB::one('SELECT * FROM bird_transfers WHERE id=? FOR UPDATE', [$id]);
            if (!$t || $t['status'] !== 'posted') {
                throw new BusinessRuleException('This entry is already cancelled.');
            }
            Auth::assertFarm((int) DB::value('SELECT farm_id FROM sheds WHERE id=?', [$t['from_shed_id']]));
            Auth::assertFarm((int) DB::value('SELECT farm_id FROM sheds WHERE id=?', [$t['to_shed_id']]));
            InventoryService::reverseBirds('transfer', $id);
            DB::update('bird_transfers', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'transfer', $id, $reason);
        });
    }

    // ------------------------------------------------------------------ Adjustments
    public static function adjustBirds(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['adjustment_date']);
            $qty = (int) $d['quantity'];
            $up = $d['direction'] === 'increase';
            $id = DB::insert('bird_adjustments', [
                'batch_id' => $f['batch_id'], 'shed_id' => $f['shed_id'], 'direction' => $d['direction'], 'quantity' => $qty,
                'adjustment_date' => $d['adjustment_date'], 'reason' => $d['reason'], 'approved_by' => Auth::id(),
            ]);
            $cost = InventoryService::moveBirds($f['batch_id'], $f['shed_id'], $up ? $qty : -$qty, $up ? 'adjustment_in' : 'adjustment_out', $d['adjustment_date'], 'adjustment', $id, "adjustment:$id", $up ? 0 : null, $d['reason']);
            if (!$up) {
                FinanceService::post('cogs', $qty * $cost, $d['adjustment_date'], 'adjustment', $id, "adjustment:$id:cogs", ['sub_type' => 'bird_adjustment', 'farm_id' => $f['farm_id'], 'batch_id' => $f['batch_id'], 'description' => $d['reason']]);
            }
            Activity::log('create', 'adjustment', $id, "{$d['direction']} $qty birds of {$f['batch_code']}: {$d['reason']}");
            return $id;
        });
    }

    public static function cancelAdjustment(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $a = DB::one('SELECT * FROM bird_adjustments WHERE id=? FOR UPDATE', [$id]);
            if (!$a || $a['status'] !== 'posted') {
                throw new BusinessRuleException('This entry is already cancelled.');
            }
            Auth::assertFarm((int) DB::value('SELECT farm_id FROM sheds WHERE id=?', [$a['shed_id']]));
            InventoryService::reverseBirds('adjustment', $id);
            FinanceService::reverse('adjustment', $id);
            DB::update('bird_adjustments', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'adjustment', $id, $reason);
        });
    }

    // ------------------------------------------------------------------ Feed
    public static function recordFeed(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['record_date']);
            $factor = (float) DB::value('SELECT kg_factor FROM units WHERE id=? AND deleted_at IS NULL', [$d['unit_id']]);
            if ($factor <= 0) {
                throw new BusinessRuleException('Choose a unit.');
            }
            $kg = round((float) $d['quantity'] * $factor, 3);
            $waste = round((float) ($d['wastage_kg'] ?? 0), 3);
            $id = DB::insert('feed_consumption', [
                'record_date' => $d['record_date'], 'farm_id' => $f['farm_id'], 'shed_id' => $f['shed_id'], 'batch_id' => $f['batch_id'],
                'feed_product_id' => $d['feed_product_id'], 'unit_id' => $d['unit_id'], 'quantity' => $d['quantity'], 'quantity_kg' => $kg,
                'wastage_kg' => $waste, 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            $product = (int) $d['feed_product_id'];
            $cost = InventoryService::moveFeed($f['farm_id'], $product, -$kg, 'consumption', $d['record_date'], 'feed_consumption', $id, "feed_consumption:$id");
            $opts = ['farm_id' => $f['farm_id'], 'batch_id' => $f['batch_id']];
            FinanceService::post('cogs', $kg * $cost, $d['record_date'], 'feed_consumption', $id, "feed_consumption:$id:cogs", [...$opts, 'sub_type' => 'feed_consumed', 'description' => "$kg kg feed, {$f['batch_code']}"]);
            if ($waste > 0) {
                InventoryService::moveFeed($f['farm_id'], $product, -$waste, 'wastage', $d['record_date'], 'feed_consumption', $id, "feed_consumption:$id:waste");
                FinanceService::post('cogs', $waste * $cost, $d['record_date'], 'feed_consumption', $id, "feed_consumption:$id:waste:cogs", [...$opts, 'sub_type' => 'feed_wastage', 'description' => "$waste kg feed wasted, {$f['batch_code']}"]);
            }
            DB::update('feed_consumption', ['unit_cost' => $cost, 'total_cost' => round(($kg + $waste) * $cost, 2)], $id);
            return $id;
        });
    }

    public static function cancelFeed(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            self::lockPosted('feed_consumption', $id);
            InventoryService::reverseFeed('feed_consumption', $id);
            FinanceService::reverse('feed_consumption', $id);
            DB::update('feed_consumption', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'feed_consumption', $id, $reason);
        });
    }

    // ------------------------------------------------------------------ Eggs
    public static function recordEggs(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['record_date']);
            if ($f['batch_type'] === 'broiler') {
                throw new BusinessRuleException("Batch {$f['batch_code']} is a broiler batch and does not lay eggs.");
            }
            $dup = DB::value("SELECT COUNT(*) FROM egg_production WHERE record_date=? AND batch_id=? AND shed_id=? AND status='posted'", [$d['record_date'], $f['batch_id'], $f['shed_id']]);
            if ($dup) {
                throw new BusinessRuleException('Eggs are already recorded for this flock on this date. Cancel that entry to re-enter it.');
            }
            $good = (int) $d['good_eggs'];
            $damaged = (int) ($d['damaged_eggs'] ?? 0);
            $broken = (int) ($d['broken_eggs'] ?? 0);
            $id = DB::insert('egg_production', [
                'record_date' => $d['record_date'], 'farm_id' => $f['farm_id'], 'shed_id' => $f['shed_id'], 'batch_id' => $f['batch_id'],
                'total_eggs' => $good + $damaged + $broken, 'good_eggs' => $good, 'damaged_eggs' => $damaged, 'broken_eggs' => $broken,
                'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
            ]);
            if ($good > 0) {
                InventoryService::moveEggs($f['farm_id'], $good, 'production', $d['record_date'], 'egg_production', $id, "egg_production:$id");
            }
            return $id;
        });
    }

    public static function cancelEggs(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            self::lockPosted('egg_production', $id);
            InventoryService::reverseEggs('egg_production', $id);
            DB::update('egg_production', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'egg_production', $id, $reason);
        });
    }

    public static function adjustEggs(array $d): int
    {
        return DB::transaction(function () use ($d) {
            Auth::assertFarm((int) $d['farm_id']);
            $id = DB::insert('egg_adjustments', [
                'farm_id' => $d['farm_id'], 'adjustment_date' => $d['adjustment_date'], 'quantity' => $d['quantity'], 'reason' => $d['reason'], 'created_by' => Auth::id(),
            ]);
            InventoryService::moveEggs((int) $d['farm_id'], -(int) $d['quantity'], 'damage_adjustment', $d['adjustment_date'], 'egg_adjustment', $id, "egg_adjustment:$id", $d['reason']);
            return $id;
        });
    }

    public static function cancelEggAdjustment(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            self::lockPosted('egg_adjustments', $id);
            InventoryService::reverseEggs('egg_adjustment', $id);
            DB::update('egg_adjustments', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'egg_adjustment', $id, $reason);
        });
    }

    // ------------------------------------------------------------------ Medicine
    public static function recordMedicine(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $f = self::flock($d['flock'], $d['record_date']);
            return self::postMedicine($f['batch_id'], $f['farm_id'], (int) $d['medicine_id'], (float) $d['quantity'], $d['record_date'], $d['veterinarian'] ?? null, $d['diagnosis'] ?? null, $d['notes'] ?? null);
        });
    }

    public static function postMedicine(int $batchId, int $farmId, int $medicineId, float $qty, string $date, ?string $vet, ?string $diagnosis, ?string $notes): int
    {
        $id = DB::insert('medicine_usage', [
            'record_date' => $date, 'farm_id' => $farmId, 'batch_id' => $batchId, 'medicine_id' => $medicineId, 'quantity' => $qty,
            'veterinarian' => $vet, 'diagnosis' => $diagnosis, 'notes' => $notes, 'created_by' => Auth::id(),
        ]);
        $cost = InventoryService::consumeMedicine($farmId, $medicineId, $qty, $date, 'medicine_usage', $id);
        DB::update('medicine_usage', ['total_cost' => $cost], $id);
        FinanceService::post('cogs', $cost, $date, 'medicine_usage', $id, "medicine_usage:$id:cogs", ['sub_type' => 'medicine', 'farm_id' => $farmId, 'batch_id' => $batchId, 'description' => 'Medicine used']);
        return $id;
    }

    public static function cancelMedicine(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            self::lockPosted('medicine_usage', $id);
            InventoryService::reverseMedicine('medicine_usage', $id);
            FinanceService::reverse('medicine_usage', $id);
            DB::update('medicine_usage', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'medicine_usage', $id, $reason);
        });
    }

    private static function lockPosted(string $table, int $id): array
    {
        $r = DB::one("SELECT * FROM `$table` WHERE id=? FOR UPDATE", [$id]);
        if (!$r || $r['status'] !== 'posted') {
            throw new BusinessRuleException('This entry is already cancelled.');
        }
        Auth::assertFarm((int) $r['farm_id']);
        return $r;
    }
}
