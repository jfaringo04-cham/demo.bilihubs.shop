<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class AdminRegistrationNotification
{
    public function send(User $applicant, string $roleLabel): void
    {
        $adminIds = User::query()
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'superadmin']))
            ->pluck('id');

        foreach ($adminIds as $adminId) {
            Notification::create([
                'user_id' => $adminId,
                'title' => 'New ' . $roleLabel . ' Registration',
                'message' => sprintf('%s (%s) registered as a %s and is awaiting approval.', $applicant->name, $applicant->email, $roleLabel),
                'type' => 'registration',
                'link' => route('admin.registrations.show', $applicant, false),
                'is_read' => false,
            ]);
        }
    }
}
