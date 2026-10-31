@extends('seller.layout')

@section('content')

@php
$emailVerified = !is_null($user->email_verified_at);

$logoUrl = null;

if ($user->logo) {
try {
if (
\Illuminate\Support\Facades\Storage::disk('s3')
->exists($user->logo)
) {
$logoUrl =
\Illuminate\Support\Facades\Storage::disk('s3')
->url($user->logo);
} elseif (
\Illuminate\Support\Facades\Storage::disk('public')
->exists($user->logo)
) {
$logoUrl = url(
'storage/' . ltrim($user->logo, '/')
);
}
} catch (\Throwable $e) {
report($e);
$logoUrl = null;
}
}

$businessName =
$user->business_name
?? $user->store_name
?? 'My Shop';

$status = strtolower(
$user->status ?? 'active'
);

$statusClass = match ($status) {
'active' => 'success',
'pending' => 'warning',
'suspended' => 'danger',
'deactivated' => 'secondary',
'rejected' => 'dark',
default => 'secondary',
};

$idSubmitted = !empty($user->id_verification);

$businessPermitSubmitted =
!empty($user->business_permit);

$businessDocumentsSubmitted =
!empty($user->business_documents);

$addressText = collect([
$user->house_number,
$user->street_address,
$user->barangay_name,
$user->municipality_name,
$user->province_name,
$user->region_name,
])
->filter()
->implode(', ');
@endphp


<div
  class="
        d-flex
        justify-content-between
        flex-wrap
        flex-md-nowrap
        align-items-center
        pt-3
        pb-2
        mb-4
        border-bottom
    ">
  <div>
    <h1 class="h2 mb-1">
      Seller Account
    </h1>

    <p class="text-muted mb-0">
      Manage your shop, seller information,
      account status, and security.
    </p>
  </div>

  <a
    href="{{ route('seller.storefront', $user) }}"
    target="_blank"
    rel="noopener"
    class="btn btn-outline-primary">
    <i class="bi bi-shop me-1"></i>
    View My Shop
  </a>
</div>

