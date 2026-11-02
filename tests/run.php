<?php
// Business-rule tests. Runs against its own throwaway database (poultrypro_test), never your real data.
// Usage: php tests/run.php
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/database/seeders/BaseSeeder.php';

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\BatchService;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use App\Services\InventoryService;
use App\Services\Lookup;
use App\Services\OperationsService as Ops;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\VaccinationService;

$_ENV['DB_DATABASE'] = 'poultrypro_test';
$c = config('database');
$root = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('DROP DATABASE IF EXISTS poultrypro_test');
$root->exec('CREATE DATABASE poultrypro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$root->exec('USE poultrypro_test');
foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', file_get_contents(BASE_PATH . '/database/schema.sql'))))) as $s) {
    $root->exec($s);
}
$admin = BaseSeeder::run();
Auth::actAs($admin);

$pass = 0;
$fail = [];
function ok(bool $cond, string $name): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
    } else {
        $fail[] = $name;
        echo "  FAIL: $name\n";
    }
}
function eq(mixed $a, mixed $b, string $name): void
{
    $same = is_numeric($a) && is_numeric($b) ? abs((float) $a - (float) $b) < 0.006 : $a === $b;
    ok($same, $same ? $name : "$name (got " . var_export($a, true) . ', expected ' . var_export($b, true) . ')');
}
function refused(callable $fn, string $name, string $class = BusinessRuleException::class): void
{
    try {
        $fn();
        ok(false, "$name (was allowed)");
    } catch (\Throwable $e) {
        ok($e instanceof $class, $e instanceof $class ? $name : "$name (threw " . get_class($e) . ': ' . $e->getMessage() . ')');
    }
}
function section(string $s): void
{
    echo "$s\n";
}
function birds(int $batch, ?int $shed = null): int
{
    return (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE batch_id=?' . ($shed ? " AND shed_id=$shed" : ''), [$batch]);
}
function shedCount(int $shed): int
{
    return (int) DB::value('SELECT current_bird_count FROM sheds WHERE id=?', [$shed]);
}
function feedKg(int $farm, int $p): float
{
    return (float) DB::value('SELECT COALESCE(quantity_kg,0) FROM feed_inventory WHERE farm_id=? AND feed_product_id=?', [$farm, $p]);
}
function eggs(int $farm): int
{
    return (int) DB::value('SELECT COALESCE(quantity,0) FROM egg_inventory WHERE farm_id=?', [$farm]);
}
function days(int $ago): string
{
    return date('Y-m-d', strtotime(sprintf('%+d days', -$ago)));
}

// -------------------------------------------------------------------- Fixtures
$farmA = DB::insert('farms', ['name' => 'Farm A', 'code' => 'A', 'capacity' => 10000]);
$farmB = DB::insert('farms', ['name' => 'Farm B', 'code' => 'B', 'capacity' => 10000]);
$shed1 = DB::insert('sheds', ['farm_id' => $farmA, 'name' => 'S1', 'shed_number' => '1', 'shed_type' => 'broiler', 'max_capacity' => 1000]);
$shed2 = DB::insert('sheds', ['farm_id' => $farmA, 'name' => 'S2', 'shed_number' => '2', 'shed_type' => 'broiler', 'max_capacity' => 300]);
$shed3 = DB::insert('sheds', ['farm_id' => $farmB, 'name' => 'S3', 'shed_number' => '1', 'shed_type' => 'layer', 'max_capacity' => 2000]);
$sup = DB::insert('suppliers', ['name' => 'Sup']);
$cust = DB::insert('customers', ['name' => 'Cust']);
$cash = DB::insert('accounts', ['name' => 'Cash', 'account_type' => 'cash', 'opening_balance' => 100000]);
$bank = DB::insert('accounts', ['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 0]);
$cat = DB::insert('feed_categories', ['name' => 'C']);
$kg = (int) DB::value("SELECT id FROM units WHERE symbol='kg'");
$bag = (int) DB::value("SELECT id FROM units WHERE symbol='bag50'");
$feed = DB::insert('feed_products', ['category_id' => $cat, 'default_unit_id' => $kg, 'name' => 'Feed', 'low_stock_kg' => 10]);
$mcat = DB::insert('medicine_categories', ['name' => 'M']);
$med = DB::insert('medicines', ['category_id' => $mcat, 'name' => 'Vax']);
$elec = (int) DB::value("SELECT id FROM expense_categories WHERE name='Electricity'");
$equipCat = (int) DB::value("SELECT id FROM expense_categories WHERE name='Equipment'");

// -------------------------------------------------------------------- Batches and capacity
section('Batches and shed capacity');
$b1 = BatchService::create(['name' => 'B1', 'batch_type' => 'broiler', 'shed_id' => $shed1, 'purchase_date' => days(30), 'initial_quantity' => 500]);
eq(birds($b1), 500, 'opening birds reach inventory');
eq(shedCount($shed1), 500, 'shed count follows inventory');
eq(DB::value("SELECT COUNT(*) FROM purchases"), 0, 'no price means no purchase is created');
refused(fn () => BatchService::create(['name' => 'Big', 'batch_type' => 'broiler', 'shed_id' => $shed1, 'purchase_date' => days(30), 'initial_quantity' => 501]), 'batch over shed capacity is refused');
eq(DB::value("SELECT COUNT(*) FROM batches WHERE name='Big'"), 0, 'refused batch leaves nothing behind (rollback)');
refused(fn () => BatchService::create(['name' => 'NoSup', 'batch_type' => 'broiler', 'shed_id' => $shed1, 'purchase_date' => days(30), 'initial_quantity' => 10, 'purchase_price' => 50]), 'price without a supplier is refused');

$b2 = BatchService::create(['name' => 'B2', 'batch_type' => 'broiler', 'shed_id' => $shed2, 'purchase_date' => days(30), 'initial_quantity' => 200, 'purchase_price' => 50, 'supplier_id' => $sup]);
eq(birds($b2), 200, 'priced batch adds birds once');
eq(DB::value("SELECT status FROM purchases ORDER BY id DESC LIMIT 1"), 'confirmed', 'priced batch creates a confirmed purchase');
eq(DB::value("SELECT SUM(quantity) FROM bird_transactions WHERE batch_id=$b2"), 200, 'priced batch has one purchase entry in the ledger');
eq(FinanceService::payables(), 10000, 'supplier is owed for the birds');
eq(DB::value("SELECT avg_bird_cost FROM batches WHERE id=$b2"), 50, 'bird cost recorded');

// -------------------------------------------------------------------- Mortality
section('Mortality and negative stock');
$flock1 = "$b1:$shed1";
$flock2 = "$b2:$shed2";
$m = Ops::recordMortality(['flock' => $flock2, 'record_date' => days(5), 'quantity' => 10]);
eq(birds($b2), 190, 'mortality reduces birds');
eq(shedCount($shed2), 190, 'mortality reduces shed count');
eq(FinanceService::profitAndLoss(null, null)['cogs'], 500, 'dead birds cost reaches the P&L at bird cost');
Ops::cancelMortality($m, 'mistake');
eq(birds($b2), 200, 'cancelling mortality restores birds');
eq(FinanceService::profitAndLoss(null, null)['cogs'], 0, 'cancelling mortality reverses its cost');
refused(fn () => Ops::cancelMortality($m), 'cancelling twice is refused');
eq(birds($b2), 200, 'second cancel changed nothing');
refused(fn () => Ops::recordMortality(['flock' => $flock2, 'record_date' => days(5), 'quantity' => 201]), 'mortality above stock is refused');
eq(birds($b2), 200, 'refused mortality changed nothing');
refused(fn () => Ops::recordMortality(['flock' => $flock2, 'record_date' => days(-1), 'quantity' => 1]), 'future date is refused');
refused(fn () => Ops::recordMortality(['flock' => $flock2, 'record_date' => days(60), 'quantity' => 1]), 'date before the batch arrived is refused');
$perf = BatchService::performance($b2);
eq($perf['dead'], 0, 'batch performance nets out the cancelled record');

// -------------------------------------------------------------------- Transfers and adjustments
section('Transfers and adjustments');
refused(fn () => Ops::transferBirds(['flock' => $flock1, 'to_shed_id' => $shed2, 'quantity' => 150, 'transfer_date' => days(1)]), 'transfer over destination capacity is refused');
eq(birds($b1, $shed1), 500, 'refused transfer rolled back the source shed');
$t = Ops::transferBirds(['flock' => $flock1, 'to_shed_id' => $shed2, 'quantity' => 100, 'transfer_date' => days(1)]);
eq(birds($b1, $shed1), 400, 'transfer leaves source');
eq(birds($b1, $shed2), 100, 'transfer reaches destination');
eq(birds($b1), 500, 'transfer does not change batch total');
eq(shedCount($shed2), 300, 'destination shed count updated');
refused(fn () => Ops::transferBirds(['flock' => $flock1, 'to_shed_id' => $shed1, 'quantity' => 1, 'transfer_date' => days(1)]), 'transfer to the same shed is refused');
Ops::cancelTransfer($t);
eq(birds($b1, $shed1), 500, 'cancelling a transfer restores the source');
eq(birds($b1, $shed2), 0, 'cancelling a transfer empties the destination');
$a = Ops::adjustBirds(['flock' => $flock1, 'direction' => 'reduction', 'quantity' => 5, 'adjustment_date' => days(1), 'reason' => 'count']);
eq(birds($b1), 495, 'approved reduction applied');
Ops::cancelAdjustment($a);
eq(birds($b1), 500, 'adjustment reversal applied');
$perf = BatchService::performance($b1);
eq($perf['opening'] + $perf['purchased'] + $perf['transfers_in'] - $perf['transfers_out'] - $perf['dead'] - $perf['sold'] + $perf['adjust_in'] - $perf['reductions'], $perf['current'], 'stock formula equals current birds');

// -------------------------------------------------------------------- Feed
section('Feed: units, cost and consumption');
$p1 = PurchaseService::saveDraft(['supplier_id' => $sup, 'farm_id' => $farmA, 'purchase_date' => days(20)], [['item_type' => 'feed', 'ref_id' => $feed, 'unit_id' => $bag, 'quantity' => 10, 'unit_price' => 2500]]);
eq(feedKg($farmA, $feed), 0, 'a draft purchase does not touch stock');
PurchaseService::confirm($p1);
eq(feedKg($farmA, $feed), 500, '10 bags of 50 kg arrive as 500 kg');
refused(fn () => PurchaseService::confirm($p1), 'confirming a purchase twice is refused');
eq(feedKg($farmA, $feed), 500, 'second confirm did not add stock');
eq(DB::value("SELECT COUNT(*) FROM feed_transactions WHERE feed_product_id=$feed"), 1, 'exactly one ledger row');
eq(DB::value("SELECT avg_cost_kg FROM feed_inventory WHERE farm_id=$farmA"), 50, 'cost per kg worked out from bags');
$pl = FinanceService::profitAndLoss(null, null);
eq($pl['cogs'] + $pl['operating_expenses'], 0, 'buying feed is not an expense in the P&L');
eq($pl['inventory_purchases'], 35000, 'buying feed is recorded as stock purchased');
$p2 = PurchaseService::saveDraft(['supplier_id' => $sup, 'farm_id' => $farmA, 'purchase_date' => days(19)], [['item_type' => 'feed', 'ref_id' => $feed, 'unit_id' => $kg, 'quantity' => 500, 'unit_price' => 70]]);
PurchaseService::confirm($p2);
eq(DB::value("SELECT avg_cost_kg FROM feed_inventory WHERE farm_id=$farmA"), 60, 'moving average cost');
$fc = Ops::recordFeed(['flock' => $flock1, 'record_date' => days(3), 'feed_product_id' => $feed, 'quantity' => 2, 'unit_id' => $bag, 'wastage_kg' => 5]);
eq(feedKg($farmA, $feed), 895, 'consumption and wastage reduce stock');
eq(DB::value("SELECT total_cost FROM feed_consumption WHERE id=$fc"), 6300, 'consumption costed at average');
$pl = FinanceService::profitAndLoss(null, null);
eq($pl['cogs'], 6300, 'feed reaches the P&L when consumed');
$perf = BatchService::performance($b1);
eq($perf['feed_kg'], 100, 'batch feed kg tracked');
eq($perf['feed_cost'], 6300, 'batch feed cost tracked');
refused(fn () => Ops::recordFeed(['flock' => $flock1, 'record_date' => days(3), 'feed_product_id' => $feed, 'quantity' => 2000, 'unit_id' => $kg]), 'consumption above stock is refused');
eq(DB::value('SELECT COUNT(*) FROM feed_consumption'), 1, 'refused consumption left no record');
$fc2 = Ops::recordFeed(['flock' => $flock1, 'record_date' => days(3), 'feed_product_id' => $feed, 'quantity' => 500, 'unit_id' => $kg]);
refused(fn () => PurchaseService::cancel($p2), 'cancelling a purchase whose stock is used is refused');
eq(DB::value("SELECT status FROM purchases WHERE id=$p2"), 'confirmed', 'refused cancel left the purchase confirmed');
eq(feedKg($farmA, $feed), 395, 'refused cancel left stock alone');
Ops::cancelFeed($fc2);
Ops::cancelFeed($fc);
eq(feedKg($farmA, $feed), 1000, 'cancelling consumption restores stock');
eq(FinanceService::profitAndLoss(null, null)['cogs'], 0, 'cancelling consumption reverses cost');
PurchaseService::cancel($p2);
eq(feedKg($farmA, $feed), 500, 'cancelling a purchase removes its stock');
eq(DB::value("SELECT avg_cost_kg FROM feed_inventory WHERE farm_id=$farmA"), 50, 'cancelling a purchase restores average cost');
eq(FinanceService::profitAndLoss(null, null)['inventory_purchases'], 35000, 'cancelling a purchase reverses stock value');

// -------------------------------------------------------------------- Eggs
section('Eggs');
$layer = BatchService::create(['name' => 'Layers', 'batch_type' => 'layer', 'shed_id' => $shed3, 'purchase_date' => days(30), 'initial_quantity' => 1000]);
$flock3 = "$layer:$shed3";
refused(fn () => Ops::recordEggs(['flock' => $flock1, 'record_date' => days(1), 'good_eggs' => 5]), 'broilers cannot lay eggs');
$e = Ops::recordEggs(['flock' => $flock3, 'record_date' => days(2), 'good_eggs' => 800, 'damaged_eggs' => 15, 'broken_eggs' => 5]);
eq(DB::value("SELECT total_eggs FROM egg_production WHERE id=$e"), 820, 'total worked out from the parts');
eq(eggs($farmB), 800, 'only good eggs enter stock');
refused(fn () => Ops::recordEggs(['flock' => $flock3, 'record_date' => days(2), 'good_eggs' => 800]), 'a second entry for the same flock and date is refused');
$adj = Ops::adjustEggs(['farm_id' => $farmB, 'adjustment_date' => days(1), 'quantity' => 30, 'reason' => 'dropped tray']);
eq(eggs($farmB), 770, 'damage adjustment removes eggs');
refused(fn () => Ops::adjustEggs(['farm_id' => $farmB, 'adjustment_date' => days(1), 'quantity' => 771, 'reason' => 'x']), 'adjustment above stock is refused');
Ops::cancelEggAdjustment($adj);
eq(eggs($farmB), 800, 'cancelling adjustment restores eggs');

// -------------------------------------------------------------------- Sales
section('Sales: confirm once, cancel fully');
$birdCostBefore = (float) DB::value("SELECT avg_bird_cost FROM batches WHERE id=$b2");
$cashBefore = FinanceService::accountBalance($cash);
$s = SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmA, 'sale_date' => days(1), 'discount' => 100, 'tax_rate' => 10], [
    ['item_type' => 'bird', 'ref_id' => $flock2, 'quantity' => 50, 'unit_price' => 100],
    ['item_type' => 'feed', 'ref_id' => $feed, 'quantity' => 100, 'unit_price' => 75],
]);
$sale = DB::one("SELECT * FROM sales WHERE id=$s");
eq($sale['subtotal'], 12500, 'subtotal');
eq($sale['tax_amount'], 1240, 'tax on the discounted amount');
eq($sale['grand_total'], 13640, 'grand total');
eq(birds($b2), 200, 'a draft invoice does not touch stock');
SaleService::confirm($s);
eq(birds($b2), 150, 'confirmed sale removes birds');
eq(feedKg($farmA, $feed), 400, 'confirmed sale removes feed');
refused(fn () => SaleService::confirm($s), 'confirming a sale twice is refused');
eq(birds($b2), 150, 'second confirm did not remove more');
eq(DB::value("SELECT COUNT(*) FROM bird_transactions WHERE reference_type='sale_item' AND batch_id=$b2"), 1, 'exactly one bird ledger row');
refused(fn () => InventoryService::moveBirds($b2, $shed2, -1, 'sale', days(1), 'sale_item', 99, 'x', null) && DB::requireTransaction(), 'posting outside a transaction is refused', \LogicException::class);
refused(function () use ($b2, $shed2, $s) {
    DB::transaction(function () use ($b2, $shed2, $s) {
        $iid = DB::value("SELECT id FROM sale_items WHERE sale_id=$s AND item_type='bird'");
        InventoryService::moveBirds($b2, $shed2, -1, 'sale', days(1), 'sale_item', (int) $iid, "sale_item:$iid");
    });
}, 'database rejects a duplicate posting of the same line', \PDOException::class);
eq(birds($b2), 150, 'rejected duplicate rolled back');
$pl = FinanceService::profitAndLoss(null, null);
eq($pl['revenue'], 12400, 'revenue is net of discount and excludes tax');
eq($pl['tax_collected'], 1240, 'tax tracked separately');
eq($pl['cogs'], 50 * 50 + 100 * 50, 'cost of birds and feed sold');
eq($pl['net_profit'], 12400 - 7500, 'net profit');
eq(DB::value("SELECT SUM(amount) FROM financial_transactions WHERE reference_type='sale' AND reference_id=$s AND entry_type='revenue'"), 12400, 'line revenue adds up after discount rounding');
$perf = BatchService::performance($b2);
ok($perf['revenue'] > 4900 && $perf['revenue'] < 5000, 'discount share lands on batch revenue');
refused(fn () => SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmA, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'bird', 'ref_id' => $flock2, 'quantity' => 1.5, 'unit_price' => 100]]), 'fractional birds are refused');
refused(fn () => SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmB, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'bird', 'ref_id' => $flock2, 'quantity' => 1, 'unit_price' => 100]]), 'selling another farm\'s flock is refused');
refused(fn () => SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmA, 'sale_date' => days(1), 'discount' => 99999, 'tax_rate' => 0], [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 1, 'unit_price' => 10]]), 'discount above subtotal is refused');
$oversell = SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmA, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [
    ['item_type' => 'feed', 'ref_id' => $feed, 'quantity' => 10, 'unit_price' => 75],
    ['item_type' => 'bird', 'ref_id' => $flock2, 'quantity' => 9999, 'unit_price' => 100],
]);
refused(fn () => SaleService::confirm($oversell), 'overselling is refused');
eq(feedKg($farmA, $feed), 400, 'a failed confirm rolls back its earlier lines');
eq(DB::value("SELECT status FROM sales WHERE id=$oversell"), 'draft', 'a failed confirm leaves the invoice as a draft');
SaleService::cancel($oversell);
eq(DB::value('SELECT COUNT(*) FROM bird_transactions WHERE reference_id=' . (int) DB::value("SELECT id FROM sale_items WHERE sale_id=$oversell AND item_type='bird'") . " AND reference_type='sale_item'"), 0, 'cancelling a draft posts nothing');

