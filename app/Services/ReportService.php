<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Models\Party;

/**
 * Report definitions. Each one is either SQL ('sql', with filters already applied) or computed rows ('rows').
 * Filters here use bound parameters; farm scoping uses Auth::farmSql, which only ever inlines cast integers.
 */
class ReportService
{
    public static function definitions(): array
    {
        $fin = 'reports_financial.view';
        $ops = 'reports.view';
        return [
            'farm-performance' => ['title' => 'Farm performance', 'perm' => $ops, 'icon' => 'bi-buildings', 'filters' => ['date', 'farm'], 'build' => [self::class, 'farmPerformance']],
            'shed-performance' => ['title' => 'Shed performance', 'perm' => $ops, 'icon' => 'bi-house-door', 'filters' => ['date', 'farm'], 'build' => [self::class, 'shedPerformance']],
            'bird-inventory' => ['title' => 'Bird inventory', 'perm' => $ops, 'icon' => 'bi-clipboard-data', 'filters' => ['farm', 'batch'], 'build' => [self::class, 'birdInventory']],
            'batch-performance' => ['title' => 'Batch performance', 'perm' => $ops, 'icon' => 'bi-collection', 'filters' => ['farm', 'batch'], 'build' => [self::class, 'batchPerformance']],
            'mortality' => ['title' => 'Mortality', 'perm' => $ops, 'icon' => 'bi-heartbreak', 'filters' => ['date', 'farm', 'batch'], 'build' => [self::class, 'mortality']],
            'feed-consumption' => ['title' => 'Feed consumption', 'perm' => $ops, 'icon' => 'bi-basket', 'filters' => ['date', 'farm', 'batch'], 'build' => [self::class, 'feed']],
            'egg-production' => ['title' => 'Egg production', 'perm' => $ops, 'icon' => 'bi-egg', 'filters' => ['date', 'farm', 'batch'], 'build' => [self::class, 'eggs']],
            'medicine-usage' => ['title' => 'Medicine usage', 'perm' => $ops, 'icon' => 'bi-capsule', 'filters' => ['date', 'farm', 'batch'], 'build' => [self::class, 'medicine']],
            'employees' => ['title' => 'Employees', 'perm' => $ops, 'icon' => 'bi-person-badge', 'filters' => ['date', 'farm'], 'build' => [self::class, 'employees']],
            'purchases' => ['title' => 'Purchases', 'perm' => $fin, 'icon' => 'bi-cart-plus', 'filters' => ['date', 'farm', 'supplier'], 'build' => [self::class, 'purchases']],
            'sales' => ['title' => 'Sales', 'perm' => $fin, 'icon' => 'bi-receipt', 'filters' => ['date', 'farm', 'customer'], 'build' => [self::class, 'sales']],
            'expenses' => ['title' => 'Expenses', 'perm' => $fin, 'icon' => 'bi-wallet2', 'filters' => ['date', 'farm', 'batch'], 'build' => [self::class, 'expenses']],
            'customer-ledger' => ['title' => 'Customer ledger', 'perm' => $fin, 'icon' => 'bi-people', 'filters' => ['date', 'customer'], 'build' => [self::class, 'customerLedger']],
            'supplier-ledger' => ['title' => 'Supplier ledger', 'perm' => $fin, 'icon' => 'bi-truck', 'filters' => ['date', 'supplier'], 'build' => [self::class, 'supplierLedger']],
            'profit-loss' => ['title' => 'Profit and loss', 'perm' => $fin, 'icon' => 'bi-graph-up-arrow', 'filters' => ['date', 'farm'], 'build' => [self::class, 'profitLoss'], 'no_search' => true],
        ];
    }

    /** Validated filter values from the query string. */
    public static function filters(): array
    {
        $date = fn ($k) => ($v = Request::query($k)) && ($d = \DateTime::createFromFormat('Y-m-d', $v)) && $d->format('Y-m-d') === $v ? $v : null;
        $farm = Request::int('farm_id') ?: null;
        if ($farm) {
            Auth::assertFarm($farm);
        }
        return [
            'from' => $date('from'), 'to' => $date('to'), 'farm' => $farm, 'batch' => Request::int('batch_id') ?: null,
            'customer' => Request::int('customer_id') ?: null, 'supplier' => Request::int('supplier_id') ?: null,
        ];
    }

