{{-- Published from Laravel's own html/footer.blade.php to add the
     attribution line. Everything above it is the framework's markup
     unchanged: the four theme stylesheets target `.footer`,
     `.footer p` and `.footer a` against exactly this structure, so
     the new paragraph deliberately reuses that vocabulary rather than
     introducing a selector every theme would have to define
     (docs/theming-email-checklist.md).

     $slot is the copyright line from the message template; the second
     paragraph is ours, and disappears on an installation that has
     white-labelled itself — see $mail_attribution in
     ThemedMailChannel. Defaulting to true matters: this partial also
     renders for mail sent outside the notification channel, which
     never sets the variable. --}}
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
@if ($mail_attribution ?? true)
{{-- Each edition has its own front door, so this is resolved rather than read straight out of config — see OfficialLinks. --}}
<p><a href="{{ app(App\Modules\Platform\OfficialLinks::class)->website() }}">{{ __('Powered by ProjectSend') }}</a></p>
@endif
</td>
</tr>
</table>
</td>
</tr>
