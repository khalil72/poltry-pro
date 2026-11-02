<?php
namespace App\Models;

use App\Core\Database as DB;

class Shed extends Model
{
    protected static string $table = 'sheds';

    public static function performance(int $id): array
    {
        return [
            'flocks' => DB::all('SELECT b.id, b.batch_code, b.name, b.batch_type, i.quantity FROM bird_inventory i JOIN batches b ON b.id=i.batch_id WHERE i.shed_id=? AND i.quantity>0', [$id]),
            'dead' => (int) DB::value("SELECT COALESCE(SUM(quantity),0) FROM mortality_records WHERE shed_id=? AND status='posted'", [$id]),
            'feed_kg' => (float) DB::value("SELECT COALESCE(SUM(quantity_kg),0) FROM feed_consumption WHERE shed_id=? AND status='posted'", [$id]),
            'eggs' => (int) DB::value("SELECT COALESCE(SUM(total_eggs),0) FROM egg_production WHERE shed_id=? AND status='posted'", [$id]),
        ];
    }
}