    /** Builds "AND col..." fragments and their params for the common filters. */
    private static function where(array $f, ?string $dateCol, ?string $farmCol, ?string $batchCol = null): array
    {
        $w = '';
        $p = [];
        if ($dateCol && $f['from']) {
            $w .= " AND $dateCol>=?";
            $p[] = $f['from'];
        }
        if ($dateCol && $f['to']) {
            $w .= " AND $dateCol<=?";
            $p[] = $f['to'];
        }
        if ($farmCol) {
            $w .= Auth::farmSql($farmCol);
            if ($f['farm']) {
                $w .= " AND $farmCol=?";
                $p[] = $f['farm'];
            }
        }
        if ($batchCol && $f['batch']) {
            $w .= " AND $batchCol=?";
            $p[] = $f['batch'];
        }
        return [$w, $p];
    }

    /** Date condition for a correlated subquery, with its params repeated at each use. */
    private static function range(array $f, string $col): array
    {
        $w = '';
        $p = [];
        if ($f['from']) {
            $w .= " AND $col>=?";
            $p[] = $f['from'];
        }
        if ($f['to']) {
            $w .= " AND $col<=?";
            $p[] = $f['to'];
        }
        return [$w, $p];
    }

    private static function showMoney(): bool
    {
        return can('reports_financial.view');
    }

    public static function farmPerformance(array $f): array
    {
        [$w, $p] = self::where($f, null, 'x.id');
        [$rm, $pm] = self::range($f, 'm.record_date');
        [$rc, $pc] = self::range($f, 'c.record_date');
        [$re, $pe] = self::range($f, 'g.record_date');
        [$rt, $pt] = self::range($f, 't.txn_date');
        $money = self::showMoney();
        $finCols = $money ? ", (SELECT COALESCE(SUM(t.amount),0) FROM financial_transactions t WHERE t.farm_id=x.id AND t.entry_type='revenue'$rt) revenue,
            (SELECT COALESCE(SUM(t.amount),0) FROM financial_transactions t WHERE t.farm_id=x.id AND t.entry_type IN ('cogs','opex')$rt) costs" : '';
        $params = [...$pm, ...$pc, ...$pe, ...($money ? [...$pt, ...$pt] : []), ...$p];
        $cols = [
            ['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Farm'],
            ['key' => 'shed_count', 'label' => 'Sheds', 'type' => 'number', 'total' => true], ['key' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'total' => true],
            ['key' => 'birds', 'label' => 'Birds', 'type' => 'number', 'total' => true], ['key' => 'dead', 'label' => 'Mortality', 'type' => 'number', 'total' => true],
            ['key' => 'feed_kg', 'label' => 'Feed used (kg)', 'type' => 'decimal', 'total' => true], ['key' => 'eggs', 'label' => 'Eggs', 'type' => 'number', 'total' => true],
        ];
        if ($money) {
            $cols[] = ['key' => 'revenue', 'label' => 'Revenue', 'type' => 'money', 'total' => true];
            $cols[] = ['key' => 'costs', 'label' => 'Costs', 'type' => 'money', 'total' => true];
        }
        return [
            'sql' => "SELECT x.code, x.name, x.capacity,
                (SELECT COUNT(*) FROM sheds s WHERE s.farm_id=x.id AND s.deleted_at IS NULL) shed_count,
                (SELECT COALESCE(SUM(i.quantity),0) FROM bird_inventory i WHERE i.farm_id=x.id) birds,
                (SELECT COALESCE(SUM(m.quantity),0) FROM mortality_records m WHERE m.farm_id=x.id AND m.status='posted'$rm) dead,
                (SELECT COALESCE(SUM(c.quantity_kg),0) FROM feed_consumption c WHERE c.farm_id=x.id AND c.status='posted'$rc) feed_kg,
                (SELECT COALESCE(SUM(g.total_eggs),0) FROM egg_production g WHERE g.farm_id=x.id AND g.status='posted'$re) eggs
                $finCols FROM farms x WHERE x.deleted_at IS NULL$w",
            'params' => $params, 'order' => 'name', 'columns' => $cols, 'search' => ['name', 'code'],
        ];
    }

