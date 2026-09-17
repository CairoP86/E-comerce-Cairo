<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_registration_cannot_inject_role_or_verified_status(): void
    {
        $this->post('/register', ['name' => 'Cliente', 'email' => 'CLIENT@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'admin', 'email_verified_at' => now()])->assertRedirect('/verify-email');
        $user = User::first();
        $this->assertSame(Role::Customer, $user->role);
        $this->assertSame('client@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('LongPassword123!', $user->password));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.registered', 'subject_id' => $user->id]);
        $this->get('/account')->assertRedirect('/verify-email');
    }

    public function test_duplicate_email_and_weak_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'client@example.test']);
        $this->post('/register', ['name' => 'Test', 'email' => 'CLIENT@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_multibyte_passwords_above_bcrypt_limit_are_rejected(): void
    {
        $password = str_repeat('á', 40).'123';
        $this->post('/register', ['name' => 'Test', 'email' => 'client@example.test', 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rotates_session_and_logout_invalidates_access(): void
    {
        $user = User::factory()->create();
        $this->get('/login');
        $previous = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/account');
        $this->assertNotSame($previous, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/account')->assertRedirect('/login');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'actor_id' => $user->id]);
    }

    public function test_invalid_login_is_generic_audited_and_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'private-secret'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'private-secret'])->assertStatus(429);
        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 5);
        $logs = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('private-secret', $logs);
        $this->assertStringNotContainsString('missing@example.test', $logs);
    }

    public function test_role_access_matrix_and_public_props(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        foreach ([Role::Customer, Role::Operator, Role::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/account')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', $role->value)->missing('auth.user.password')->missing('auth.user.remember_token'));
            $this->get('/admin')->assertStatus($role === Role::Customer ? 403 : 200);
            $this->get('/admin/audit')->assertStatus($role === Role::Admin ? 200 : 403);
        }
    }

    public function test_even_an_administrator_must_verify_email(): void
    {
        $user = User::factory()->unverified()->create(['role' => Role::Admin]);
        $this->actingAs($user)->get('/admin')->assertRedirect('/verify-email');
    }

    public function test_signed_verification_is_required_and_audited_once(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/verify-email/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/account');
        $this->get($url)->assertRedirect('/account');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame(1, AuditLog::where('event', 'auth.email_verified')->count());
    }

    public function test_expired_or_other_users_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($expired)->assertForbidden();
        $wrong = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => sha1($other->email)]);
        $this->get($wrong)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_recovery_response_does_not_disclose_account_existence(): void
    {
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $known = session('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', $known);
    }

    public function test_password_reset_requires_token_and_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => 'invalid', 'password' => 'Replacement12345!', 'password_confirmation' => 'Replacement12345!'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/reset-password', [...$data, 'token' => $token])->assertRedirect('/login');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertNotSame($user->remember_token, $user->fresh()->remember_token);
        $this->post('/reset-password', [...$data, 'token' => $token])->assertSessionHasErrors('email');
        $this->assertSame(1, AuditLog::where('event', 'auth.password_reset')->count());
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Replacement12345!', 'password_confirmation' => 'Replacement12345!'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_console_role_changes_are_audited_and_last_admin_is_protected(): void
    {
        $user = User::factory()->create();
        $this->artisan('users:role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();
        $this->assertSame(Role::Admin, $user->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.role_changed', 'source' => 'console', 'subject_id' => $user->id]);
        $this->artisan('users:role', ['email' => $user->email, 'role' => 'customer'])->assertFailed();
        $this->artisan('users:role', ['email' => $user->email, 'role' => 'invalid'])->assertFailed();
    }

    public function test_unverified_user_cannot_be_promoted(): void
    {
        $user = User::factory()->unverified()->create();
        $this->artisan('users:role', ['email' => $user->email, 'role' => 'operator'])->assertFailed();
        $this->assertSame(Role::Customer, $user->fresh()->role);
    }

    public function test_audit_is_read_only_and_escapes_user_data(): void
    {
        $user = User::factory()->create(['role' => Role::Admin, 'name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get('/account')->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/admin/audit', ['event' => 'forged'])->assertStatus(405);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_old_authenticated_session_is_rejected_after_password_change(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->password = 'ChangedPassword123!';
        $user->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get('/account')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_role_demotion_applies_on_next_request(): void
    {
        $user = User::factory()->create(['role' => Role::Operator]);
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->artisan('users:role', ['email' => $user->email, 'role' => 'customer'])->assertSuccessful();
        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }
}
