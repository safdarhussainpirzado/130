<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Uniqueness for ENCRYPTED columns (users.cnic / users.mobile_no). The stored value is randomised AES-GCM ciphertext,
 * so Laravel's `unique:users,cnic` compares plaintext with ciphertext and never finds a duplicate. This rule compares
 * the deterministic HMAC blind index instead (cnic_bidx / mobile_no_bidx), using the exact same normalisation as User::booted().
 */
class UniqueBlindIndex implements ValidationRule
{
    /** @param 'cnic'|'phone' $kind */
    public function __construct(
        private string $kind,
        private ?int $ignoreId = null,
        private string $table = 'users',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        [$column, $bidx, $label] = $this->kind === 'cnic'
            ? ['cnic_bidx', cnic_blind_index((string) $value), 'CNIC']
            : ['mobile_no_bidx', phone_blind_index((string) $value), 'mobile number'];

        if ($bidx === null) {
            return;
        }

        $query = DB::table($this->table)->where($column, $bidx);
        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail("This {$label} is already registered to another user.");
        }
    }
}
