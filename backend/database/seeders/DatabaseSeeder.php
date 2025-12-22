<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MaterialType;
use App\Models\Project;
use App\Models\Client;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('admin123'),
                'is_admin' => true,
            ]
        );

        $defaultUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Użytkownik',
                'password' => bcrypt('password123'),
                'is_admin' => false,
            ]
        );

        $types = ['Płyta laminowana', 'Lakiery', 'Forniry'];
        foreach ($types as $typeName) {
            MaterialType::firstOrCreate(['name' => $typeName]);
        }

        $client = Client::firstOrCreate(
            ['first_name' => 'Klient', 'last_name' => 'Demo', 'email' => 'klient@example.com'],
        );

        Project::firstOrCreate(
            [
                'client_id' => 1,
                'created_by' => $client->id,
                'name' => 'Projekt Demo'
            ]
        );
    }
}
