<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database as DB;

/** Everything the bell icon shows, limited to what the signed-in user may see. */
class AlertService
{
    public static function all(): array
    {
        $out = [];
        if (can('vaccinations.view')) {
            $days = (int) setting('vaccination_reminder_days', '7');
            $rows = DB::all("SELECT r.scheduled_date, v.name, b.batch_code FROM vaccination_records r JOIN vaccinations v ON v.id=r.vaccination_id JOIN batches b ON b.id=r.batch_id
                WHERE r.status='scheduled' AND b.status='active' AND b.deleted_at IS NULL AND r.scheduled_date<=DATE_ADD(CURDATE(), INTERVAL $days DAY)" . Auth::farmSql('r.farm_id') . ' ORDER BY r.scheduled_date LIMIT 15');
            foreach ($rows as $r) {
                $late = $r['scheduled_date'] < date('Y-m-d');
                $out[] = ['level' => $late ? 'danger' : 'warning', 'icon' => 'bi-shield-exclamation', 'url' => 'admin/vaccination-records?status=scheduled',
                    'text' => ($late ? 'Overdue: ' : 'Due ' . fdate($r['scheduled_date']) . ': ') . "{$r['name']} for {$r['batch_code']}"];
            }
        }
        if (can('feed.view')) {
            $rows = DB::all('SELECT i.quantity_kg, p.name, f.name farm FROM feed_inventory i JOIN feed_products p ON p.id=i.feed_product_id JOIN farms f ON f.id=i.farm_id
                WHERE p.deleted_at IS NULL AND p.low_stock_kg>0 AND i.quantity_kg<=p.low_stock_kg' . Auth::farmSql('i.farm_id') . ' LIMIT 10');
            foreach ($rows as $r) {
                $out[] = ['level' => 'warning', 'icon' => 'bi-box-seam', 'url' => 'admin/feed-stock', 'text' => "Low feed: {$r['name']} at {$r['farm']} (" . num($r['quantity_kg']) . ' kg left)'];
            }
        }
        if (can('medicines.view')) {
            $days = (int) setting('expiry_alert_days', '30');
            $rows = DB::all("SELECT l.expiry_date, l.lot_no, m.name FROM medicine_lots l JOIN medicines m ON m.id=l.medicine_id
                WHERE l.quantity>0 AND l.expiry_date IS NOT NULL AND l.expiry_date<=DATE_ADD(CURDATE(), INTERVAL $days DAY)" . Auth::farmSql('l.farm_id') . ' ORDER BY l.expiry_date LIMIT 10');
            foreach ($rows as $r) {
                $out[] = ['level' => 'danger', 'icon' => 'bi-capsule', 'url' => 'admin/medicine-stock', 'text' => ($r['expiry_date'] < date('Y-m-d') ? 'Expired: ' : 'Expires ' . fdate($r['expiry_date']) . ': ') . $r['name']];
            }
        }
        if (can('mortality.view')) {
            $limit = (float) setting('mortality_alert_pct', '5');
            $rows = DB::all("SELECT b.batch_code, ROUND(SUM(m.quantity)/b.initial_quantity*100,2) pct FROM mortality_records m JOIN batches b ON b.id=m.batch_id
                WHERE m.status='posted' AND b.status='active' AND b.deleted_at IS NULL" . Auth::farmSql('b.farm_id') . ' GROUP BY b.id HAVING pct>=? LIMIT 10', [$limit]);
            foreach ($rows as $r) {
                $out[] = ['level' => 'danger', 'icon' => 'bi-heartbreak', 'url' => 'admin/mortality', 'text' => "Mortality at {$r['pct']}% in {$r['batch_code']}"];
            }
        }
        if (can('cms.view')) {
            $new = (int) DB::value('SELECT COUNT(*) FROM contact_messages WHERE is_read=0 AND deleted_at IS NULL');
            if ($new) {
                $out[] = ['level' => 'primary', 'icon' => 'bi-envelope', 'url' => 'admin/messages?unread=0', 'text' => "$new new website " . ($new === 1 ? 'message' : 'messages')];
            }
        }
        return $out;
    }
}
