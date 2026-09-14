<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Logistic;

class LogisticSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'logistic@bilihub.com')->first();

        if ($user) {
            Logistic::updateOrCreate(
                ['owner_user_id' => $user->id],
                [
                    'company_name' => 'Speedy Express Logistics',
                    'contact_person' => 'Juan Dela Cruz',
                    'email' => 'speedy@bilihub.com',
                    'phone' => '09678901234',
                    'address' => '789 Logistics Ave, Pasig',
                    'status' => 'active',
                    'approved_at' => now(),
                ]
            );
        }
    }
}