// -------------------------------------------------------------------- Payments
section('Payments');
refused(fn () => PaymentService::record(['doc_type' => 'sale', 'doc_id' => $s, 'amount' => 13641, 'account_id' => $cash, 'payment_date' => days(1)]), 'overpaying is refused');
$pay = PaymentService::record(['doc_type' => 'sale', 'doc_id' => $s, 'amount' => 5000, 'account_id' => $cash, 'payment_date' => days(1), 'method' => 'cash']);
eq(DB::value("SELECT payment_status FROM sales WHERE id=$s"), 'partial', 'part payment marks partial');
eq(FinanceService::accountBalance($cash), $cashBefore + 5000, 'payment reaches the account');
eq(FinanceService::receivables(), 8640, 'receivable reduced');
$pay2 = PaymentService::record(['doc_type' => 'sale', 'doc_id' => $s, 'amount' => 8640, 'account_id' => $bank, 'payment_date' => days(1), 'method' => 'bank_transfer']);
eq(DB::value("SELECT payment_status FROM sales WHERE id=$s"), 'paid', 'full payment marks paid');
PaymentService::void($pay2, 'bounced');
eq(DB::value("SELECT paid_amount FROM sales WHERE id=$s"), 5000, 'voiding reverses the paid amount');
eq(FinanceService::accountBalance($bank), 0, 'voiding reverses the account');
refused(fn () => PaymentService::void($pay2), 'voiding twice is refused');
refused(fn () => PaymentService::record(['doc_type' => 'sale', 'doc_id' => SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmA, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 1, 'unit_price' => 1]]), 'amount' => 1, 'account_id' => $cash, 'payment_date' => days(1)]), 'paying a draft is refused');

