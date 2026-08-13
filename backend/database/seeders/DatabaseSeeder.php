<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $company = Company::query()->firstOrCreate(
            ['name' => 'Default Company'],
            ['status' => 'active'],
        );

        $superadmin = User::factory()->create([
            'name' => 'Sam Superadmin',
            'email' => 'superadmin@omnichat.test',
            'password' => bcrypt('password'),
            'company_id' => null,
        ]);
        $superadmin->assignRole('superadmin');

        $admin = User::factory()->create([
            'name' => 'Ava Admin',
            'email' => 'admin@omnichat.test',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $admin->assignRole('admin');

        $manager = User::factory()->create([
            'name' => 'Marcus Manager',
            'email' => 'manager@omnichat.test',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $manager->assignRole('manager');

        $agent = User::factory()->create([
            'name' => 'Aria Agent',
            'email' => 'agent@omnichat.test',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $agent->assignRole('agent');

        (new PipelineStagesSeeder($company->id))->run();
        (new ContactsSeeder($company->id))->run();
        $this->call(ConversationsSeeder::class);
        (new ApiConnectionsSeeder($company->id))->run();
    }
}
