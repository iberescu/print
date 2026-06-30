<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {password} {--name=Admin}';

    protected $description = 'Create (or update) an admin user for the dashboard';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name'     => $this->option('name'),
                'password' => Hash::make($this->argument('password')),
                'is_admin' => true,
            ],
        );

        $this->info("Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