// -------------------------------------------------------------------- Cancel a paid sale
section('Cancelling a sale reverses everything');
SaleService::cancel($s, 'test');
eq(birds($b2), 200, 'birds returned');
eq(feedKg($farmA, $feed), 500, 'feed returned');
eq(FinanceService::accountBalance($cash), $cashBefore, 'cash returned');
eq(FinanceService::receivables(), 0, 'receivable cleared');
$pl = FinanceService::profitAndLoss(null, null);
eq($pl['revenue'], 0, 'revenue reversed');
eq($pl['cogs'], 0, 'cost reversed');
eq($pl['tax_collected'], 0, 'tax reversed');
eq(DB::value("SELECT avg_bird_cost FROM batches WHERE id=$b2"), $birdCostBefore, 'bird cost unchanged after round trip');
eq(DB::value("SELECT COUNT(*) FROM payments WHERE sale_id=$s AND status='posted'"), 0, 'payments voided');
refused(fn () => SaleService::cancel($s), 'cancelling twice is refused');
eq(birds($b2), 200, 'second cancel changed nothing');
eq(DB::value("SELECT COUNT(*) FROM bird_transactions WHERE reversal_of IS NOT NULL AND reference_type='sale_item'"), 1, 'history is kept, as a reversal row');

// -------------------------------------------------------------------- Eggs and products through sales
$es = SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmB, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'egg', 'ref_id' => (string) $layer, 'quantity' => 300, 'unit_price' => 25]]);
SaleService::confirm($es);
eq(eggs($farmB), 500, 'egg sale reduces stock');
eq(BatchService::performance($layer)['revenue'], 7500, 'egg revenue attributed to its batch');
$noeggs = SaleService::saveDraft(['customer_id' => $cust, 'farm_id' => $farmB, 'sale_date' => days(1), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 501, 'unit_price' => 25]]);
refused(fn () => SaleService::confirm($noeggs), 'selling more eggs than in stock is refused');
refused(fn () => Ops::cancelEggs($e), 'cancelling a collection whose eggs were sold is refused');
eq(eggs($farmB), 500, 'refused cancel left egg stock alone');

