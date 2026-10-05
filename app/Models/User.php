<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'address',
    'store_name',
    'business_address',
    'business_documents',
    'id_verification',
    'mobile_number',
    'selling_categories',
    'first_name',
    'middle_name',
    'last_name',
    'sex',
    'birthday',
    'age',
    'house_number',
    'street_address',
    'barangay',
    'barangay_name',
    'municipality',
    'municipality_name',
    'province',
    'province_name',
    'region',
    'region_name',
    'business_name',
    'logo',
    'business_permit',
    'vehicle_type',
    'license_number',
    'rider_documents',
    'or_document',
    'cr_document',
    'status',
    'rejection_reason',
    'approved_at',
    'logistic_id',
    'hub_id',
    'logistic_status',
    'logistic_approved_at',
    'logistic_rejection_reason',
    'max_capacity',
    'current_load',
    'availability_status',
    'assigned_zone',
    'last_active_at',
    'suspended_at',
    'appeal_submitted_at',
    'appeal_message',
    'preferred_logistic_id',
    'daily_pickups_completed',
    'daily_deliveries_completed',
    'last_quota_reset_date',

    'latitude',
    'longitude',
    'location_updated_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DEACTIVATED = 'deactivated';
    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'selling_categories' => 'array',
            'approved_at' => 'datetime',
            'logistic_approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'appeal_submitted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE CHECKING
    |--------------------------------------------------------------------------
    */

    public function hasRole(string $role): bool
    {
        // Load roles only once for this User instance.
        // This prevents repeated role queries while rendering the page.
        $this->loadMissing('roles');

        return $this->roles->contains('name', $role);
    }

    public function hasAnyRole(array $roles): bool
    {
        // Load roles only once for this User instance.
        $this->loadMissing('roles');

        return $this->roles
            ->pluck('name')
            ->intersect($roles)
            ->isNotEmpty();
    }

    /**
     * Check if the user has administrator privileges.
     * Both admin and superadmin are allowed to access admin areas.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([
            'admin',
            'superadmin'
        ]);
    }

    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    public function allowedCategoryIds(): array
    {
        return is_array($this->selling_categories)
            ? $this->selling_categories
            : [];
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('buyer');
    }

    public function isRider(): bool
    {
        return $this->hasRole('rider');
    }

    public function isLogisticOwner(): bool
    {
        return $this->hasRole('logistics');
    }

    public function isGuest(): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isDeactivated(): bool
    {
        return $this->status === self::STATUS_DEACTIVATED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function canLogin(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || $this->status === self::STATUS_SUSPENDED;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PENDING => 'warning',
            self::STATUS_SUSPENDED => 'danger',
            self::STATUS_DEACTIVATED => 'secondary',
            self::STATUS_REJECTED => 'dark',
            default => 'secondary',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function warnings()
    {
        return $this->hasMany(Warning::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'admin_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'seller_id');
    }

    public function complaintsAgainst()
    {
        return $this->hasMany(
            SupportTicket::class,
            'against_user_id'
        );
    }

    public function unreadMessagesCount()
    {
        return $this->receivedMessages()
            ->where('is_read', false)
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | LOGISTICS
    |--------------------------------------------------------------------------
    */

    public function logistic()
    {
        return $this->belongsTo(Logistic::class);
    }

    public function ownedLogistic()
    {
        return $this->hasOne(
            Logistic::class,
            'owner_user_id'
        );
    }

    public function logisticRiders()
    {
        return $this->hasMany(
            User::class,
            'logistic_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER
    |--------------------------------------------------------------------------
    */

    public function riderProfile()
    {
        return $this->hasOne(Rider::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HUB
    |--------------------------------------------------------------------------
    */

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function hubRiders()
    {
        return $this->hasMany(
            User::class,
            'hub_id'
        );
    }

    public function logisticStatusBadgeClass(): string
    {
        return match ($this->logistic_status) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES
    |--------------------------------------------------------------------------
    */

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER
    |--------------------------------------------------------------------------
    */

    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
    }

        try {
            if (Storage::disk('s3')->exists($this->logo)) {
                return Storage::disk('s3')->url($this->logo);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }

        return null;
    }
     
       /*
|--------------------------------------------------------------------------
| WISHLIST
|--------------------------------------------------------------------------
*/

public function wishlists()
{
    return $this->hasMany(Wishlist::class);
}

public function wishlistProducts()
{
    return $this->belongsToMany(Product::class, 'wishlists')
        ->withTimestamps();
}

    
}