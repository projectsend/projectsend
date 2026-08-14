{{-- The plain-text half of html/footer.blade.php. Laravel renders both
     parts for every markdown notification (MailChannel builds an html
     and a text view), so leaving this one unpublished would put the
     attribution in only one of the two bodies a client might read. --}}
{{ $slot }}
@if ($mail_attribution ?? true)

{{ __('Powered by ProjectSend') }}: {{ config('projectsend.links.website') }}
@endif
