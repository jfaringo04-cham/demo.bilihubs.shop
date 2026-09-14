<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\User;

class UserObserver
{
    public function created(User $user)
    {
        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        $roleLabel = match ($user->role) {
            'customer' => 'Buyer',
            'seller' => 'Seller',
            'rider' => 'Rider',
            'logistic_owner' => 'Logistics Company',
            default => ucfirst($user->role),
        };

        $link = route('admin.registrations.show', $user);

        $title = 'New ' . $roleLabel . ' Registration';
        $message = sprintf(
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
