<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database as DB;

/**
 * Options for select fields, limited to what the user may see. Form values are checked against
 * these same lists on the server, so a tampered id from another farm is rejected.
 * Each option: value, label, parent (optional, used to filter by another field), price (optional).
 */
class Lookup
{
    private static array $cache = [];

    public static function options(string $name): array
    {
        return self::$cache[$name] ??= self::load($name);
    }

    public static function has(string $name, mixed $value): bool
    {
        foreach (self::options($name) as $o) {
            if ((string) $o['value'] === (string) $value) {
                return true;
            }
        }
        return false;
    }

    private static function load(string $n): array
    {
        $simple = fn (string $table, string $where = 'deleted_at IS NULL', string $label = 'name') =>
            DB::all("SELECT id value, $label label FROM `$table` WHERE $where ORDER BY label");

        switch ($n) {
            case 'farms':
                return DB::all("SELECT id value, name label FROM farms WHERE deleted_at IS NULL AND status='active'" . Auth::farmSql('id') . ' ORDER BY name');
            case 'sheds':
                return DB::all("SELECT s.id value, CONCAT(f.name,' / ',s.name,' (', s.current_bird_count,'/',s.max_capacity,')') label, s.farm_id parent FROM sheds s JOIN farms f ON f.id=s.farm_id WHERE s.deleted_at IS NULL AND s.status='active'" . Auth::farmSql('s.farm_id') . ' ORDER BY f.name, s.name');
            case 'batches':
                return DB::all("SELECT id value, CONCAT(batch_code,' ',name) label, farm_id parent FROM batches WHERE deleted_at IS NULL AND status='active'" . Auth::farmSql('farm_id') . ' ORDER BY id DESC');
            case 'batches_all':
                return DB::all('SELECT id value, CONCAT(batch_code,\' \',name) label, farm_id parent FROM batches WHERE deleted_at IS NULL' . Auth::farmSql('farm_id') . ' ORDER BY id DESC');
            case 'flocks':
            case 'layer_flocks':
                $type = $n === 'layer_flocks' ? " AND b.batch_type<>'broiler'" : '';
                return DB::all("SELECT CONCAT(i.batch_id,':',i.shed_id) value, CONCAT(b.batch_code,' ',b.name,' / ',s.name,' (',i.quantity,' birds)') label, i.farm_id parent, i.quantity available
                    FROM bird_inventory i JOIN batches b ON b.id=i.batch_id JOIN sheds s ON s.id=i.shed_id
                    WHERE i.quantity>0 AND b.status='active' AND b.deleted_at IS NULL$type" . Auth::farmSql('i.farm_id') . ' ORDER BY b.id DESC, s.name');
            case 'suppliers':
                return $simple('suppliers', "deleted_at IS NULL AND status='active'");
            case 'customers':
                return DB::all("SELECT id value, IF(business_name IS NULL OR business_name='', name, CONCAT(name,' (',business_name,')')) label FROM customers WHERE deleted_at IS NULL AND status='active' ORDER BY name");
            case 'feed_categories':
                return $simple('feed_categories');
            case 'feed_products':
                return DB::all("SELECT id value, name label, sale_price_kg price, default_unit_id unit FROM feed_products WHERE deleted_at IS NULL AND status='active' ORDER BY name");
            case 'units':
                return DB::all("SELECT id value, CONCAT(name,' (',symbol,')') label FROM units WHERE deleted_at IS NULL ORDER BY kg_factor");
            case 'medicine_categories':
                return $simple('medicine_categories');
            case 'medicines':
                return DB::all("SELECT id value, CONCAT(name,' (',unit,')') label FROM medicines WHERE deleted_at IS NULL AND status='active' ORDER BY name");
            case 'vaccinations':
                return $simple('vaccinations');
            case 'mortality_reasons':
                return $simple('mortality_reasons');
            case 'expense_categories':
                return DB::all("SELECT id value, CONCAT(name, IF(expense_type='capital',' (capital)','')) label FROM expense_categories WHERE deleted_at IS NULL ORDER BY name");
            case 'accounts':
                return DB::all("SELECT id value, CONCAT(name,' (',account_type,')') label FROM accounts WHERE deleted_at IS NULL AND status='active' ORDER BY name");
            case 'employees':
                return DB::all("SELECT id value, CONCAT(name,' (',employee_code,')') label, farm_id parent, salary price FROM employees WHERE deleted_at IS NULL AND status IN ('active','on_leave')" . Auth::farmSql('farm_id') . ' ORDER BY name');
            case 'product_categories':
                return $simple('product_categories');
            case 'products':
                return DB::all("SELECT id value, name label, price FROM products WHERE deleted_at IS NULL ORDER BY name");
            case 'stock_products':
                return DB::all("SELECT id value, name label, price FROM products WHERE deleted_at IS NULL AND track_stock=1 ORDER BY name");
            case 'roles':
                return $simple('roles', '1=1');
        }
        throw new \LogicException("Unknown lookup: $n");
    }
}
