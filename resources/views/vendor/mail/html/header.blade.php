@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (($mail_theme_context['logo_url'] ?? null) !== null)
<img src="{{ $mail_theme_context['logo_url'] }}" class="logo" alt="{{ $slot }}">
@elseif (($mail_theme ?? 'default') === 'minimal')
<span class="header-label">{{ $slot }}</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
