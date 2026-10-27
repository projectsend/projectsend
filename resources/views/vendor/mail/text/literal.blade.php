{{-- The plain-text half of html/literal.blade.php.

     The line as written. Nothing parses Markdown in a text/plain body, so
     an escape here would arrive as a stray backslash: my\_report\.pdf. --}}
{{ $text }}
