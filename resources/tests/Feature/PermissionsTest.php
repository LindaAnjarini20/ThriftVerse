<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_manage_users_or_system_settings()
    {
        // Create roles and permissions using the seeder logic snapshot
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $staff = User::factory()->create();
        $staff->assignRole('Staff');

        $this->assertFalse($staff->can('manage users'));
        $this->assertFalse($staff->can('manage system settings'));
        $this->assertTrue($staff->can('view armada'));
    }
}
