{{-- Laravel's HTML message layout, published so the header and the
     copyright line read the installation's own name instead of the one
     baked into config('app.name') at install time.

     This is a copy of a framework view, so it does not follow Laravel
     forward on its own. If an upgrade changes the layout, re-copy it and
     re-apply the two-line change below. --}}
<?php

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

$siteName = app(Settings::class)->get(Setting::SiteName);
$siteName = is_string($siteName) ? $siteName : 'ProjectSend';
?>
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $siteName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
