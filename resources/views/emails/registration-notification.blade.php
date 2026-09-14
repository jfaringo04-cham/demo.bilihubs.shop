@component('mail::message')
# Welcome to {{ config('app.name') }}, {{ $user->first_name }}!

Thank you for registering as a {{ ucfirst($user->role) }} on {{ config('app.name') }}.

Your application has been received and is pending review by our administrator. You will be notified via email once your account has been approved.

@component('mail::button', ['url' => $loginUrl])
Visit {{ config('app.name') }}
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
