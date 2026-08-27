{{-- The plain-text half of html/action-url.blade.php.

     Just the URL. Laravel's own view writes `[$url]($url)` here, which is
     correct for the HTML half and wrong for this one: nothing parses
     markdown in a text/plain body, so it arrives as literal brackets with
     the address duplicated inside them — the shape a phishing template
     has. Seen in a real password reset, which is often the first mail an
     installation ever sends somebody. --}}
{{ $url }}
