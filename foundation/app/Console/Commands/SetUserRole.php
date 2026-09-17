<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetUserRole extends Command
{
    protected $signature = 'users:role {email} {role}';

    protected $description = 'Assign a role to an existing verified user via trusted server access';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));
        if (! $role) {
            $this->error('Allowed roles: customer, operator, admin.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($role) {
            // Serialize role changes to protect the last administrator from concurrent demotions.
            $users = User::query()->orderBy('id')->lockForUpdate()->get();
            $user = $users->firstWhere('email', strtolower(trim($this->argument('email'))));
            if (! $user || ! $user->hasVerifiedEmail()) {
                $this->error('A verified existing account is required.');

                return self::FAILURE;
            }
            if ($user->role === $role) {
                $this->info('Role unchanged.');

                return self::SUCCESS;
            }
            if ($user->role === Role::Admin && $role !== Role::Admin && $users->where('role', Role::Admin)->count() <= 1) {
                $this->error('Cannot demote the last administrator.');

                return self::FAILURE;
            }
            $previous = $user->role->value;
            $user->role = $role;
            $user->save();
            Audit::record('user.role_changed', null, $user->id, ['from_role' => $previous, 'to_role' => $role->value], 'console');
            $this->info('Role updated and audited.');

            return self::SUCCESS;
        });
    }
}
