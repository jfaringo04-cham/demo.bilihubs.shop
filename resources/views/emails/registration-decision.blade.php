@component('mail::message')
# Hello {{ $user->first_name }},

Your registration on {{ config('app.name') }} has been **{{ $decision }}**.

@if($decision === 'approved')
You can now log in and start using your account.
@else
**Reason:** {{ $reason }}
@endif

@component('mail::button', ['url' => $loginUrl])
{{ $decision === 'approved' ? 'Log In' : 'Learn More' }}
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
