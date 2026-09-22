<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--name= : Full name}
        {--email= : Login email}
        {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create or update an administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $email = $this->option('email') ?: $this->ask('Login email');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:160'],
                'password' => ['required', 'string', 'min:10'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => 'admin', 'is_active' => true],
        );

        $this->info("Administrator {$admin->email} is ready.");

        return self::SUCCESS;
    }
}
