<?php

namespace Database\Seeders;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;

class MigrateRidersSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->where('name', 'rider');
            })
            ->each(function (User $user) {

                Rider::updateOrCreate(
                    [
                        'user_id' => $user->id,
                    ],
                    [
                        'logistic_id' => $user->logistic_id,

                        'vehicle_type' => $user->vehicle_type,
                        'license_number' => $user->license_number,

                        'rider_documents' => $user->rider_documents,
                        'or_document' => $user->or_document,
                        'cr_document' => $user->cr_document,

                        'status' => $user->logistic_status
                            ?? $user->status
                            ?? 'pending',

                        'rejection_reason' => $user->logistic_rejection_reason
                            ?? $user->rejection_reason,

                        'approved_at' => $user->logistic_approved_at
                            ?? $user->approved_at,

                        'max_capacity' => $user->max_capacity ?? 10,
                        'current_load' => $user->current_load ?? 0,

                        'availability_status' => $user->availability_status
                            ?? 'offline',

                        'assigned_zone' => $user->assigned_zone,
                        'last_active_at' => $user->last_active_at,

                        'daily_pickups_completed' =>
                            $user->daily_pickups_completed ?? 0,

                        'daily_deliveries_completed' =>
                            $user->daily_deliveries_completed ?? 0,

                        'last_quota_reset_date' =>
                            $user->last_quota_reset_date,
                    ]
                );
            });
    }
}