// -------------------------------------------------------------------- Medicine
section('Medicine: earliest expiry first, expired skipped');
$mp = PurchaseService::saveDraft(['supplier_id' => $sup, 'farm_id' => $farmA, 'purchase_date' => days(10)], [
    ['item_type' => 'medicine', 'ref_id' => $med, 'quantity' => 100, 'unit_price' => 2, 'lot_no' => 'LATE', 'expiry_date' => days(-200)],
    ['item_type' => 'medicine', 'ref_id' => $med, 'quantity' => 100, 'unit_price' => 4, 'lot_no' => 'SOON', 'expiry_date' => days(-20)],
    ['item_type' => 'medicine', 'ref_id' => $med, 'quantity' => 100, 'unit_price' => 9, 'lot_no' => 'OLD', 'expiry_date' => days(5)],
]);
PurchaseService::confirm($mp);
$u = Ops::recordMedicine(['flock' => $flock1, 'record_date' => days(1), 'medicine_id' => $med, 'quantity' => 150]);
$qty = fn ($lot) => (float) DB::value('SELECT quantity FROM medicine_lots WHERE lot_no=?', [$lot]);
eq($qty('SOON'), 0, 'lot expiring first used first');
eq($qty('LATE'), 50, 'then the next lot');
eq($qty('OLD'), 100, 'expired lot never used');
eq(DB::value("SELECT total_cost FROM medicine_usage WHERE id=$u"), 100 * 4 + 50 * 2, 'cost follows the lots used');
eq(BatchService::performance($b1)['medicine_cost'], 500, 'medicine cost reaches the batch');
refused(fn () => Ops::recordMedicine(['flock' => $flock1, 'record_date' => days(1), 'medicine_id' => $med, 'quantity' => 51]), 'using more than the unexpired stock is refused');
refused(fn () => PurchaseService::cancel($mp), 'cancelling received medicine that was used is refused');
Ops::cancelMedicine($u);
eq($qty('SOON'), 100, 'cancelling treatment restores the lot');
PurchaseService::cancel($mp);
eq($qty('LATE') + $qty('SOON') + $qty('OLD'), 0, 'cancelling the purchase empties the lots');

