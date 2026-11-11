<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Reusable Test User Factory for NHMP 130 CRM automated testing.
 * Provides consistent, role-scoped synthetic user accounts for unit, feature, and integration tests.
 */
class TestUserFactory
{
    public const DEFAULT_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const DEFAULT_PASSWORD = 'TestUserPass123!';

    /**
     * Create or retrieve an in-memory/persisted test user with a specific role.
     */
    public static function createWithRole(string $roleName, array $attributes = [], bool $persist = false): User
    {
        $role = Role::where('name', $roleName)->first();
        $roleId = $role ? $role->id : match ($roleName) {
            'super_admin' => 1,
            'operation_admin' => 2,
            'zone_admin' => 3,
            'sector_admin' => 4,
            'beat_operator' => 5,
            'agent_supervisor' => 6,
            'agent' => 7,
            default => null,
        };

        $defaults = [
            'username' => 'test_'.$roleName.'_'.substr(md5(uniqid()), 0, 6),
            'full_name' => 'Synthetic '.ucwords(str_replace('_', ' ', $roleName)),
            'email' => 'test.'.$roleName.'.'.uniqid().'@nhmp.synthetic',
            'mobile_no' => '03'.str_pad((string) rand(100000000, 999999999), 9, '0'),
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'role_id' => $roleId,
            'is_active' => true,
            'mfa_required' => true,
            'mfa_assurance_level' => 'totp',
            'password_changed_at' => Carbon::now(),
        ];

        $user = new User(array_merge($defaults, $attributes));
        $user->setEncryptedTotpSecret(self::DEFAULT_TOTP_SECRET);
        $user->mfa_enrolled_at = Carbon::now();

        if ($persist) {
            $user->save();
        }

        return $user;
    }

    public static function superAdmin(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('super_admin', $attributes, $persist);
    }

    public static function operationAdmin(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('operation_admin', $attributes, $persist);
    }

    public static function zoneAdmin(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('zone_admin', $attributes, $persist);
    }

    public static function sectorAdmin(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('sector_admin', $attributes, $persist);
    }

    public static function beatOperator(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('beat_operator', $attributes, $persist);
    }

    public static function agent(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('agent', $attributes, $persist);
    }

    public static function unauthorized(array $attributes = [], bool $persist = false): User
    {
        return self::createWithRole('unauthorized', array_merge(['role_id' => null], $attributes), $persist);
    }
}
