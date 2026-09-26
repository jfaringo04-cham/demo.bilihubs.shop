<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Roles are stored in the role_user pivot table.
        // A role may not yet be attached at the exact moment
        // the User::created event fires, so safely stop here
        // when no role has been assigned yet.
        $roleLabel = match (true) {
            $user->hasRole('buyer') => 'Buyer',
            $user->hasRole('seller') => 'Seller',
            $user->hasRole('rider') => 'Rider',
            $user->hasRole('logistics') => 'Logistics Company',
            $user->hasRole('admin') => 'Admin',
            default => null,
        };

        if (!$roleLabel) {
            return;
        }

        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        if ($admins->isEmpty()) {
            return;
        }

        $link = route('admin.registrations.show', $user);

        $isRider = $roleLabel === 'Rider';

        $title = 'New ' . $roleLabel . ' Registration';

        $message = $isRider
            ? sprintf('%s (%s) has applied as a Rider and is awaiting approval from the logistics company.', $user->name, $user->email)
            : sprintf(
                '%s (%s) has registered as a %s and is awaiting your approval.',
                $user->name,
                $user->email,
                $roleLabel
            );

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => 'registration',
                'link' => $link,
                'is_read' => false,
            ]);
        }
    }
}