// -------------------------------------------------------------------- Vaccination
section('Vaccination');
$v = DB::insert('vaccinations', ['name' => 'V', 'medicine_id' => $med, 'batch_type' => 'broiler', 'age_days' => 3, 'repeat_days' => 30]);
$b5 = BatchService::create(['name' => 'B5', 'batch_type' => 'broiler', 'shed_id' => $shed1, 'purchase_date' => days(10), 'initial_quantity' => 50]);
$vr = (int) DB::value("SELECT id FROM vaccination_records WHERE batch_id=$b5");
ok($vr > 0, 'new batch is scheduled from the programme');
eq(DB::value("SELECT scheduled_date FROM vaccination_records WHERE id=$vr"), days(7), 'scheduled at the right age');
refused(fn () => VaccinationService::markGiven($vr, ['given_date' => days(1), 'doses' => 50]), 'no stock means the dose cannot be recorded');
$mp2 = PurchaseService::saveDraft(['supplier_id' => $sup, 'farm_id' => $farmA, 'purchase_date' => days(10)], [['item_type' => 'medicine', 'ref_id' => $med, 'quantity' => 100, 'unit_price' => 2, 'lot_no' => 'N', 'expiry_date' => days(-100)]]);
PurchaseService::confirm($mp2);
VaccinationService::markGiven($vr, ['given_date' => days(1), 'doses' => 50]);
eq($qty('N'), 50, 'giving a dose uses stock');
eq(DB::value("SELECT COUNT(*) FROM vaccination_records WHERE batch_id=$b5 AND status='scheduled'"), 1, 'repeat dose scheduled');
eq(DB::value("SELECT next_date FROM vaccination_records WHERE id=$vr"), days(-29), 'next date recorded');
refused(fn () => VaccinationService::markGiven($vr, ['given_date' => days(1), 'doses' => 50]), 'marking given twice is refused');
VaccinationService::cancel($vr);
eq($qty('N'), 100, 'cancelling a given dose restores stock');