    public static function shedPerformance(array $f): array
    {
        [$w, $p] = self::where($f, null, 'x.farm_id');
        [$rm, $pm] = self::range($f, 'm.record_date');
        [$rc, $pc] = self::range($f, 'c.record_date');
        [$re, $pe] = self::range($f, 'g.record_date');
        return [
            'sql' => "SELECT x.shed_number, x.name, a.name farm, x.shed_type, x.max_capacity, x.current_bird_count, ROUND(x.current_bird_count/x.max_capacity*100,1) used,
                (SELECT COALESCE(SUM(m.quantity),0) FROM mortality_records m WHERE m.shed_id=x.id AND m.status='posted'$rm) dead,
                (SELECT COALESCE(SUM(c.quantity_kg),0) FROM feed_consumption c WHERE c.shed_id=x.id AND c.status='posted'$rc) feed_kg,
                (SELECT COALESCE(SUM(g.total_eggs),0) FROM egg_production g WHERE g.shed_id=x.id AND g.status='posted'$re) eggs
                FROM sheds x JOIN farms a ON a.id=x.farm_id WHERE x.deleted_at IS NULL$w",
            'params' => [...$pm, ...$pc, ...$pe, ...$p], 'order' => 'farm, shed_number', 'search' => ['name', 'farm'],
            'columns' => [
                ['key' => 'farm', 'label' => 'Farm'], ['key' => 'shed_number', 'label' => 'No.'], ['key' => 'name', 'label' => 'Shed'], ['key' => 'shed_type', 'label' => 'Type', 'type' => 'label'],
                ['key' => 'max_capacity', 'label' => 'Capacity', 'type' => 'number', 'total' => true], ['key' => 'current_bird_count', 'label' => 'Birds', 'type' => 'number', 'total' => true], ['key' => 'used', 'label' => 'Used %', 'type' => 'decimal'],
                ['key' => 'dead', 'label' => 'Mortality', 'type' => 'number', 'total' => true], ['key' => 'feed_kg', 'label' => 'Feed (kg)', 'type' => 'decimal', 'total' => true], ['key' => 'eggs', 'label' => 'Eggs', 'type' => 'number', 'total' => true],
            ],
        ];
    }

