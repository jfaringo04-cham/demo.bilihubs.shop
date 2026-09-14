@component('mail::message')
# New Registration Received

A new user has registered on {{ config('app.name') }} and requires your review.

**User Details:**
- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Role:** {{ ucfirst($user->role) }}
- **Registered At:** {{ $user->created_at->format('M d, Y h:i A') }}

@component('mail::button', ['url' => $reviewUrl])
Review Application
@endComponent

Please review and approve or reject this application.

Thanks,<br>
{{ config('app.name') }} System
@endcomponent
