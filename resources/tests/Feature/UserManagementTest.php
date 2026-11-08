<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Staff']);
    }

    public function test_other_super_admin_cannot_edit_or_delete_first_super_admin()
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $first->assignRole('Super Admin');

        $second = User::factory()->create(['email' => 'second@example.com']);
        $second->assignRole('Super Admin');
        $second->created_by = $first->id;
        $second->save();

        $this->actingAs($second);

        $resp = $this->get(route('admin.user.edit', $first));
        $resp->assertRedirect(route('admin.user.index'));

        $resp = $this->delete(route('admin.user.destroy', $first));
        $resp->assertRedirect(route('admin.user.index'));
    }

    public function test_first_super_admin_can_delete_super_admin_it_created()
    {
        $first = User::factory()->create(['email' => 'first2@example.com']);
        $first->assignRole('Super Admin');

        $child = User::factory()->create(['email' => 'child@example.com', 'created_by' => $first->id]);
        $child->assignRole('Super Admin');

        $this->actingAs($first);

        $resp = $this->delete(route('admin.user.destroy', $child));
        $resp->assertRedirect(route('admin.user.index'));

        $this->assertDatabaseMissing('users', ['id' => $child->id]);
    }
}
