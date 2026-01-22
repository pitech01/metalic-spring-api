<x-mail::message>
# Welcome, {{ $user->name }}!

Thank you for registering with {{ config('app.name') }}. Please click the button below to verify your email address.

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
