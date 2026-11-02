<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\Validator;

class SaleService
{
    public static function saveDraft(array $h, array $lines, ?int $id = null): int
    {
        $h = Validator::check($h, [
            'customer_id' => 'required|int', 'farm_id' => 'required|int', 'sale_date' => 'required|date',
            'discount' => 'numeric|min:0', 'tax_rate' => 'numeric|min:0', 'notes' => 'max:500',
        ]);
        Auth::assertFarm((int) $h['farm_id']);
        $items = self::normalizeLines($lines, (int) $h['farm_id']);
        $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
        $discount = round((float) $h['discount'], 2);
        if ($discount > $subtotal) {
            throw new BusinessRuleException('The discount cannot be more than the subtotal.');
        }
        $rate = (float) $h['tax_rate'];
        $tax = round(($subtotal - $discount) * $rate / 100, 2);
        $row = [...$h, 'discount' => $discount, 'tax_rate' => $rate, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'grand_total' => round($subtotal - $discount + $tax, 2)];

        return DB::transaction(function () use ($row, $items, $id) {
            if ($id) {
                $cur = DB::one('SELECT status, farm_id FROM sales WHERE id=? FOR UPDATE', [$id]);
                if (!$cur || $cur['status'] !== 'draft') {
                    throw new BusinessRuleException('Only draft invoices can be edited.');
                }
                Auth::assertFarm((int) $cur['farm_id']);
                DB::update('sales', $row, $id);
                DB::query('DELETE FROM sale_items WHERE sale_id=?', [$id]);
            } else {
                $id = DB::insert('sales', [...$row, 'invoice_no' => 'TMP' . bin2hex(random_bytes(6)), 'created_by' => Auth::id()]);
                DB::update('sales', ['invoice_no' => 'INV-' . date('Y', strtotime($row['sale_date'])) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)], $id);
            }
            foreach ($items as $it) {
                DB::insert('sale_items', [...$it, 'sale_id' => $id]);
            }
            return $id;
        });
    }

    private static function normalizeLines(array $lines, int $farmId): array
    {
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $type = $l['item_type'] ?? '';
            if ($type === '') {
                continue;
            }
            $qty = (float) ($l['quantity'] ?? 0);
            $price = (float) ($l['unit_price'] ?? 0);
            if ($qty <= 0 || $price < 0) {
                throw new BusinessRuleException("Line $n: enter a quantity above zero and a price of zero or more.");
            }
            $it = ['item_type' => $type, 'batch_id' => null, 'shed_id' => null, 'feed_product_id' => null, 'product_id' => null, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => round($qty * $price, 2)];
            $ref = (string) ($l['ref_id'] ?? '');
            switch ($type) {
                case 'bird':
                    if ($qty != (int) $qty) {
                        throw new BusinessRuleException("Line $n: birds must be a whole number.");
                    }
                    [$batchId, $shedId] = array_map('intval', array_pad(explode(':', $ref), 2, 0));
                    $row = DB::one('SELECT b.batch_code, s.name shed FROM bird_inventory i JOIN batches b ON b.id=i.batch_id JOIN sheds s ON s.id=i.shed_id WHERE i.batch_id=? AND i.shed_id=? AND i.farm_id=?', [$batchId, $shedId, $farmId]);
                    if (!$row) {
                        throw new BusinessRuleException("Line $n: choose a flock at this farm.");
                    }
                    $it['batch_id'] = $batchId;
                    $it['shed_id'] = $shedId;
                    $it['description'] = "Live birds, {$row['batch_code']} ({$row['shed']})";
                    break;
                case 'egg':
                    $b = null;
                    if ($qty != (int) $qty) {
                        throw new BusinessRuleException("Line $n: eggs must be a whole number.");
                    }
                    if ($ref !== '') {
                        $b = DB::one('SELECT id, batch_code FROM batches WHERE id=? AND farm_id=?', [(int) $ref, $farmId]);
                        if (!$b) {
                            throw new BusinessRuleException("Line $n: that batch is not at this farm.");
                        }
                        $it['batch_id'] = (int) $b['id'];
                    }
                    $it['description'] = 'Fresh eggs' . ($b ? " ({$b['batch_code']})" : '');
                    break;
                case 'feed':
                    $f = DB::one('SELECT id, name FROM feed_products WHERE id=? AND deleted_at IS NULL', [(int) $ref]);
                    if (!$f) {
                        throw new BusinessRuleException("Line $n: choose a feed product.");
                    }
                    $it['feed_product_id'] = (int) $f['id'];
                    $it['description'] = "{$f['name']} (kg)";
                    break;
                case 'product':
                    $p = DB::one('SELECT id, name FROM products WHERE id=? AND deleted_at IS NULL', [(int) $ref]);
                    if (!$p) {
                        throw new BusinessRuleException("Line $n: choose a product.");
                    }
                    $it['product_id'] = (int) $p['id'];
                    $it['description'] = $p['name'];
                    break;
                default:
                    throw new BusinessRuleException("Line $n: choose an item type.");
            }
            $out[] = $it;
        }
        if (!$out) {
            throw new BusinessRuleException('Add at least one item.');
        }
        return $out;
    }

    /** Posts stock and money. Safe to call twice: the second call is refused under the row lock. */
    public static function confirm(int $id): void
    {
        DB::transaction(function () use ($id) {
            $s = DB::one('SELECT * FROM sales WHERE id=? FOR UPDATE', [$id]);
            if (!$s || $s['status'] !== 'draft') {
                throw new BusinessRuleException('Only draft invoices can be confirmed.');
            }
            Auth::assertFarm((int) $s['farm_id']);
            $farm = (int) $s['farm_id'];
            $date = $s['sale_date'];
            $items = DB::all('SELECT * FROM sale_items WHERE sale_id=? ORDER BY id', [$id]);
            $subtotal = (float) $s['subtotal'];
            $discount = (float) $s['discount'];
            $allocated = 0.0;

            foreach ($items as $idx => $it) {
                $iid = (int) $it['id'];
                $key = "sale_item:$iid";
                $qty = (float) $it['quantity'];
                $cost = 0.0;
                $costType = 'product_sold';
                switch ($it['item_type']) {
                    case 'bird':
                        $cost = InventoryService::moveBirds((int) $it['batch_id'], (int) $it['shed_id'], -(int) $qty, 'sale', $date, 'sale_item', $iid, $key);
                        $costType = 'birds_sold';
                        break;
                    case 'egg':
                        InventoryService::moveEggs($farm, -(int) $qty, 'sale', $date, 'sale_item', $iid, $key);
                        break;
                    case 'feed':
                        $cost = InventoryService::moveFeed($farm, (int) $it['feed_product_id'], -$qty, 'sale', $date, 'sale_item', $iid, $key);
                        $costType = 'feed_sold';
                        break;
                    case 'product':
                        $tracked = (int) DB::value('SELECT track_stock FROM products WHERE id=?', [$it['product_id']]);
                        if ($tracked) {
                            $cost = InventoryService::moveProduct((int) $it['product_id'], -$qty, 'sale', $date, 'sale_item', $iid, $key);
                        }
                        break;
                }
                // The invoice discount is shared across lines so batch revenue stays accurate; the last line takes the rounding.
                $share = $idx === count($items) - 1 ? $discount - $allocated : round($subtotal > 0 ? $discount * (float) $it['line_total'] / $subtotal : 0, 2);
                $allocated += $share;
                $opts = ['farm_id' => $farm, 'batch_id' => $it['batch_id'], 'description' => "{$s['invoice_no']} {$it['description']}"];
                FinanceService::post('revenue', (float) $it['line_total'] - $share, $date, 'sale', $id, "$key:revenue", [...$opts, 'sub_type' => $it['item_type']]);
                if ($cost > 0) {
                    FinanceService::post('cogs', $qty * $cost, $date, 'sale', $id, "$key:cogs", [...$opts, 'sub_type' => $costType]);
                }
            }
            FinanceService::post('tax_collected', (float) $s['tax_amount'], $date, 'sale', $id, "sale:$id:tax", ['farm_id' => $farm, 'description' => "{$s['invoice_no']} tax"]);
            DB::update('sales', ['status' => 'confirmed', 'confirmed_at' => date('Y-m-d H:i:s')], $id);
            Activity::log('confirm', 'sale', $id, $s['invoice_no'] . ' for ' . money($s['grand_total']));
        });
    }

    public static function cancel(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $s = DB::one('SELECT * FROM sales WHERE id=? FOR UPDATE', [$id]);
            if (!$s || $s['status'] === 'cancelled') {
                throw new BusinessRuleException('This invoice is already cancelled.');
            }
            Auth::assertFarm((int) $s['farm_id']);
            if ($s['status'] === 'confirmed') {
                PaymentService::voidAllFor('sale', $id, 'Invoice cancelled');
                foreach (DB::all('SELECT id FROM sale_items WHERE sale_id=?', [$id]) as $it) {
                    $iid = (int) $it['id'];
                    InventoryService::reverseBirds('sale_item', $iid);
                    InventoryService::reverseEggs('sale_item', $iid);
                    InventoryService::reverseFeed('sale_item', $iid);
                    InventoryService::reverseProducts('sale_item', $iid);
                }
                FinanceService::reverse('sale', $id);
            }
            DB::update('sales', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => $reason], $id);
            Activity::log('cancel', 'sale', $id, $s['invoice_no'] . ($reason ? ": $reason" : ''));
        });
    }
}
