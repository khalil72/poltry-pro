<?php
namespace App\Models;

use App\Core\Database as DB;

class Farm extends Model
{
    protected static string $table = 'farms';

    public static function stats(int $id): array
    {
        return [
            'sheds' => (int) DB::value('SELECT COUNT(*) FROM sheds WHERE farm_id=? AND deleted_at IS NULL', [$id]),
            'active_batches' => (int) DB::value("SELECT COUNT(*) FROM batches WHERE farm_id=? AND status='active' AND deleted_at IS NULL", [$id]),
            'birds' => (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE farm_id=?', [$id]),
            'dead' => (int) DB::value("SELECT COALESCE(SUM(quantity),0) FROM mortality_records WHERE farm_id=? AND status='posted'", [$id]),
            'feed_kg' => (float) DB::value('SELECT COALESCE(SUM(quantity_kg),0) FROM feed_inventory WHERE farm_id=?', [$id]),
            'eggs' => (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM egg_inventory WHERE farm_id=?', [$id]),
            'employees' => (int) DB::value("SELECT COUNT(*) FROM employees WHERE farm_id=? AND status='active' AND deleted_at IS NULL", [$id]),
        ];
    }
}
