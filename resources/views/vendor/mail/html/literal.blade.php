{{-- One line of a notification, as text rather than Markdown.

     Paired with text/literal.blade.php. Most lines carry a value somebody
     else chose — a display name, a file name, a comment author — and the
     HTML half is parsed as Markdown, which Blade's escaping does not cover:
     a client named `[Re-verify](https://…)` became a working link in mail
     sent to staff (GHSA-64r6-jqg3-7x36). Every character Markdown reads as
     syntax is backslash-escaped, so it renders as itself.

     An Htmlable line is code that means its Markdown — a link the
     notification builds itself — and is left alone. Never wrap a line in
     HtmlString if it carries a value from outside. --}}
@if ($text instanceof \Illuminate\Contracts\Support\Htmlable)
{{ $text }}
@else
{{ preg_replace('/[\\\\`*_{}\[\]()#+.!|~-]/', '\\\\$0', (string) $text) }}
@endif