{{-- ACCOUNT HEADER --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <div
      class="
                d-flex
                flex-column
                flex-lg-row
                justify-content-between
                gap-4
            ">

      <div class="d-flex align-items-center gap-3">

        @if($logoUrl)

        <img
          src="{{ $logoUrl }}"
          alt="{{ $businessName }}"
          style="
                            width:84px;
                            height:84px;
                            object-fit:cover;
                            border-radius:18px;
                        ">

        @else

        <div
          class="
                            bg-light
                            border
                            d-flex
                            align-items-center
                            justify-content-center
                        "
          style="
                            width:84px;
                            height:84px;
                            border-radius:18px;
                        ">
          <i
            class="
                                bi
                                bi-shop
                                fs-2
                                text-primary
                            "></i>
        </div>

        @endif

        <div>
          <h2 class="h4 fw-bold mb-1">
            {{ $businessName }}
          </h2>

          <div class="text-muted mb-2">
            {{ $user->name }}
          </div>

          <div class="d-flex gap-2 flex-wrap">

            <span
              class="
                                badge
                                text-bg-{{ $statusClass }}
                            ">
              {{ ucfirst($status) }}
            </span>

            @if($emailVerified)

            <span class="badge text-bg-success">
              <i
                class="
                                        bi
                                        bi-patch-check
                                        me-1
                                    "></i>

              Email Verified
            </span>

            @else

            <span class="badge text-bg-warning">
              Email Not Verified
            </span>

            @endif

          </div>
        </div>

      </div>


      <div class="text-lg-end small">

        <div class="text-muted">
          Member Since
        </div>

        <div class="fw-semibold mb-3">
          {{
                        optional($user->created_at)
                            ->format('F Y')
                    }}
        </div>

        @if($user->approved_at)

        <div class="text-muted">
          Approved
        </div>

        <div class="fw-semibold">
          {{
                            \Carbon\Carbon::parse(
                                $user->approved_at
                            )->format('M d, Y')
                        }}
        </div>

        @endif

      </div>

    </div>

  </div>
</div>


{{-- OVERVIEW --}}

<div class="row g-3 mb-4">

  <div class="col-6 col-xl-3">
    <a
      href="{{ route('seller.products') }}"
      class="text-decoration-none">
      <div
        class="
                    card
                    shadow-sm
                    border-0
                    h-100
                ">
        <div class="card-body">
          <div
            class="
                            d-flex
                            justify-content-between
                            align-items-center
                        ">
            <div>
              <div class="text-muted small">
                Products
              </div>

              <div class="fs-3 fw-bold text-dark">
                {{ $totalProducts }}
              </div>
            </div>

            <i
              class="
                                bi
                                bi-box-seam
                                fs-2
                                text-primary
                            "></i>
          </div>
        </div>
      </div>
    </a>
  </div>


  <div class="col-6 col-xl-3">
    <a
      href="{{ route('seller.orders') }}"
      class="text-decoration-none">
      <div
        class="
                    card
                    shadow-sm
                    border-0
                    h-100
                ">
        <div class="card-body">
          <div
            class="
                            d-flex
                            justify-content-between
                            align-items-center
                        ">
            <div>
              <div class="text-muted small">
                Active Orders
              </div>

              <div class="fs-3 fw-bold text-dark">
                {{ $activeOrders }}
              </div>
            </div>

            <i
              class="
                                bi
                                bi-bag-check
                                fs-2
                                text-warning
                            "></i>
          </div>
        </div>
      </div>
    </a>
  </div>


  <div class="col-6 col-xl-3">
    <a
      href="{{ route('seller.orders') }}"
      class="text-decoration-none">
      <div
        class="
                    card
                    shadow-sm
                    border-0
                    h-100
                ">
        <div class="card-body">
          <div
            class="
                            d-flex
                            justify-content-between
                            align-items-center
                        ">
            <div>
              <div class="text-muted small">
                Completed
              </div>

              <div class="fs-3 fw-bold text-dark">
                {{ $completedOrders }}
              </div>
            </div>

            <i
              class="
                                bi
                                bi-check-circle
                                fs-2
                                text-success
                            "></i>
          </div>
        </div>
      </div>
    </a>
  </div>


  <div class="col-6 col-xl-3">
    <a
      href="{{ route('seller.wallet.index') }}"
      class="text-decoration-none">
      <div
        class="
                    card
                    shadow-sm
                    border-0
                    h-100
                ">
        <div class="card-body">
          <div
            class="
                            d-flex
                            justify-content-between
                            align-items-center
                        ">
            <div>
              <div class="text-muted small">
                Wallet Balance
              </div>

              <div class="fs-4 fw-bold text-dark">
                ₱{{
                                    number_format(
                                        $availableWalletMinor / 100,
                                        2
                                    )
                                }}
              </div>
            </div>

            <i
              class="
                                bi
                                bi-wallet2
                                fs-2
                                text-success
                            "></i>
          </div>
        </div>
      </div>
    </a>
  </div>

</div>


{{-- ORDER SUMMARY --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <h5 class="mb-3">
      Order Summary
    </h5>

    <div class="row text-center g-3">

      <div class="col-6 col-md-3">
        <div class="bg-light rounded-3 p-3">
          <div class="fw-bold fs-4">
            {{ $totalOrders }}
          </div>

          <div class="text-muted small">
            Total Orders
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="bg-light rounded-3 p-3">
          <div class="fw-bold fs-4">
            {{ $activeOrders }}
          </div>

          <div class="text-muted small">
            Active
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="bg-light rounded-3 p-3">
          <div
            class="
                            fw-bold
                            fs-4
                            text-success
                        ">
            {{ $completedOrders }}
          </div>

          <div class="text-muted small">
            Completed
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="bg-light rounded-3 p-3">
          <div
            class="
                            fw-bold
                            fs-4
                            text-danger
                        ">
            {{ $cancelledOrders }}
          </div>

          <div class="text-muted small">
            Cancelled
          </div>
        </div>
      </div>

    </div>

  </div>
</div>


{{-- QUICK MENU --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <h5 class="mb-1">
      Seller Menu
    </h5>

    <p class="text-muted small mb-3">
      Quick access to your shop tools.
    </p>

    @php
    $sellerLinks = [
    [
    'route' => route('seller.products'),
    'icon' => 'bi-box-seam',
    'title' => 'Products',
    'subtitle' =>
    $totalProducts . ' products',
    ],
    [
    'route' => route('seller.orders'),
    'icon' => 'bi-bag-check',
    'title' => 'Orders',
    'subtitle' =>
    $activeOrders . ' active',
    ],
    [
    'route' =>
    route('seller.wallet.index'),
    'icon' => 'bi-wallet2',
    'title' => 'Wallet',
    'subtitle' =>
    '₱' .
    number_format(
    $availableWalletMinor / 100,
    2
    ),
    ],
    [
    'route' =>
    route('seller.vouchers.index'),
    'icon' => 'bi-ticket-perforated',
    'title' => 'Vouchers',
    'subtitle' => 'Manage promos',
    ],
    [
    'route' =>
    route('seller.reports'),
    'icon' => 'bi-bar-chart',
    'title' => 'Reports',
    'subtitle' => 'Sales analytics',
    ],
    [
    'route' =>
    route('seller.notifications'),
    'icon' => 'bi-bell',
    'title' => 'Notifications',
    'subtitle' =>
    $unreadNotifications
    . ' unread',
    ],
    [
    'route' =>
    route('seller.messages.index'),
    'icon' => 'bi-chat-dots',
    'title' => 'Messages',
    'subtitle' =>
    $unreadMessages
    . ' unread',
    ],
    ];
    @endphp

    <div class="row g-3">

      @foreach($sellerLinks as $link)

      <div
        class="
                        col-12
                        col-md-6
                        col-xl-3
                    ">
        <a
          href="{{ $link['route'] }}"
          class="text-decoration-none">
          <div
            class="
                                border
                                rounded-3
                                p-3
                                h-100
                            ">
            <div
              class="
                                    d-flex
                                    align-items-center
                                    gap-3
                                ">
              <i
                class="
                                        bi
                                        {{ $link['icon'] }}
                                        fs-3
                                        text-primary
                                    "></i>

              <div>
                <div
                  class="
                                            fw-semibold
                                            text-dark
                                        ">
                  {{ $link['title'] }}
                </div>

                <div
                  class="
                                            small
                                            text-muted
                                        ">
                  {{ $link['subtitle'] }}
                </div>
              </div>
            </div>
          </div>
        </a>
      </div>

      @endforeach

    </div>

  </div>
</div>


{{-- ACCOUNT / COMPLIANCE --}}

<div class="row g-4 mb-4">

  <div class="col-lg-6">
    <div
      class="
                card
                shadow-sm
                border-0
                h-100
            ">
      <div class="card-body p-4">

        <h5 class="mb-3">
          Account Information
        </h5>

        <div
          class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    ">
          <span class="text-muted">
            Account Status
          </span>

          <span
            class="
                            badge
                            text-bg-{{ $statusClass }}
                        ">
            {{ ucfirst($status) }}
          </span>
        </div>

        <div
          class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    ">
          <span class="text-muted">
            Email
          </span>

          @if($emailVerified)

          <span
            class="
                                text-success
                                fw-semibold
                            ">
            Verified
          </span>

          @else

          <a
            href="{{
                                route(
                                    'seller.email.verify'
                                )
                            }}"
            class="
                                text-warning
                                fw-semibold
                                text-decoration-none
                            ">
            Verify Email
          </a>

          @endif
        </div>

        <div
          class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    ">
          <span class="text-muted">
            Member Since
          </span>

          <span class="fw-semibold">
            {{
                            optional(
                                $user->created_at
                            )->format('M d, Y')
                        }}
          </span>
        </div>

        <div
          class="
                        d-flex
                        justify-content-between
                        py-2
                    ">
          <span class="text-muted">
            Approval Date
          </span>

          <span class="fw-semibold">
            @if($user->approved_at)
            {{
                                \Carbon\Carbon::parse(
                                    $user->approved_at
                                )->format('M d, Y')
                            }}
            @else
            —
            @endif
          </span>
        </div>

      </div>
    </div>
  </div>


  <div class="col-lg-6">
    <div
      class="
                card
                shadow-sm
                border-0
                h-100
            ">
      <div class="card-body p-4">

        <h5 class="mb-3">
          Seller Compliance
        </h5>

        <div
          class="
                        d-flex
                        justify-content-between
                        align-items-center
                        py-2
                        border-bottom
                    ">
          <span>
            Identity Verification
          </span>

          @if($idSubmitted)
          <span class="badge text-bg-success">
            Submitted
          </span>
          @else
          <span class="badge text-bg-warning">
            Missing
          </span>
          @endif
        </div>

        <div
          class="
                        d-flex
                        justify-content-between
                        align-items-center
                        py-2
                        border-bottom
                    ">
          <span>
            Business Permit
          </span>

          @if($businessPermitSubmitted)
          <span class="badge text-bg-success">
            Submitted
          </span>
          @else
          <span class="badge text-bg-secondary">
            Not Available
          </span>
          @endif
        </div>

        <div
          class="
                        d-flex
                        justify-content-between
                        align-items-center
                        py-2
                    ">
          <span>
            Business Documents
          </span>

          @if($businessDocumentsSubmitted)
          <span class="badge text-bg-success">
            Submitted
          </span>
          @else
          <span class="badge text-bg-secondary">
            Not Available
          </span>
          @endif
        </div>

      </div>
    </div>
  </div>

</div>


@if($status === 'suspended')

<div class="card border-danger shadow-sm mb-4">
  <div class="card-body p-4">

    <h5 class="text-danger">
      Account Suspended
    </h5>

    @if($user->appeal_submitted_at)

    <div class="alert alert-warning mb-0">
      Appeal submitted on

      <strong>
        {{
                            \Carbon\Carbon::parse(
                                $user->appeal_submitted_at
                            )->format('M d, Y h:i A')
                        }}
      </strong>.

      Please wait for administrator review.
    </div>

    @else

    <form
      method="POST"
      action="{{
                        route(
                            'seller.account.appeal'
                        )
                    }}">
      @csrf

      <div class="mb-3">
        <label
          for="appeal_message"
          class="form-label">
          Appeal Message
        </label>

        <textarea
          name="appeal_message"
          id="appeal_message"
          class="form-control"
          rows="4"
          maxlength="2000"
          required>{{ old('appeal_message') }}</textarea>
      </div>

      <button
        type="submit"
        class="btn btn-danger">
        Submit Appeal
      </button>
    </form>

    @endif

  </div>
</div>

@endif


@if($errors->updateAccount->any())

<div class="alert alert-danger">
  <strong>
    Please check the account form.
  </strong>

  <ul class="mb-0 mt-2">
    @foreach(
    $errors->updateAccount->all()
    as $error
    )
    <li>
      {{ $error }}
    </li>
    @endforeach
  </ul>
</div>

@endif


{{-- ACCOUNT FORM --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <form
      method="POST"
      action="{{ route('seller.account.update') }}"
      enctype="multipart/form-data"
      id="accountForm">
      @csrf
      @method('PUT')

      <h5 class="mb-3">
        Personal Information
      </h5>

      <div class="row g-3 mb-4">

        <div class="col-md-4">
          <label
            for="last_name"
            class="form-label">
            Last Name
            <span class="text-danger">*</span>
          </label>

          <input
            type="text"
            name="last_name"
            id="last_name"
            class="form-control"
            value="{{
                            old(
                                'last_name',
                                $user->last_name
                            )
                        }}"
            required>
        </div>

        <div class="col-md-4">
          <label
            for="first_name"
            class="form-label">
            First Name
            <span class="text-danger">*</span>
          </label>

          <input
            type="text"
            name="first_name"
            id="first_name"
            class="form-control"
            value="{{
                            old(
                                'first_name',
                                $user->first_name
                            )
                        }}"
            required>
        </div>

        <div class="col-md-4">
          <label
            for="middle_name"
            class="form-label">
            Middle Name
          </label>

          <input
            type="text"
            name="middle_name"
            id="middle_name"
            class="form-control"
            value="{{
                            old(
                                'middle_name',
                                $user->middle_name
                            )
                        }}">
        </div>

        <div class="col-md-3">
          <label
            for="sex"
            class="form-label">
            Sex
          </label>

          <select
            name="sex"
            id="sex"
            class="form-select"
            required>
            <option value="">
              Select Sex
            </option>

            <option
              value="Male"
              {{
                                old(
                                    'sex',
                                    $user->sex
                                ) === 'Male'
                                    ? 'selected'
                                    : ''
                            }}>
              Male
            </option>

            <option
              value="Female"
              {{
                                old(
                                    'sex',
                                    $user->sex
                                ) === 'Female'
                                    ? 'selected'
                                    : ''
                            }}>
              Female
            </option>
          </select>
        </div>

        <div class="col-md-3">
          <label
            for="email"
            class="form-label">
            Email
          </label>

          <input
            type="email"
            name="email"
            id="email"
            class="form-control"
            value="{{
                            old(
                                'email',
                                $user->email
                            )
                        }}"
            required>

          <div class="form-text">
            Changing email requires verification again.
          </div>
        </div>

        <div class="col-md-3">
          <label
            for="mobile_number"
            class="form-label">
            Contact No.
          </label>

          <input
            type="text"
            name="mobile_number"
            id="mobile_number"
            class="form-control"
            value="{{
                            old(
                                'mobile_number',
                                $user->mobile_number
                            )
                        }}"
            required>
        </div>

        <div class="col-md-3">
          <label
            for="birthday"
            class="form-label">
            Birthday
          </label>

          <input
            type="date"
            name="birthday"
            id="birthday"
            class="form-control"
            value="{{
                            old(
                                'birthday',
                                $user->birthday
                                    ? \Carbon\Carbon::parse(
                                        $user->birthday
                                    )->format('Y-m-d')
                                    : ''
                            )
                        }}"
            max="{{ now()->format('Y-m-d') }}"
            required>
        </div>

        <div class="col-md-3">
          <label
            for="age"
            class="form-label">
            Age
          </label>

          <input
            type="number"
            id="age"
            class="form-control"
            value="{{
                            $user->birthday
                                ? \Carbon\Carbon::parse(
                                    $user->birthday
                                )->age
                                : $user->age
                        }}"
            readonly>
        </div>

      </div>


      <h5 class="mb-3">
        Address
      </h5>

      @if($addressText)
      <div class="alert alert-light border">
        <i class="bi bi-geo-alt me-1"></i>
        {{ $addressText }}
      </div>
      @endif

      <div class="row g-3 mb-4">

        <div class="col-md-4">
          <label
            for="region"
            class="form-label">
            Region
          </label>

          <input
            type="hidden"
            name="region"
            id="region-hidden"
            value="{{
                            old(
                                'region',
                                $user->region
                            )
                        }}">

          <select
            id="region"
            class="form-select"
            required>
            <option value="">
              Select Region
            </option>
          </select>
        </div>

        <div class="col-md-4">
          <label
            for="province"
            class="form-label">
            Province/City
          </label>

          <input
            type="hidden"
            name="province"
            id="province-hidden"
            value="{{
                            old(
                                'province',
                                $user->province
                            )
                        }}">

          <select
            id="province"
            class="form-select"
            required
            disabled>
            <option value="">
              Select Province/City
            </option>
          </select>
        </div>

        <div
          class="col-md-4"
          id="municipality-field">
          <label
            for="municipality"
            class="form-label">
            Municipality
          </label>

          <input
            type="hidden"
            name="municipality"
            id="municipality-hidden"
            value="{{
                            old(
                                'municipality',
                                $user->municipality
                            )
                        }}">

          <select
            id="municipality"
            class="form-select"
            required
            disabled>
            <option value="">
              Select Municipality
            </option>
          </select>
        </div>

        <div class="col-md-4">
          <label
            for="barangay"
            class="form-label">
            Barangay
          </label>

          <input
            type="hidden"
            name="barangay"
            id="barangay-hidden"
            value="{{
                            old(
                                'barangay',
                                $user->barangay
                            )
                        }}">

          <select
            id="barangay"
            class="form-select"
            required
            disabled>
            <option value="">
              Select Barangay
            </option>
          </select>
        </div>

        <div class="col-md-4">
          <label
            for="house_number"
            class="form-label">
            House Number
          </label>

          <input
            type="text"
            name="house_number"
            id="house_number"
            class="form-control"
            value="{{
                            old(
                                'house_number',
                                $user->house_number
                            )
                        }}">
        </div>

        <div class="col-md-4">
          <label
            for="street_address"
            class="form-label">
            Street
          </label>

          <input
            type="text"
            name="street_address"
            id="street_address"
            class="form-control"
            value="{{
                            old(
                                'street_address',
                                $user->street_address
                            )
                        }}">
        </div>

      </div>


      <h5 class="mb-3">
        Business Information
      </h5>

      <div class="row g-3 mb-4">

        <div class="col-md-6">
          <label
            for="business_name"
            class="form-label">
            Business Name
          </label>

          <input
            type="text"
            name="business_name"
            id="business_name"
            class="form-control"
            value="{{
                            old(
                                'business_name',
                                $user->business_name
                                    ?? $user->store_name
                            )
                        }}"
            required>
        </div>

        <div class="col-md-6">
          <label
            for="logo"
            class="form-label">
            Shop Logo
          </label>

          <input
            type="file"
            name="logo"
            id="logo"
            class="form-control"
            accept="image/jpeg,image/png,image/webp">

          <small class="text-muted">
            JPG, PNG or WEBP. Max 2 MB.
          </small>

          @if($logoUrl)

          <div
            class="
                                mt-3
                                d-flex
                                align-items-center
                                gap-3
                            ">
            <img
              src="{{ $logoUrl }}"
              alt="{{ $businessName }}"
              style="
                                    width:80px;
                                    height:80px;
                                    object-fit:cover;
                                    border-radius:10px;
                                ">

            <div class="form-check">
              <input
                type="checkbox"
                name="remove_logo"
                value="1"
                id="remove_logo"
                class="form-check-input">

              <label
                for="remove_logo"
                class="form-check-label">
                Remove current logo
              </label>
            </div>
          </div>

          @endif
        </div>

        <div class="col-12">
          <label
            for="preferred_logistic_id"
            class="form-label">
            Preferred Logistics Company
          </label>

          <select
            name="preferred_logistic_id"
            id="preferred_logistic_id"
            class="form-select">
            <option value="">
              Select Logistics Company
            </option>

            @foreach($logistics as $logistic)
            <option
              value="{{ $logistic->id }}"
              {{
                                    (string) old(
                                        'preferred_logistic_id',
                                        $user->preferred_logistic_id
                                    )
                                    ===
                                    (string) $logistic->id
                                        ? 'selected'
                                        : ''
                                }}>
              {{ $logistic->company_name }}
            </option>
            @endforeach
          </select>
        </div>

      </div>

      <button
        type="submit"
        class="btn btn-bili-hub">
        <i class="bi bi-save me-1"></i>
        Update Account
      </button>

    </form>

  </div>
