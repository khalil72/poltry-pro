<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\Validator;

class PurchaseService
{
    /** Creates or replaces a draft (an open purchase order). Stock and money are untouched until it is confirmed. */
    public static function saveDraft(array $h, array $lines, ?int $id = null): int
    {
        $h = Validator::check($h, [
            'supplier_id' => 'required|int', 'farm_id' => 'required|int', 'purchase_date' => 'required|date',
            'supplier_invoice_no' => 'max:60', 'notes' => 'max:500',
        ]);
        Auth::assertFarm((int) $h['farm_id']);
        $items = self::normalizeLines($lines, (int) $h['farm_id']);
        $total = round(array_sum(array_column($items, 'line_total')), 2);

        return DB::transaction(function () use ($h, $items, $total, $id) {
            if ($id) {
                $cur = DB::one('SELECT status, farm_id FROM purchases WHERE id=? FOR UPDATE', [$id]);
                if (!$cur || $cur['status'] !== 'draft') {
                    throw new BusinessRuleException('Only draft purchases can be edited.');
                }
                Auth::assertFarm((int) $cur['farm_id']);
                DB::update('purchases', [...$h, 'total_amount' => $total], $id);
                DB::query('DELETE FROM purchase_items WHERE purchase_id=?', [$id]);
            } else {
                $id = DB::insert('purchases', [...$h, 'purchase_no' => 'TMP' . bin2hex(random_bytes(6)), 'total_amount' => $total, 'created_by' => Auth::id()]);
                DB::update('purchases', ['purchase_no' => 'PUR-' . date('Y', strtotime($h['purchase_date'])) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)], $id);
            }
            foreach ($items as $it) {
                DB::insert('purchase_items', [...$it, 'purchase_id' => $id]);
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
            $it = [
                'item_type' => $type, 'batch_id' => null, 'feed_product_id' => null, 'unit_id' => null, 'medicine_id' => null, 'product_id' => null,
                'lot_no' => ($l['lot_no'] ?? '') ?: null, 'expiry_date' => ($l['expiry_date'] ?? '') ?: null,
                'quantity' => $qty, 'unit_price' => $price, 'line_total' => round($qty * $price, 2),
            ];
            $ref = (int) ($l['ref_id'] ?? 0);
            switch ($type) {
                case 'chick':
                    if ($qty != (int) $qty) {
                        throw new BusinessRuleException("Line $n: birds must be a whole number.");
                    }
                    $b = DB::one("SELECT id, batch_code, farm_id FROM batches WHERE id=? AND status='active' AND deleted_at IS NULL", [$ref]);
                    if (!$b || (int) $b['farm_id'] !== $farmId) {
                        throw new BusinessRuleException("Line $n: choose an active batch at this farm.");
                    }
                    $it['batch_id'] = $ref;
                    $it['description'] = "Birds for batch {$b['batch_code']}";
                    break;
                case 'feed':
                    $f = DB::one('SELECT id, name, default_unit_id FROM feed_products WHERE id=? AND deleted_at IS NULL', [$ref]);
                    if (!$f) {
                        throw new BusinessRuleException("Line $n: choose a feed product.");
                    }
                    $it['feed_product_id'] = $ref;
                    $it['unit_id'] = (int) ($l['unit_id'] ?? 0) ?: (int) $f['default_unit_id'];
                    $u = DB::one('SELECT symbol FROM units WHERE id=? AND deleted_at IS NULL', [$it['unit_id']]);
                    if (!$u) {
                        throw new BusinessRuleException("Line $n: choose a unit.");
                    }
                    $it['description'] = "{$f['name']} ({$u['symbol']})";
                    break;
                case 'medicine':
                    $m = DB::one('SELECT id, name FROM medicines WHERE id=? AND deleted_at IS NULL', [$ref]);
                    if (!$m) {
                        throw new BusinessRuleException("Line $n: choose a medicine.");
                    }
                    $it['medicine_id'] = $ref;
                    $it['description'] = $m['name'];
                    break;
                case 'product':
                    $p = DB::one('SELECT id, name FROM products WHERE id=? AND track_stock=1 AND deleted_at IS NULL', [$ref]);
                    if (!$p) {
                        throw new BusinessRuleException("Line $n: choose a product with stock tracking switched on.");
                    }
                    $it['product_id'] = $ref;
                    $it['description'] = $p['name'];
                    break;
                case 'equipment':
                case 'other':
                    $it['description'] = trim((string) ($l['description'] ?? ''));
                    if ($it['description'] === '') {
                        throw new BusinessRuleException("Line $n: describe what was bought.");
                    }
                    break;
                default:
                    throw new BusinessRuleException("Line $n: choose an item type.");
            }
            $it['description'] = mb_substr($it['description'], 0, 255);
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
            $p = DB::one('SELECT * FROM purchases WHERE id=? FOR UPDATE', [$id]);
            if (!$p || $p['status'] !== 'draft') {
                throw new BusinessRuleException('Only draft purchases can be confirmed.');
            }
            Auth::assertFarm((int) $p['farm_id']);
            $farm = (int) $p['farm_id'];
            $date = $p['purchase_date'];
            foreach (DB::all('SELECT * FROM purchase_items WHERE purchase_id=?', [$id]) as $it) {
                $iid = (int) $it['id'];
                $key = "purchase_item:$iid";
                $qty = (float) $it['quantity'];
                $opts = ['farm_id' => $farm, 'description' => "{$p['purchase_no']} {$it['description']}"];
                switch ($it['item_type']) {
                    case 'chick':
                        $batch = DB::one('SELECT shed_id FROM batches WHERE id=?', [$it['batch_id']]);
                        InventoryService::moveBirds((int) $it['batch_id'], (int) $batch['shed_id'], (int) $qty, 'purchase', $date, 'purchase_item', $iid, $key, (float) $it['unit_price']);
                        FinanceService::post('inventory_purchase', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'chick', 'batch_id' => $it['batch_id']]);
                        break;
                    case 'feed':
                        $factor = (float) DB::value('SELECT kg_factor FROM units WHERE id=?', [$it['unit_id']]);
                        $kg = $qty * $factor;
                        InventoryService::moveFeed($farm, (int) $it['feed_product_id'], $kg, 'purchase', $date, 'purchase_item', $iid, $key, (float) $it['line_total'] / $kg);
                        FinanceService::post('inventory_purchase', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'feed']);
                        break;
                    case 'medicine':
                        InventoryService::addMedicineLot($farm, (int) $it['medicine_id'], $qty, (float) $it['unit_price'], $it['lot_no'], $it['expiry_date'], $date, 'purchase_item', $iid, $key);
                        FinanceService::post('inventory_purchase', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'medicine']);
                        break;
                    case 'product':
                        InventoryService::moveProduct((int) $it['product_id'], $qty, 'purchase', $date, 'purchase_item', $iid, $key, (float) $it['unit_price']);
                        FinanceService::post('inventory_purchase', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'product']);
                        break;
                    case 'equipment':
                        FinanceService::post('capex', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'equipment']);
                        break;
                    case 'other':
                        $cat = DB::value("SELECT id FROM expense_categories WHERE name='Other Expenses'");
                        FinanceService::post('opex', (float) $it['line_total'], $date, 'purchase', $id, "$key:fin", [...$opts, 'sub_type' => 'purchase', 'category_id' => $cat ?: null]);
                        break;
                }
            }
            DB::update('purchases', ['status' => 'confirmed', 'confirmed_at' => date('Y-m-d H:i:s')], $id);
            Activity::log('confirm', 'purchase', $id, $p['purchase_no'] . ' for ' . money($p['total_amount']));
        });
    }

    /** Undoes the stock, money and payments of a purchase. Fails if the stock has already been used. */
    public static function cancel(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $p = DB::one('SELECT * FROM purchases WHERE id=? FOR UPDATE', [$id]);
            if (!$p || $p['status'] === 'cancelled') {
                throw new BusinessRuleException('This purchase is already cancelled.');
            }
            Auth::assertFarm((int) $p['farm_id']);
            if ($p['status'] === 'confirmed') {
                PaymentService::voidAllFor('purchase', $id, 'Purchase cancelled');
                foreach (DB::all('SELECT id FROM purchase_items WHERE purchase_id=?', [$id]) as $it) {
                    $iid = (int) $it['id'];
                    InventoryService::reverseBirds('purchase_item', $iid);
                    InventoryService::reverseFeed('purchase_item', $iid);
                    InventoryService::reverseMedicine('purchase_item', $iid);
                    InventoryService::reverseProducts('purchase_item', $iid);
                }
                FinanceService::reverse('purchase', $id);
            }
            DB::update('purchases', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => $reason], $id);
            Activity::log('cancel', 'purchase', $id, $p['purchase_no'] . ($reason ? ": $reason" : ''));
        });
    }
}