// -------------------------------------------------------------------- Expenses and double counting
section('Expenses, salaries and double counting');
$before = FinanceService::profitAndLoss(null, null);
$x = ExpenseService::create(['expense_date' => days(2), 'category_id' => $elec, 'farm_id' => $farmA, 'description' => 'Power', 'amount' => 1000, 'paid_amount' => 400, 'account_id' => $cash]);
$after = FinanceService::profitAndLoss(null, null);
eq($after['operating_expenses'] - $before['operating_expenses'], 1000, 'operating expense hits the P&L in full, paid or not');
eq(FinanceService::unpaidExpenses(), 600, 'unpaid part tracked');
$cap = ExpenseService::create(['expense_date' => days(2), 'category_id' => $equipCat, 'description' => 'Fan', 'amount' => 5000]);
$after2 = FinanceService::profitAndLoss(null, null);
eq($after2['net_profit'], $after['net_profit'], 'capital expense does not reduce profit');
eq($after2['capex'] - $after['capex'], 5000, 'capital expense tracked separately');
refused(fn () => ExpenseService::create(['expense_date' => days(2), 'category_id' => $elec, 'description' => 'x', 'amount' => 10, 'paid_amount' => 20, 'account_id' => $cash]), 'paying more than the expense is refused');
refused(fn () => ExpenseService::create(['expense_date' => days(2), 'category_id' => $elec, 'description' => 'x', 'amount' => 10, 'paid_amount' => 5]), 'paying without an account is refused');
eq(DB::value("SELECT COUNT(*) FROM expenses WHERE description='x'"), 0, 'refused expenses left nothing behind');
ExpenseService::cancel($x);
eq(FinanceService::profitAndLoss(null, null)['operating_expenses'], $before['operating_expenses'], 'cancelling reverses the expense');
ExpenseService::cancel($cap);

