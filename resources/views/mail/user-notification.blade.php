<x-mail::message>
# {{ $title }}

Здравствуйте, {{ $user->display_name ?? $user->name }}!

{{ $message }}

@if ($actionUrl && $actionLabel)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
