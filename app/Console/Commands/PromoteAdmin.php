<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdmin extends Command
{
    protected $signature = 'admin:promote {email}';
    protected $description = 'Grant admin access to a user by email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email: {$this->argument('email')}");
            return 1;
        }

        if ($user->is_admin) {
            $this->info("{$user->email} is already an admin.");
            return 0;
        }

        $user->update(['is_admin' => true]);
        $this->info("{$user->name} ({$user->email}) is now an admin.");
        return 0;
    }
}
