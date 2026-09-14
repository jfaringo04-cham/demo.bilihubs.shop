@component('mail::message')
# Hello {{ $user->first_name }},

{{ $messageContent }}

@if($user->role === 'seller')
You can view your products and compliance status by logging into your seller account.
@endif

@component('mail::button', ['url' => route('login')])
Log In to {{ config('app.name') }}
@endcomponent

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }} Compliance Team
@endcomponent
