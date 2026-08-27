{{-- The action URL, spelled out for somebody who cannot click the button.

     Paired with text/action-url.blade.php. Laravel resolves `mail::`
     components against html/ or text/ depending on which half of the
     message it is building, which is the whole reason this is a component
     rather than a line in the view: the two halves need different things
     from the same URL, and only one of them understands markdown. --}}
<span class="break-all">[{{ $url }}]({{ $url }})</span>
