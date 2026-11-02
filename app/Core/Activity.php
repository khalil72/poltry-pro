<?php
namespace App\Core;

class Activity
{
    public static function log(string $action, ?string $entity = null, ?int $id = null, ?string $description = null): void
    {
        Database::insert('activity_logs', [
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $id,
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'ip_address' => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
        ]);
    }
}
