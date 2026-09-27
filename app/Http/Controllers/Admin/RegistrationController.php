<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationDecision;
use App\Models\Logistic;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    private function createNotification(
        $userId,
        $title,
        $message,
        $type = 'order',
        $link = null
    ) {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    private function notifyByEmail(
        User $user,
        string $decision,
        ?string $reason = null
    ) {
        try {
            Mail::to($user->email)->send(
                new RegistrationDecision(
                    $user,
                    $decision,
                    $reason
                )
            );
        } catch (\Throwable $e) {
            logger()->error(
                'Failed to send registration email to '
                . $user->email
                . ': '
                . $e->getMessage()
            );
        }
    }

    public function index(Request $request)
    {
        $query = User::where(
            'status',
            User::STATUS_PENDING
        )->whereHas('roles', function ($q) {
            $q->whereIn(
                'name',
                ['buyer', 'seller', 'logistics']
            );
        });

        if ($request->filled('role')) {
            $requestedRole = match ($request->role) {
                'customer' => 'buyer',
                'logistic_owner',
                'logistic' => 'logistics',
                default => $request->role,
            };

            $query->whereHas(
                'roles',
                fn ($q) => $q->where(
                    'name',
                    $requestedRole
                )
            );
        }

        $applications = $query
            ->latest()
            ->paginate(15);

        return view(
            'admin.registrations',
            compact('applications')
        );
    }

    public function show(User $user)
    {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications are reviewed by the logistics company.'
                );
        }

        if (!$user->isPending()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'This application is no longer pending.'
                );
        }

        return view(
            'admin.registrations-show',
            compact('user')
        );
    }

    public function approve(
        Request $request,
        User $user
    ) {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications must be approved by the logistics company.'
                );
        }

        if (!$user->isPending()) {
            return back()->with(
                'error',
                'This application has already been processed.'
            );
        }

        $roleName = $user
            ->roles()
            ->value('name');

        if (
            !in_array(
                $roleName,
                ['buyer', 'seller', 'logistics'],
                true
            )
        ) {
            return back()->with(
                'error',
                'This role cannot be approved from the registrations page.'
            );
        }

        DB::transaction(
            function () use ($user, $roleName) {
                /*
                |--------------------------------------------------------------------------
                | Activate approved user
                |--------------------------------------------------------------------------
                */
                $user->update([
                    'status' => User::STATUS_ACTIVE,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Seller approval
                |--------------------------------------------------------------------------
                | Create/update the canonical sellers table record so products
                | created by this seller receive a valid seller_id.
                */
                if ($roleName === 'seller') {
                    $shopName = $user->store_name
                        ?: $user->business_name
                        ?: $user->name . ' Shop';

                    $baseSlug = Str::slug($shopName);

                    if ($baseSlug === '') {
                        $baseSlug = 'seller-' . $user->id;
                    }

                    $slug = $baseSlug;
                    $counter = 1;

                    while (
                        Seller::where('slug', $slug)
                            ->where(
                                'user_id',
                                '!=',
                                $user->id
                            )
                            ->exists()
                    ) {
                        $slug = $baseSlug
                            . '-'
                            . $counter;

                        $counter++;
                    }

                    Seller::updateOrCreate(
                        [
                            'user_id' => $user->id,
                        ],
                        [
                            'name' => $shopName,
                            'slug' => $slug,
                            'description' => null,
                            'logo_path' => $user->logo,
                            'banner_path' => null,
                            'status' => 'approved',
                            'rejection_reason' => null,
                            'commission_bps' => 0,
                            'pickup_address_id' => null,
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Logistics approval
                |--------------------------------------------------------------------------
                */
                if ($roleName === 'logistics') {
                    Logistic::where(
                        'owner_user_id',
                        $user->id
                    )->update([
                        'status' => 'active',
                        'approved_at' => now(),
                        'rejection_reason' => null,
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | In-app notification
        |--------------------------------------------------------------------------
        */
        $this->createNotification(
            $user->id,
            'Registration Approved',
            'Your '
                . ucfirst($roleName ?? 'user')
                . ' account application has been approved. You can now log in.',
            'account',
            route('login')
        );

        /*
        |--------------------------------------------------------------------------
        | Email notification
        |--------------------------------------------------------------------------
        */
        $this->notifyByEmail(
            $user,
            'approved'
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with(
                'success',
                ucfirst($roleName ?? 'user')
                    . ' application approved and notified via email.'
            );
    }

    public function reject(
        Request $request,
        User $user
    ) {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications must be rejected by the logistics company.'
                );
        }

        if (!$user->isPending()) {
            return back()->with(
                'error',
                'This application has already been processed.'
            );
        }

        $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $roleName = $user
            ->roles()
            ->value('name');

        if (
            !in_array(
                $roleName,
                ['buyer', 'seller', 'logistics'],
                true
            )
        ) {
            return back()->with(
                'error',
                'This role cannot be rejected from the registrations page.'
            );
        }

        DB::transaction(
            function () use (
                $user,
                $roleName,
                $request
            ) {
                $user->update([
                    'status' => User::STATUS_REJECTED,
                    'rejection_reason' =>
                        $request->rejection_reason,
                    'approved_at' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Seller rejection
                |--------------------------------------------------------------------------
                | If a Seller record already exists for any reason,
                | keep its status synchronized.
                */
                if ($roleName === 'seller') {
                    Seller::where(
                        'user_id',
                        $user->id
                    )->update([
                        'status' => 'rejected',
                        'rejection_reason' =>
                            $request->rejection_reason,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Logistics rejection
                |--------------------------------------------------------------------------
                */
                if ($roleName === 'logistics') {
                    Logistic::where(
                        'owner_user_id',
                        $user->id
                    )->update([
                        'status' => 'rejected',
                        'rejection_reason' =>
                            $request->rejection_reason,
                        'approved_at' => null,
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | In-app notification
        |--------------------------------------------------------------------------
        */
        $this->createNotification(
            $user->id,
            'Registration Declined',
            'Your '
                . ucfirst($roleName ?? 'user')
                . ' account application was declined. Reason: '
                . $request->rejection_reason,
            'account'
        );

        /*
        |--------------------------------------------------------------------------
        | Email notification
        |--------------------------------------------------------------------------
        */
        $this->notifyByEmail(
            $user,
            'rejected',
            $request->rejection_reason
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with(
                'success',
                ucfirst($roleName ?? 'user')
                    . ' application rejected and notified via email.'
            );
    }
}