<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database as DB;

/** Every number on the dashboard is queried from the ledgers. Nothing is typed in or cached. */
class DashboardService
{
    private static function scope(string $col, ?int $farm): string
    {
        return Auth::farmSql($col) . ($farm ? " AND $col=" . $farm : '');
    }

    public static function stats(?int $farm, string $from, string $to): array
    {
        $birds = (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE 1=1' . self::scope('farm_id', $farm));
        $mort = DB::one("SELECT COALESCE(SUM(b.initial_quantity),0) initial, COALESCE((SELECT SUM(m.quantity) FROM mortality_records m JOIN batches x ON x.id=m.batch_id WHERE m.status='posted' AND x.status='active' AND x.deleted_at IS NULL" . self::scope('x.farm_id', $farm) . "),0) dead
            FROM batches b WHERE b.status='active' AND b.deleted_at IS NULL" . self::scope('b.farm_id', $farm));
        $out = [
            'farms' => (int) DB::value('SELECT COUNT(*) FROM farms WHERE deleted_at IS NULL' . self::scope('id', $farm)),
            'sheds' => (int) DB::value('SELECT COUNT(*) FROM sheds WHERE deleted_at IS NULL' . self::scope('farm_id', $farm)),
            'birds' => $birds,
            'active_batches' => (int) DB::value("SELECT COUNT(*) FROM batches WHERE status='active' AND deleted_at IS NULL" . self::scope('farm_id', $farm)),
            'customers' => (int) DB::value('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
            'suppliers' => (int) DB::value('SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL'),
            'feed_kg' => (float) DB::value('SELECT COALESCE(SUM(quantity_kg),0) FROM feed_inventory WHERE 1=1' . self::scope('farm_id', $farm)),
            'eggs_today' => (int) DB::value("SELECT COALESCE(SUM(total_eggs),0) FROM egg_production WHERE status='posted' AND record_date=CURDATE()" . self::scope('farm_id', $farm)),
            'eggs_stock' => (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM egg_inventory WHERE 1=1' . self::scope('farm_id', $farm)),
            'mortality_pct' => $mort['initial'] > 0 ? round($mort['dead'] / $mort['initial'] * 100, 2) : 0,
        ];
        if (can('finance.view')) {
            $pl = FinanceService::profitAndLoss($from, $to, $farm);
            $out += ['sales' => $pl['revenue'], 'expenses' => $pl['operating_expenses'], 'direct_costs' => $pl['cogs'], 'net_profit' => $pl['net_profit']];
        }
        return $out;
    }

    /** Fills months with no activity, so a gap in the data shows as zero rather than vanishing from the axis. */
    private static function months(string $from, string $to): array
    {
        $out = [];
        $d = new \DateTime(substr($from, 0, 7) . '-01');
        $end = new \DateTime($to);
        while ($d <= $end && count($out) < 60) {
            $out[] = $d->format('Y-m');
            $d->modify('+1 month');
        }
        return $out;
    }

    private static function byMonth(string $sql, array $params, array $months): array
    {
        $map = array_column(DB::all($sql, $params), 'v', 'm');
        return array_map(fn ($m) => round((float) ($map[$m] ?? 0), 2), $months);
    }

    public static function charts(?int $farm, string $from, string $to): array
    {
        $months = self::months($from, $to);
        $labels = array_map(fn ($m) => date('M y', strtotime("$m-01")), $months);
        $p = [$from, $to];
        $mon = fn ($col) => "DATE_FORMAT($col,'%Y-%m')";

        $charts = [
            'labels' => $labels,
            'eggs' => self::eggSeries($farm, $from, $to),
            'mortality' => self::byMonth("SELECT {$mon('record_date')} m, SUM(quantity) v FROM mortality_records WHERE status='posted' AND record_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY m', $p, $months),
            'feed' => self::byMonth("SELECT {$mon('record_date')} m, SUM(quantity_kg) v FROM feed_consumption WHERE status='posted' AND record_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY m', $p, $months),
        ];
        $rows = DB::all("SELECT b.batch_code, b.initial_quantity, (SELECT COALESCE(SUM(i.quantity),0) FROM bird_inventory i WHERE i.batch_id=b.id) current_q,
            (SELECT COALESCE(SUM(m.quantity),0) FROM mortality_records m WHERE m.batch_id=b.id AND m.status='posted') dead
            FROM batches b WHERE b.status='active' AND b.deleted_at IS NULL" . self::scope('b.farm_id', $farm) . ' ORDER BY b.id DESC LIMIT 8');
        $charts['batches'] = [
            'labels' => array_column($rows, 'batch_code'),
            'initial' => array_map('intval', array_column($rows, 'initial_quantity')),
            'current' => array_map('intval', array_column($rows, 'current_q')),
        ];
        if (can('finance.view')) {
            $fin = fn ($type) => self::byMonth("SELECT {$mon('txn_date')} m, SUM(amount) v FROM financial_transactions WHERE entry_type='$type' AND txn_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY m', $p, $months);
            $charts['revenue'] = $fin('revenue');
            $charts['opex'] = $fin('opex');
            $charts['cogs'] = $fin('cogs');
            $charts['sales_total'] = self::byMonth("SELECT {$mon('sale_date')} m, SUM(grand_total) v FROM sales WHERE status='confirmed' AND sale_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY m', $p, $months);
            $charts['purchases_total'] = self::byMonth("SELECT {$mon('purchase_date')} m, SUM(total_amount) v FROM purchases WHERE status='confirmed' AND purchase_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY m', $p, $months);
        }
        return $charts;
    }

    private static function eggSeries(?int $farm, string $from, string $to): array
    {
        $rows = DB::all("SELECT record_date d, SUM(good_eggs) good, SUM(damaged_eggs+broken_eggs) bad FROM egg_production WHERE status='posted' AND record_date BETWEEN ? AND ?" . self::scope('farm_id', $farm) . ' GROUP BY d ORDER BY d', [$from, $to]);
        return [
            'labels' => array_map(fn ($r) => date('d M', strtotime($r['d'])), $rows),
            'good' => array_map('intval', array_column($rows, 'good')),
            'bad' => array_map('intval', array_column($rows, 'bad')),
        ];
    }
}
