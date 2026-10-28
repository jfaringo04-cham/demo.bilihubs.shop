<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_change_password_with_current_password(): void
    {
        $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
        $user = User::factory()->create(['password' => 'old-password']);
        $user->roles()->attach($buyerRole->id);

        $response = $this->actingAs($user)->patch(route('profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('password_success');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
        $user = User::factory()->create(['password' => 'old-password']);
        $user->roles()->attach($buyerRole->id);

        $response = $this->actingAs($user)->patch(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('current_password', null, 'passwordUpdate');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