$emp = DB::insert('employees', ['employee_code' => 'E1', 'name' => 'Emp', 'farm_id' => $farmA, 'salary' => 1000]);
$cb = FinanceService::accountBalance($cash);
$sal = ExpenseService::paySalary(['employee_id' => $emp, 'period' => '2026-01', 'basic_amount' => 1000, 'allowances' => 100, 'deductions' => 50, 'pay_date' => days(1), 'account_id' => $cash]);
eq(FinanceService::accountBalance($cash), $cb - 1050, 'salary leaves the account');
eq(DB::value("SELECT net_amount FROM salaries WHERE id=$sal"), 1050, 'net salary');
eq(DB::value("SELECT COUNT(*) FROM expenses WHERE description LIKE 'Salary%'"), 1, 'salary appears as exactly one expense');
refused(fn () => ExpenseService::paySalary(['employee_id' => $emp, 'period' => '2026-01', 'basic_amount' => 1000, 'pay_date' => days(1), 'account_id' => $cash]), 'paying the same month twice is refused');
refused(fn () => ExpenseService::cancel((int) DB::value("SELECT expense_id FROM salaries WHERE id=$sal")), 'salary expense cannot be cancelled on its own');
ExpenseService::cancelSalary($sal);
eq(FinanceService::accountBalance($cash), $cb, 'cancelling salary returns the money');
$sal2 = ExpenseService::paySalary(['employee_id' => $emp, 'period' => '2026-01', 'basic_amount' => 1000, 'pay_date' => days(1), 'account_id' => $cash]);
ok($sal2 > 0, 'a cancelled month can be paid again');
ExpenseService::cancelSalary($sal2);

$plTr = FinanceService::profitAndLoss(null, null);
$tr = FinanceService::transferBetweenAccounts(['from_account_id' => $cash, 'to_account_id' => $bank, 'transfer_date' => days(1), 'amount' => 700]);
eq(FinanceService::accountBalance($bank), 700, 'account transfer in');
eq(FinanceService::profitAndLoss(null, null)['net_profit'], $plTr['net_profit'], 'account transfer leaves profit alone');
FinanceService::cancelTransfer($tr);
eq(FinanceService::accountBalance($bank), 0, 'account transfer reversed');

// -------------------------------------------------------------------- Equipment and other purchase lines
$eq = PurchaseService::saveDraft(['supplier_id' => $sup, 'farm_id' => $farmA, 'purchase_date' => days(2)], [
    ['item_type' => 'equipment', 'description' => 'Drinkers', 'quantity' => 2, 'unit_price' => 500],
    ['item_type' => 'other', 'description' => 'Sawdust', 'quantity' => 1, 'unit_price' => 300],
]);
$bx = FinanceService::profitAndLoss(null, null);
PurchaseService::confirm($eq);
$ax = FinanceService::profitAndLoss(null, null);
eq($ax['capex'] - $bx['capex'], 1000, 'equipment line is capital');
eq($ax['operating_expenses'] - $bx['operating_expenses'], 300, 'other line is an expense');
eq($ax['inventory_purchases'], $bx['inventory_purchases'], 'neither is stock');
PurchaseService::cancel($eq);

// -------------------------------------------------------------------- Delete rules
$b6 = BatchService::create(['name' => 'B6', 'batch_type' => 'broiler', 'shed_id' => $shed1, 'purchase_date' => days(1), 'initial_quantity' => 10]);
$sc = shedCount($shed1);
BatchService::delete($b6);
eq(shedCount($shed1), $sc - 10, 'deleting a mistaken batch removes its birds');
refused(fn () => BatchService::delete($b1), 'a batch with history cannot be deleted', BusinessRuleException::class) ;

// -------------------------------------------------------------------- Farm scoping
section('Farm scoping and permissions');
$managerRole = (int) DB::value("SELECT id FROM roles WHERE slug='farm_manager'");
$staffRole = (int) DB::value("SELECT id FROM roles WHERE slug='farm_staff'");
$acctRole = (int) DB::value("SELECT id FROM roles WHERE slug='accountant'");
$viewRole = (int) DB::value("SELECT id FROM roles WHERE slug='viewer'");
$mgr = DB::insert('users', ['role_id' => $managerRole, 'name' => 'M', 'email' => 'm@t.test', 'password_hash' => 'x']);
$staff = DB::insert('users', ['role_id' => $staffRole, 'name' => 'S', 'email' => 's@t.test', 'password_hash' => 'x']);
$acct = DB::insert('users', ['role_id' => $acctRole, 'name' => 'A', 'email' => 'a@t.test', 'password_hash' => 'x']);
$view = DB::insert('users', ['role_id' => $viewRole, 'name' => 'V', 'email' => 'v@t.test', 'password_hash' => 'x']);
$unassigned = DB::insert('users', ['role_id' => $staffRole, 'name' => 'U', 'email' => 'u@t.test', 'password_hash' => 'x']);
DB::insert('user_farms', ['user_id' => $mgr, 'farm_id' => $farmA]);
DB::insert('user_farms', ['user_id' => $staff, 'farm_id' => $farmA]);

