{{-- Laravel's notification email view, published so the subcopy can spell
     the action URL out differently in each half of the message.

     Upstream writes `[$url]($url)` there. That is right for the HTML half
     and wrong for the text one, where nothing parses markdown: it arrives
     as literal brackets around a duplicated address, which is what a
     badly-built phishing mail looks like — on a password reset, often the
     first mail an installation ever sends anybody. The x-mail::action-url
     component resolves to a different file per half, which is how every
     other component in this message already handles the same problem.

     This is a copy of a framework view, so it does not follow Laravel
     forward on its own. If an upgrade changes the notification layout,
     re-copy it and re-apply the one-line change below. --}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <x-mail::action-url :url="$actionUrl" />
</x-slot:subcopy>
@endisset
</x-mail::message>
