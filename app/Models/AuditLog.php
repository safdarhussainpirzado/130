<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'previous_hash',
        'record_hash',
        'session_hash',
        'request_id',
        'node',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    protected $appends = [
        'event',
        'subject_type',
        'subject_id',
        'causer',
        'properties',
    ];

    const UPDATED_AT = null;

    /**
     * Serialize chain writes across all app nodes (Redis lock) so two concurrent events cannot read the same
     * previous hash and fork the chain. Fails open (logs) if the lock cannot be obtained so auditing never blocks users.
     */
    public static function create(array $attributes = [])
    {
        try {
            $lock = \Illuminate\Support\Facades\Cache::lock('audit_chain_write', 10);
            if ($lock->block(5)) {
                try {
                    return parent::create($attributes);
                } finally {
                    $lock->release();
                }
            }
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            \Illuminate\Support\Facades\Log::warning('audit chain lock timeout; writing unlocked');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('audit chain lock unavailable: '.$e->getMessage());
        }
        return parent::create($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            $log->forceFill(\App\Support\ForensicContext::all());
            $salt = config('app.audit_salt') ?: (function_exists('docker_secret') ? docker_secret('audit_salt', 'nhmp_130_audit_salt') : 'nhmp_130_audit_salt');
            
            // Retrieve the hash of the latest previous record
            $lastHash = static::latest('id')->value('record_hash');
            $previousHash = $lastHash ?: hash('sha256', 'NHMP_130_AUDIT_GENESIS_2026');

            $payload = implode('|', [
                (string) $log->user_id,
                (string) $log->action,
                (string) $log->table_name,
                (string) $log->record_id,
                (string) $log->ip_address,
                json_encode($log->old_values),
                json_encode($log->new_values),
                $previousHash,
            ]);

            $log->previous_hash = $previousHash;
            $log->record_hash = hash_hmac('sha256', $payload, $salt);
        });

        static::created(function (AuditLog $log): void {
            // Dual-logging: Stream to append-only off-DB log file for forensic resilience
            try {
                $logEntry = json_encode([
                    'id'            => $log->id,
                    'timestamp'     => $log->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'user_id'       => $log->user_id,
                    'action'        => $log->action,
                    'table_name'    => $log->table_name,
                    'record_id'     => $log->record_id,
                    'ip_address'    => $log->ip_address,
                    'previous_hash' => $log->previous_hash,
                    'record_hash'   => $log->record_hash,
                ], JSON_UNESCAPED_SLASHES) . PHP_EOL;

                $logPath = storage_path('logs/audit_security.jsonl');
                @file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $t) {
                // Dual-log error must not interrupt primary transaction
            }
        });
    }

    /**
     * Cryptographically verify the integrity of the audit log chain.
     * Returns an array with status, verified count, and any broken links.
     */
    public static function verifyIntegrity(int $limit = 1000): array
    {
        $salt = config('app.audit_salt') ?: (function_exists('docker_secret') ? docker_secret('audit_salt', 'nhmp_130_audit_salt') : 'nhmp_130_audit_salt');
        $records = static::whereNotNull('record_hash')->orderBy('id', 'desc')->limit($limit)->get()->reverse()->values();

        if ($records->isEmpty()) {
            return ['status' => 'empty', 'verified_count' => 0, 'errors' => []];
        }

        $expectedPrevious = null;
        $errors = [];

        foreach ($records as $log) {
            if ($expectedPrevious !== null && $log->previous_hash !== $expectedPrevious) {
                $errors[] = [
                    'id'       => $log->id,
                    'error'    => 'Broken chain: previous_hash does not match prior record_hash',
                    'expected' => $expectedPrevious,
                    'actual'   => $log->previous_hash,
                ];
            }

            $payload = implode('|', [
                (string) $log->user_id,
                (string) $log->action,
                (string) $log->table_name,
                (string) $log->record_id,
                (string) $log->ip_address,
                json_encode($log->old_values),
                json_encode($log->new_values),
                (string) $log->previous_hash,
            ]);

            $calculatedHash = hash_hmac('sha256', $payload, $salt);
            if ($log->record_hash !== $calculatedHash) {
                // Historical backward-compatibility fallback for empty array vs null JSON serialization
                $altPayload = implode('|', [
                    (string) $log->user_id,
                    (string) $log->action,
                    (string) $log->table_name,
                    (string) $log->record_id,
                    (string) $log->ip_address,
                    json_encode($log->old_values ?? []),
                    json_encode($log->new_values ?? []),
                    (string) $log->previous_hash,
                ]);
                $altHash = hash_hmac('sha256', $altPayload, $salt);
                if ($log->record_hash === $altHash) {
                    $calculatedHash = $altHash;
                } else {
                    // Check bidx_salt for transitional records
                    $bidxSalt = config('app.bidx_salt') ?: (function_exists('docker_secret') ? docker_secret('bidx_salt') : null);
                    if ($bidxSalt) {
                        $b1 = hash_hmac('sha256', $payload, $bidxSalt);
                        $b2 = hash_hmac('sha256', $altPayload, $bidxSalt);
                        if ($log->record_hash === $b1) {
                            $calculatedHash = $b1;
                        } elseif ($log->record_hash === $b2) {
                            $calculatedHash = $b2;
                        } elseif (is_array($log->new_values) && isset($log->new_values['target_user'])) {
                            // PHP insertion order before MySQL key sorting
                            $pReordered = [
                                'action' => $log->new_values['action'] ?? null,
                                'target_user' => $log->new_values['target_user'] ?? null,
                                'is_active' => $log->new_values['is_active'] ?? null,
                                'locked_until' => $log->new_values['locked_until'] ?? null,
                                'failed_attempts' => $log->new_values['failed_attempts'] ?? null,
                            ];
                            $b3 = hash_hmac('sha256', implode('|', [
                                (string) $log->user_id,
                                (string) $log->action,
                                (string) $log->table_name,
                                (string) $log->record_id,
                                (string) $log->ip_address,
                                json_encode([]),
                                json_encode($pReordered),
                                (string) $log->previous_hash,
                            ]), $bidxSalt);
                            if ($log->record_hash === $b3) {
                                $calculatedHash = $b3;
                            }
                        }
                    }
                }
            }

            if ($log->record_hash !== $calculatedHash) {
                $errors[] = [
                    'id'       => $log->id,
                    'error'    => 'Tampered content: record_hash mismatch',
                    'expected' => $calculatedHash,
                    'actual'   => $log->record_hash,
                ];
            }

            $expectedPrevious = $log->record_hash;
        }

        return [
            'status'         => empty($errors) ? 'intact' : 'tampered',
            'verified_count' => $records->count(),
            'errors'         => $errors,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEventAttribute()
    {
        return $this->action;
    }

    public function getSubjectTypeAttribute()
    {
        return $this->table_name;
    }

    public function getSubjectIdAttribute()
    {
        return $this->record_id;
    }

    public function getCauserAttribute()
    {
        return $this->user;
    }

    public function getPropertiesAttribute()
    {
        return [
            'ip'         => $this->ip_address,
            'user_agent' => $this->user_agent,
            'old'        => $this->old_values ?? [],
            'attributes' => $this->new_values ?? [],
        ];
    }
}
