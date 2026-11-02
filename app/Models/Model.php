<?php
namespace App\Models;

use App\Core\Database as DB;

/** Thin table gateway for read access. Writes that move stock or money belong in the services. */
abstract class Model
{
    protected static string $table;
    protected static bool $softDelete = true;

    public static function find(int $id): ?array
    {
        $soft = static::$softDelete ? ' AND deleted_at IS NULL' : '';
        return DB::one('SELECT * FROM `' . static::$table . "` WHERE id=?$soft", [$id]);
    }
}
