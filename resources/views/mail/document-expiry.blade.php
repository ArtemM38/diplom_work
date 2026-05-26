<x-mail::message>
# @if ($status === 'expired')
Документ просрочен
@else
Истекает срок действия документа
@endif

Здравствуйте, {{ $user->display_name ?? $user->name }}!

**{{ $documentLabel }}**

@if ($status === 'expired')
Срок действия истёк **{{ \Carbon\Carbon::parse($expiryDate)->format('d.m.Y') }}**. Пожалуйста, загрузите актуальный документ в личном кабинете.
@else
Срок действия до **{{ \Carbon\Carbon::parse($expiryDate)->format('d.m.Y') }}** (осталось {{ $daysLeft }} дн.). Обновите документ заранее.
@endif

<x-mail::button :url="url('/profile')">
Личный кабинет
</x-mail::button>

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
