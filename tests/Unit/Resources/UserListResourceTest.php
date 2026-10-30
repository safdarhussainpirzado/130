<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\UserListResource;
use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Positive and Negative Unit Tests for UserListResource.
 * Verifies credential shielding, secret exclusion, and lazy-loading query prevention.
 */
class UserListResourceTest extends TestCase
{
    /**
     * Positive Scenario: UserListResource includes only permitted user profile fields.
     */
    public function test_user_list_resource_includes_permitted_profile_fields(): void
    {
        $user = new User;
        $user->id = 77;
        $user->username = 'officer77';
        $user->full_name = 'Sub-Inspector Tariq';
        $user->email = 'tariq@nhmp.gov.pk';
        $user->mobile_no = '03001122334';
        $user->cnic = '37405-1234567-1';
        $user->is_active = true;

        $resource = new UserListResource($user);
        $result = $resource->toArray(Request::create('/mgmt/users'));

        $this->assertEquals(77, $result['id']);
        $this->assertEquals('officer77', $result['username']);
        $this->assertEquals('Sub-Inspector Tariq', $result['full_name']);
        $this->assertEquals('tariq@nhmp.gov.pk', $result['email']);
        $this->assertEquals('03001122334', $result['mobile_no']);
        $this->assertEquals('37405-1234567-1', $result['cnic']);
        $this->assertTrue($result['is_active']);
    }

    /**
     * Negative Scenario: UserListResource strictly shields credentials and MFA secrets.
     */
    public function test_user_list_resource_strictly_shields_secrets(): void
    {
        $user = new User;
        $user->id = 77;
        $user->username = 'officer77';
        $user->password = '$2y$12$eX4mPL3H4sHEDpAssW0rDStRInG1234567890abcdefg';
        $user->totp_secret = 'ENC:JBSWY3DPEHPK3PXP';
        $user->remember_token = 'remember_secret_token_abc';
        $user->failed_attempts = 3;
        $user->locked_until = Carbon::now()->addMinutes(15);
        $user->security_version = 2;

        $resource = new UserListResource($user);
        $result = $resource->toArray(Request::create('/mgmt/users'));

        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('totp_secret', $result);
        $this->assertArrayNotHasKey('remember_token', $result);
        $this->assertArrayNotHasKey('failed_attempts', $result);
        $this->assertArrayNotHasKey('locked_until', $result);
        $this->assertArrayNotHasKey('security_version', $result);
    }

    /**
     * Negative Scenario: Unloaded relationships must not trigger queries or throw errors.
     */
    public function test_user_list_resource_handles_unloaded_relations(): void
    {
        $user = new User;
        $user->id = 77;
        $user->username = 'officer77';
        // Neither role, designation, nor scopes are loaded

        $resource = new UserListResource($user);
        $result = $resource->toArray(Request::create('/mgmt/users'));

        $this->assertNull($result['role']);
        $this->assertNull($result['designation']);
        $this->assertEmpty($result['active_scopes']);
    }

    /**
     * Positive Scenario: Loaded relationships are formatted safely.
     */
    public function test_user_list_resource_formats_loaded_relations(): void
    {
        $user = new User;
        $user->id = 77;
        $user->username = 'officer77';

        $role = new Role;
        $role->id = 5;
        $role->name = 'beat_operator';
        $role->display_name = 'Wireless Operator';
        $user->setRelation('role', $role);

        $designation = new Designation;
        $designation->id = 12;
        $designation->name = 'Head Constable';
        $designation->short_code = 'HC';
        $designation->bps = 9;
        $user->setRelation('designation', $designation);

        $resource = new UserListResource($user);
        $result = $resource->toArray(Request::create('/mgmt/users'));

        $this->assertEquals(['id' => 5, 'name' => 'beat_operator', 'display_name' => 'Wireless Operator'], $result['role']);
        $this->assertEquals(['id' => 12, 'name' => 'Head Constable', 'short_code' => 'HC', 'bps' => 9], $result['designation']);
        $this->assertEquals('beat_operator', $result['role_name']);
    }
}
