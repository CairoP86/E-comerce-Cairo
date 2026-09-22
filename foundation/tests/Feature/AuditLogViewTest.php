<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The activity log names the people involved instead of showing their ids. */
class AuditLogViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function entries(): array
    {
        return $this->get('/admin/audit')->assertOk()->viewData('page')['props']['entries']['data'];
    }

    public function test_an_entry_carries_the_name_and_email_of_the_actor_and_the_account(): void
    {
        $actor = User::factory()->create(['name' => 'Quien Actúa', 'email' => 'actor@example.test', 'role' => Role::Admin]);
        $subject = User::factory()->create(['name' => 'Cuenta Afectada', 'email' => 'cuenta@example.test']);
        Audit::record('user.role_changed', $actor->id, $subject->id, ['from_role' => 'customer', 'to_role' => 'operator']);

        $this->actingAs($actor);
        $entry = $this->entries()[0];
        $this->assertSame(['id' => $actor->id, 'name' => 'Quien Actúa', 'email' => 'actor@example.test'], $entry['actor']);
        $this->assertSame(['id' => $subject->id, 'name' => 'Cuenta Afectada', 'email' => 'cuenta@example.test'], $entry['subject']);
    }

    public function test_deleting_a_user_clears_the_reference_by_design(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $gone = User::factory()->create();
        Audit::record('auth.login', $gone->id, $gone->id);
        $gone->delete();

        $this->actingAs($admin);
        $entry = $this->entries()[0];
        // The schema declares nullOnDelete: the log keeps the event but forgets who it was.
        $this->assertNull($entry['actor_id']);
        $this->assertNull($entry['actor']);
        $this->assertNull($entry['subject']);
        $this->assertSame('auth.login', $entry['event']);
    }

    public function test_an_action_without_an_actor_stays_empty(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        Audit::record('catalog.demo_archived', metadata: ['entity_type' => 'products', 'entity_id' => 3], source: 'cli');

        $this->actingAs($admin);
        $entry = $this->entries()[0];
        $this->assertNull($entry['actor']);
        $this->assertNull($entry['actor_id']);
        $this->assertSame('cli', $entry['source']);
    }

    public function test_the_page_resolves_every_name_in_one_query(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        foreach (User::factory()->count(5)->create() as $user) {
            Audit::record('auth.login', $user->id, $user->id);
        }

        $this->actingAs($admin);
        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'from "users"') || str_contains($query->sql, 'from `users`')) {
                $queries++;
            }
        });
        $this->entries();
        // One for the signed-in user, one for every name on the page: never one per row.
        $this->assertLessThanOrEqual(2, $queries);
    }
}