    public static function birdInventory(array $f): array
    {
        [$w, $p] = self::where($f, null, 'i.farm_id', 'i.batch_id');
        return [
            'sql' => "SELECT b.batch_code, a.name farm, s.name shed,
                SUM(IF(t.txn_type='opening',t.quantity,0)) opening_q, SUM(IF(t.txn_type='purchase',t.quantity,0)) purchase_q, SUM(IF(t.txn_type='transfer_in',t.quantity,0)) tin_q,
                -SUM(IF(t.txn_type='transfer_out',t.quantity,0)) tout_q, -SUM(IF(t.txn_type='mortality',t.quantity,0)) dead_q, -SUM(IF(t.txn_type='sale',t.quantity,0)) sold_q,
                SUM(IF(t.txn_type='adjustment_in',t.quantity,0))+SUM(IF(t.txn_type='adjustment_out',t.quantity,0)) adj_q, i.quantity current_q
                FROM bird_inventory i JOIN batches b ON b.id=i.batch_id JOIN sheds s ON s.id=i.shed_id JOIN farms a ON a.id=i.farm_id
                LEFT JOIN bird_transactions t ON t.batch_id=i.batch_id AND t.shed_id=i.shed_id WHERE b.deleted_at IS NULL$w GROUP BY i.id",
            'params' => $p, 'order' => 'batch_code DESC, shed', 'search' => ['batch_code', 'farm', 'shed'],
            'columns' => [
                ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'shed', 'label' => 'Shed'], ['key' => 'opening_q', 'label' => 'Opening', 'type' => 'number', 'total' => true], ['key' => 'purchase_q', 'label' => 'Purchases', 'type' => 'number', 'total' => true],
                ['key' => 'tin_q', 'label' => 'Transfers in', 'type' => 'number', 'total' => true], ['key' => 'tout_q', 'label' => 'Transfers out', 'type' => 'number', 'total' => true], ['key' => 'dead_q', 'label' => 'Mortality', 'type' => 'number', 'total' => true],
                ['key' => 'sold_q', 'label' => 'Sales', 'type' => 'number', 'total' => true], ['key' => 'adj_q', 'label' => 'Adjustments', 'type' => 'number', 'total' => true], ['key' => 'current_q', 'label' => 'Current', 'type' => 'number', 'total' => true],
            ],
        ];
    }

    public static function batchPerformance(array $f): array
    {
        [$w, $p] = self::where($f, null, 'b.farm_id', 'b.id');
        $rows = DB::all("SELECT b.id, b.batch_code, b.name, b.batch_type, b.status, a.name farm, b.initial_quantity FROM batches b JOIN farms a ON a.id=b.farm_id WHERE b.deleted_at IS NULL$w ORDER BY b.id DESC", $p);
        $money = self::showMoney();
        foreach ($rows as &$r) {
            $x = BatchService::performance((int) $r['id']);
            $r += ['age_days' => $x['age_days'], 'current_q' => $x['current'], 'dead' => $x['dead'], 'mortality_pct' => $x['mortality_pct'], 'feed_kg' => $x['feed_kg'], 'eggs' => $x['eggs_total'],
                'medicine_cost' => $x['medicine_cost'], 'revenue' => $x['revenue'], 'costs' => $x['bird_cost'] + $x['feed_cost'] + $x['medicine_cost'] + $x['other_cost'], 'profit' => $x['profit']];
        }
        unset($r);
        $cols = [
            ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'batch_type', 'label' => 'Type', 'type' => 'label'], ['key' => 'status', 'label' => 'Status', 'type' => 'label'], ['key' => 'age_days', 'label' => 'Age', 'type' => 'number'],
            ['key' => 'initial_quantity', 'label' => 'Initial', 'type' => 'number', 'total' => true], ['key' => 'current_q', 'label' => 'Current', 'type' => 'number', 'total' => true], ['key' => 'dead', 'label' => 'Dead', 'type' => 'number', 'total' => true], ['key' => 'mortality_pct', 'label' => 'Mortality %', 'type' => 'decimal'],
            ['key' => 'feed_kg', 'label' => 'Feed (kg)', 'type' => 'decimal', 'total' => true], ['key' => 'eggs', 'label' => 'Eggs', 'type' => 'number', 'total' => true],
        ];
        if ($money) {
            array_push($cols, ['key' => 'revenue', 'label' => 'Revenue', 'type' => 'money', 'total' => true], ['key' => 'costs', 'label' => 'Costs', 'type' => 'money', 'total' => true], ['key' => 'profit', 'label' => 'Profit / loss', 'type' => 'money', 'total' => true]);
        }
        return ['rows' => $rows, 'columns' => $cols, 'search' => ['batch_code', 'name', 'farm']];
    }

    public static function mortality(array $f): array
    {
        [$w, $p] = self::where($f, 'x.record_date', 'x.farm_id', 'x.batch_id');
        return [
            'sql' => "SELECT x.record_date, a.name farm, s.name shed, b.batch_code, b.initial_quantity, x.quantity, r.name reason, x.notes FROM mortality_records x JOIN farms a ON a.id=x.farm_id JOIN sheds s ON s.id=x.shed_id JOIN batches b ON b.id=x.batch_id LEFT JOIN mortality_reasons r ON r.id=x.reason_id WHERE x.status='posted'$w",
            'params' => $p, 'order' => 'record_date DESC', 'search' => ['batch_code', 'farm', 'shed', 'reason'],
            'columns' => [['key' => 'record_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'shed', 'label' => 'Shed'], ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'quantity', 'label' => 'Dead birds', 'type' => 'number', 'total' => true], ['key' => 'reason', 'label' => 'Reason'], ['key' => 'notes', 'label' => 'Notes']],
        ];
    }

    public static function feed(array $f): array
    {
        [$w, $p] = self::where($f, 'x.record_date', 'x.farm_id', 'x.batch_id');
        $cols = [['key' => 'record_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'shed', 'label' => 'Shed'], ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'product', 'label' => 'Feed'], ['key' => 'quantity_kg', 'label' => 'Used (kg)', 'type' => 'decimal', 'total' => true], ['key' => 'wastage_kg', 'label' => 'Wasted (kg)', 'type' => 'decimal', 'total' => true]];
        if (self::showMoney()) {
            $cols[] = ['key' => 'total_cost', 'label' => 'Cost', 'type' => 'money', 'total' => true];
        }
        return [
            'sql' => "SELECT x.record_date, a.name farm, s.name shed, b.batch_code, p.name product, x.quantity_kg, x.wastage_kg, x.total_cost FROM feed_consumption x JOIN farms a ON a.id=x.farm_id JOIN sheds s ON s.id=x.shed_id JOIN batches b ON b.id=x.batch_id JOIN feed_products p ON p.id=x.feed_product_id WHERE x.status='posted'$w",
            'params' => $p, 'order' => 'record_date DESC', 'search' => ['batch_code', 'farm', 'shed', 'product'], 'columns' => $cols,
        ];
    }

    public static function eggs(array $f): array
    {
        [$w, $p] = self::where($f, 'x.record_date', 'x.farm_id', 'x.batch_id');
        return [
            'sql' => "SELECT x.record_date, a.name farm, s.name shed, b.batch_code, x.total_eggs, x.good_eggs, x.damaged_eggs, x.broken_eggs FROM egg_production x JOIN farms a ON a.id=x.farm_id JOIN sheds s ON s.id=x.shed_id JOIN batches b ON b.id=x.batch_id WHERE x.status='posted'$w",
            'params' => $p, 'order' => 'record_date DESC', 'search' => ['batch_code', 'farm', 'shed'],
            'columns' => [['key' => 'record_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'shed', 'label' => 'Shed'], ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'total_eggs', 'label' => 'Total', 'type' => 'number', 'total' => true], ['key' => 'good_eggs', 'label' => 'Good', 'type' => 'number', 'total' => true], ['key' => 'damaged_eggs', 'label' => 'Damaged', 'type' => 'number', 'total' => true], ['key' => 'broken_eggs', 'label' => 'Broken', 'type' => 'number', 'total' => true]],
        ];
    }

    public static function medicine(array $f): array
    {
        [$w, $p] = self::where($f, 'x.record_date', 'x.farm_id', 'x.batch_id');
        $cols = [['key' => 'record_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'medicine', 'label' => 'Medicine'], ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'decimal'], ['key' => 'unit', 'label' => 'Unit'], ['key' => 'diagnosis', 'label' => 'Diagnosis'], ['key' => 'veterinarian', 'label' => 'Veterinarian']];
        if (self::showMoney()) {
            $cols[] = ['key' => 'total_cost', 'label' => 'Cost', 'type' => 'money', 'total' => true];
        }
        return [
            'sql' => "SELECT x.record_date, a.name farm, b.batch_code, m.name medicine, x.quantity, m.unit, x.diagnosis, x.veterinarian, x.total_cost FROM medicine_usage x JOIN farms a ON a.id=x.farm_id JOIN batches b ON b.id=x.batch_id JOIN medicines m ON m.id=x.medicine_id WHERE x.status='posted'$w",
            'params' => $p, 'order' => 'record_date DESC', 'search' => ['batch_code', 'farm', 'medicine', 'veterinarian'], 'columns' => $cols,
        ];
    }

    public static function employees(array $f): array
    {
        [$w, $p] = self::where($f, null, 'x.farm_id');
        [$ra, $pa] = self::range($f, 'n.attendance_date');
        [$rs, $ps] = self::range($f, 'y.pay_date');
        $money = can('salaries.view');
        $cols = [['key' => 'employee_code', 'label' => 'ID'], ['key' => 'name', 'label' => 'Name'], ['key' => 'designation', 'label' => 'Designation'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'status', 'label' => 'Status', 'type' => 'label'], ['key' => 'present_days', 'label' => 'Present', 'type' => 'number'], ['key' => 'absent_days', 'label' => 'Absent', 'type' => 'number']];
        if ($money) {
            array_push($cols, ['key' => 'salary', 'label' => 'Monthly salary', 'type' => 'money'], ['key' => 'paid', 'label' => 'Salary paid', 'type' => 'money', 'total' => true]);
        }
        return [
            'sql' => "SELECT x.employee_code, x.name, x.designation, a.name farm, x.status, x.salary,
                (SELECT COUNT(*) FROM attendance n WHERE n.employee_id=x.id AND n.status='present'$ra) present_days,
                (SELECT COUNT(*) FROM attendance n WHERE n.employee_id=x.id AND n.status='absent'$ra) absent_days,
                (SELECT COALESCE(SUM(y.net_amount),0) FROM salaries y WHERE y.employee_id=x.id AND y.status='posted'$rs) paid
                FROM employees x LEFT JOIN farms a ON a.id=x.farm_id WHERE x.deleted_at IS NULL$w",
            'params' => [...$pa, ...$pa, ...$ps, ...$p], 'order' => 'name', 'search' => ['name', 'employee_code', 'designation', 'farm'], 'columns' => $cols,
        ];
    }

    public static function purchases(array $f): array
    {
        [$w, $p] = self::where($f, 'x.purchase_date', 'x.farm_id');
        if ($f['supplier']) {
            $w .= ' AND x.supplier_id=?';
            $p[] = $f['supplier'];
        }
        return [
            'sql' => "SELECT x.purchase_no, x.purchase_date, s.name supplier, a.name farm, x.total_amount, x.paid_amount, (x.total_amount-x.paid_amount) balance, x.payment_status FROM purchases x JOIN suppliers s ON s.id=x.supplier_id JOIN farms a ON a.id=x.farm_id WHERE x.status='confirmed'$w",
            'params' => $p, 'order' => 'purchase_date DESC, purchase_no DESC', 'search' => ['purchase_no', 'supplier', 'farm'],
            'columns' => [['key' => 'purchase_no', 'label' => 'No.'], ['key' => 'purchase_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'supplier', 'label' => 'Supplier'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'total_amount', 'label' => 'Total', 'type' => 'money', 'total' => true], ['key' => 'paid_amount', 'label' => 'Paid', 'type' => 'money', 'total' => true], ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'total' => true], ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'label']],
        ];
    }

    public static function sales(array $f): array
    {
        [$w, $p] = self::where($f, 'x.sale_date', 'x.farm_id');
        if ($f['customer']) {
            $w .= ' AND x.customer_id=?';
            $p[] = $f['customer'];
        }
        return [
            'sql' => "SELECT x.invoice_no, x.sale_date, c.name customer, a.name farm, x.subtotal, x.discount, x.tax_amount, x.grand_total, x.paid_amount, (x.grand_total-x.paid_amount) balance, x.payment_status FROM sales x JOIN customers c ON c.id=x.customer_id JOIN farms a ON a.id=x.farm_id WHERE x.status='confirmed'$w",
            'params' => $p, 'order' => 'sale_date DESC, invoice_no DESC', 'search' => ['invoice_no', 'customer', 'farm'],
            'columns' => [['key' => 'invoice_no', 'label' => 'Invoice'], ['key' => 'sale_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'customer', 'label' => 'Customer'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'total' => true], ['key' => 'discount', 'label' => 'Discount', 'type' => 'money', 'total' => true], ['key' => 'tax_amount', 'label' => 'Tax', 'type' => 'money', 'total' => true], ['key' => 'grand_total', 'label' => 'Total', 'type' => 'money', 'total' => true], ['key' => 'paid_amount', 'label' => 'Paid', 'type' => 'money', 'total' => true], ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'total' => true]],
        ];
    }

    public static function expenses(array $f): array
    {
        [$w, $p] = self::where($f, 'x.expense_date', 'x.farm_id', 'x.batch_id');
        return [
            'sql' => "SELECT x.expense_date, c.name category, c.expense_type, x.description, a.name farm, b.batch_code, x.amount, x.paid_amount FROM expenses x JOIN expense_categories c ON c.id=x.category_id LEFT JOIN farms a ON a.id=x.farm_id LEFT JOIN batches b ON b.id=x.batch_id WHERE x.status='posted'$w",
            'params' => $p, 'order' => 'expense_date DESC', 'search' => ['category', 'description', 'farm'],
            'columns' => [['key' => 'expense_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'category', 'label' => 'Category'], ['key' => 'expense_type', 'label' => 'Type', 'type' => 'label'], ['key' => 'description', 'label' => 'Description'], ['key' => 'farm', 'label' => 'Farm'], ['key' => 'batch_code', 'label' => 'Batch'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true], ['key' => 'paid_amount', 'label' => 'Paid', 'type' => 'money', 'total' => true]],
        ];
    }

    private static function ledger(array $f, string $kind): array
    {
        $isCustomer = $kind === 'customer';
        $id = $f[$kind];
        $columns = [['key' => 'd', 'label' => 'Date', 'type' => 'date'], ['key' => 'ref', 'label' => 'Reference'], ['key' => 'kind', 'label' => 'Type'], ['key' => 'debit', 'label' => $isCustomer ? 'Invoiced' : 'Purchased', 'type' => 'money', 'total' => true], ['key' => 'credit', 'label' => 'Paid', 'type' => 'money', 'total' => true], ['key' => 'balance', 'label' => 'Balance', 'type' => 'money']];
        if (!$id) {
            // No party chosen: one summary row per party.
            $sql = $isCustomer
                ? "SELECT c.name party, COALESCE(SUM(x.grand_total),0) debit, COALESCE(SUM(x.paid_amount),0) credit, COALESCE(SUM(x.grand_total-x.paid_amount),0) balance FROM customers c LEFT JOIN sales x ON x.customer_id=c.id AND x.status='confirmed' WHERE c.deleted_at IS NULL GROUP BY c.id"
                : "SELECT c.name party, COALESCE(SUM(x.total_amount),0) debit, COALESCE(SUM(x.paid_amount),0) credit, COALESCE(SUM(x.total_amount-x.paid_amount),0) balance FROM suppliers c LEFT JOIN purchases x ON x.supplier_id=c.id AND x.status='confirmed' WHERE c.deleted_at IS NULL GROUP BY c.id";
            return ['sql' => $sql, 'params' => [], 'order' => 'party', 'search' => ['party'], 'note' => 'All time, one line per ' . $kind . '. Choose a ' . $kind . ' to see the full ledger.',
                'columns' => [['key' => 'party', 'label' => ucfirst($kind)], ['key' => 'debit', 'label' => $isCustomer ? 'Invoiced' : 'Purchased', 'type' => 'money', 'total' => true], ['key' => 'credit', 'label' => 'Paid', 'type' => 'money', 'total' => true], ['key' => 'balance', 'label' => 'Outstanding', 'type' => 'money', 'total' => true]]];
        }
        $l = $isCustomer ? Party::customerLedger($id, $f['from'], $f['to']) : Party::supplierLedger($id, $f['from'], $f['to']);
        return ['rows' => $l['rows'], 'columns' => $columns, 'search' => ['ref', 'kind'],
            'note' => 'Opening balance ' . money($l['opening']) . ', closing balance ' . money($l['closing']) . '.'];
    }

    public static function customerLedger(array $f): array
    {
        return self::ledger($f, 'customer');
    }

    public static function supplierLedger(array $f): array
    {
        return self::ledger($f, 'supplier');
    }

    public static function profitLoss(array $f): array
    {
        $pl = FinanceService::profitAndLoss($f['from'], $f['to'], $f['farm']);
        $rows = [['line' => 'Revenue', 'amount' => $pl['revenue'], 'bold' => true]];
        foreach ($pl['cogs_lines'] as $c) {
            $rows[] = ['line' => '  Less: ' . self::costLabel($c['sub_type']), 'amount' => -$c['amt']];
        }
        $rows[] = ['line' => 'Gross profit', 'amount' => $pl['gross_profit'], 'bold' => true];
        foreach ($pl['opex_lines'] as $c) {
            $rows[] = ['line' => '  Less: ' . $c['name'], 'amount' => -$c['amt']];
        }
        $rows[] = ['line' => 'Net profit', 'amount' => $pl['net_profit'], 'bold' => true];
        $rows[] = ['line' => 'Not part of profit', 'amount' => null, 'bold' => true];
        $rows[] = ['line' => '  Stock purchased (cost is counted when used or sold)', 'amount' => $pl['inventory_purchases']];
        $rows[] = ['line' => '  Capital spending', 'amount' => $pl['capex']];
        $rows[] = ['line' => '  Sales tax collected', 'amount' => $pl['tax_collected']];
        return ['rows' => $rows, 'columns' => [['key' => 'line', 'label' => 'Line'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money']], 'statement' => true];
    }

    public static function costLabel(?string $sub): string
    {
        return [
            'birds_sold' => 'Cost of birds sold', 'bird_mortality' => 'Cost of birds lost to mortality', 'bird_adjustment' => 'Cost of approved bird reductions', 'feed_consumed' => 'Feed consumed',
            'feed_wastage' => 'Feed wasted', 'feed_sold' => 'Cost of feed sold', 'medicine' => 'Medicine and vaccines used', 'product_sold' => 'Cost of products sold',
        ][$sub] ?? label_of($sub);
    }

    /** Runs a report: returns columns, the page of rows, totals across all rows, and the count. */
    public static function run(array $def, array $f, ?int $page, int $perPage = 25): array
    {
        $b = $def['build']($f);
        $q = (string) Request::query('q', '');
        $searchCols = $b['search'] ?? [];
        $totals = [];
        $totalCols = array_values(array_filter($b['columns'], fn ($c) => !empty($c['total'])));

        if (isset($b['rows'])) {
            $rows = $b['rows'];
            if ($q !== '' && $searchCols) {
                $rows = array_values(array_filter($rows, function ($r) use ($q, $searchCols) {
                    foreach ($searchCols as $c) {
                        if (mb_stripos((string) ($r[$c] ?? ''), $q) !== false) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
            foreach ($totalCols as $c) {
                $totals[$c['key']] = array_sum(array_column($rows, $c['key']));
            }
            $count = count($rows);
            $slice = $page ? array_slice($rows, ($page - 1) * $perPage, $perPage) : $rows;
        } else {
            $where = '';
            $params = $b['params'];
            if ($q !== '' && $searchCols) {
                $like = '%' . addcslashes($q, '%_\\') . '%';
                $where = ' WHERE (' . implode(' OR ', array_map(fn ($c) => "r.`$c` LIKE ?", $searchCols)) . ')';
                array_push($params, ...array_fill(0, count($searchCols), $like));
            }
            $base = "SELECT * FROM ({$b['sql']}) r$where";
            $count = (int) DB::value("SELECT COUNT(*) FROM ($base) c", $params);
            if ($totalCols) {
                $sel = implode(',', array_map(fn ($c) => "COALESCE(SUM(`{$c['key']}`),0) `{$c['key']}`", $totalCols));
                $totals = DB::one("SELECT $sel FROM ($base) c", $params);
            }
            $limit = $page ? ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage) : ' LIMIT 10000';
            $slice = DB::all("$base ORDER BY {$b['order']}$limit", $params);
        }
        return ['columns' => $b['columns'], 'rows' => $slice, 'totals' => $totals, 'count' => $count, 'note' => $b['note'] ?? null, 'statement' => !empty($b['statement'])];
    }
}
