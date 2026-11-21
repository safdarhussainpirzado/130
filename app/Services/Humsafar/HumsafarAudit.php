<?php

namespace App\Services\Humsafar;

use App\Models\AuditLog;
use App\Models\User;
use Throwable;

class HumsafarAudit
{
    public static function log(?User $user, string $action, string $table, int|string|null $recordId, array $new = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $user?->id, 'action' => $action, 'table_name' => $table,
                'record_id' => $recordId, 'old_values' => [], 'new_values' => $new,
                'ip_address' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