Auth::actAs($staff);
ok(Auth::can('mortality.manage'), 'staff can enter mortality');
ok(!Auth::can('sales.view'), 'staff cannot see sales');
ok(!Auth::can('finance.view'), 'staff cannot see financial figures');
ok(!Auth::can('users.view'), 'staff cannot see users');
$bc = birds($layer);
refused(fn () => Ops::recordMortality(['flock' => $flock3, 'record_date' => days(1), 'quantity' => 1]), 'staff cannot post to another farm', ForbiddenException::class);
eq(birds($layer), $bc, 'blocked post changed nothing');
ok(Lookup::has('flocks', $flock1), 'staff sees own farm flock');
ok(!Lookup::has('flocks', $flock3), 'staff does not see another farm flock');
ok(Lookup::has('farms', $farmA) && !Lookup::has('farms', $farmB), 'farm list limited to assigned farms');
$m2 = Ops::recordMortality(['flock' => $flock1, 'record_date' => days(1), 'quantity' => 1]);
ok($m2 > 0, 'staff can post to their own farm');
Auth::actAs($unassigned);
eq(Auth::farmSql('x'), ' AND 1=0', 'a user with no farms sees nothing');
refused(fn () => Ops::recordMortality(['flock' => $flock1, 'record_date' => days(1), 'quantity' => 1]), 'a user with no farms cannot post', ForbiddenException::class);
Auth::actAs($mgr);
ok(Auth::can('batches.manage') && Auth::can('sheds.manage'), 'manager runs batches and sheds');
ok(!Auth::can('purchases.view') && !Auth::can('finance.view'), 'manager has no finance access');
refused(fn () => BatchService::create(['name' => 'X', 'batch_type' => 'layer', 'shed_id' => $shed3, 'purchase_date' => days(1), 'initial_quantity' => 1]), 'manager cannot add a batch at another farm', ForbiddenException::class);
refused(fn () => Ops::cancelMortality($m), 'manager cannot act twice even in scope');
refused(fn () => SaleService::cancel($es), 'manager cannot cancel another farm invoice', ForbiddenException::class);
Auth::actAs($acct);
ok(Auth::can('sales.manage') && Auth::can('reports_financial.view'), 'accountant runs money');
ok(!Auth::can('batches.manage') && !Auth::can('mortality.manage'), 'accountant cannot change operations');
ok(Auth::farmIds() === null, 'accountant sees all farms');
Auth::actAs($view);
$manage = DB::value("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug LIKE '%.manage'", [$viewRole]);
eq($manage, 0, 'viewer has no write permission at all');
ok(!Auth::can('users.view') && !Auth::can('settings.view'), 'viewer cannot see users or settings');
Auth::actAs($admin);
ok(Auth::can('anything.at.all'), 'super admin passes every check');
DB::query("UPDATE users SET status='inactive' WHERE id=?", [$view]);
Auth::actAs($view);
ok(!Auth::check(), 'inactive users are signed out');
Auth::actAs($admin);

// -------------------------------------------------------------------- Security helpers
section('Input safety');
$dirty = '<p onclick="x()">ok</p><script>alert(1)</script><img src=x onerror=y><a href="javascript:alert(1)">l</a><a href="https://a.test">m</a><iframe src="//x"></iframe><svg onload=z></svg>';
$clean = sanitize_html($dirty);
ok(!preg_match('/script|onclick|onerror|onload|javascript:|iframe|<img|<svg/i', $clean), 'article HTML is sanitized');
ok(str_contains($clean, 'href="https://a.test"') && str_contains($clean, '<p>ok</p>'), 'safe HTML survives');
eq(e('<b>"\'&'), '&lt;b&gt;&quot;&#039;&amp;', 'output escaping');
refused(fn () => Validator::check(['a' => ''], ['a' => 'required']), 'required rule', ValidationException::class);
refused(fn () => Validator::check(['a' => 'x'], ['a' => 'email']), 'email rule', ValidationException::class);
refused(fn () => Validator::check(['a' => '2026-02-30'], ['a' => 'date']), 'impossible date refused', ValidationException::class);
refused(fn () => Validator::check(['a' => ['x']], ['a' => 'max:5']), 'array input refused', ValidationException::class);
refused(fn () => Validator::check(['a' => 'javascript:alert(1)'], ['a' => 'url']), 'script link refused', ValidationException::class);
eq(youtube_id('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ', 'video link parsed');
eq(youtube_id('https://evil.test/nope'), null, 'other links are not treated as video');

// -------------------------------------------------------------------- Whole-database reconciliation
section('Reconciliation of all ledgers');
require __DIR__ . '/reconcile.php';
foreach (reconcile() as [$name, $bad]) {
    eq($bad, 0, $name);
}

echo "\n$pass passed, " . count($fail) . " failed\n";
foreach ($fail as $f) {
    echo " - $f\n";
}
exit($fail ? 1 : 0);