</div>


{{-- PASSWORD --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <h5>
      Change Password
    </h5>

    @if(session('password_success'))
    <div class="alert alert-success">
      {{ session('password_success') }}
    </div>
    @endif

    @if($errors->passwordUpdate->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach(
        $errors->passwordUpdate->all()
        as $error
        )
        <li>
          {{ $error }}
        </li>
        @endforeach
      </ul>
    </div>
    @endif

    <form
      method="POST"
      action="{{
                route(
                    'seller.account.password.update'
                )
            }}"
      class="row g-3">
      @csrf
      @method('PUT')

      <div class="col-md-4">
        <label class="form-label">
          Current Password
        </label>

        <input
          type="password"
          name="current_password"
          id="current_password"
          class="form-control"
          required>
      </div>

      <div class="col-md-4">
        <label class="form-label">
          New Password
        </label>

        <input
          type="password"
          name="password"
          id="new_password"
          class="form-control"
          minlength="8"
          required>
      </div>

      <div class="col-md-4">
        <label class="form-label">
          Confirm Password
        </label>

        <input
          type="password"
          name="password_confirmation"
          class="form-control"
          minlength="8"
          required>
      </div>

      <div class="col-12">
        <button
          type="submit"
          class="btn btn-outline-primary">
          Change Password
        </button>
      </div>
    </form>

  </div>
</div>


{{-- WALLET --}}

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-4">

    <div
      class="
                d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
            ">
      <div>
        <h5 class="mb-1">
          Seller Wallet
        </h5>

        <div class="text-muted">
          Available:
          <strong class="text-success">
            ₱{{
                            number_format(
                                $availableWalletMinor / 100,
                                2
                            )
                        }}
          </strong>

          &nbsp; | &nbsp;

          Pending:
          <strong>
            ₱{{
                            number_format(
                                $pendingWalletMinor / 100,
                                2
                            )
                        }}
          </strong>
        </div>
      </div>

      <a
        href="{{ route('seller.wallet.index') }}"
        class="btn btn-outline-success">
        Open Wallet
      </a>
    </div>

  </div>
</div>


@push('scripts')

<script
  type="application/json"
  id="seller-account-data">
  @php
  $sellerAccountData = [
    'saved' => [
      'region' =>
      old('region', $user->region),

      'province' =>
      old('province', $user->province),

      'municipality' =>
      old(
        'municipality',
        $user->municipality
      ),

      'barangay' =>
      old('barangay', $user->barangay),
    ],
  ];
  @endphp
  @json($sellerAccountData)
</script>

@vite('resources/js/seller/account.js')

@endpush

@endsection
