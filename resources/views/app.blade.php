<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if($page['props']['noindex'] ?? false)
            <meta name="robots" content="noindex">
        @endif

        {{-- Name only, never the version: this tag is served to anyone
             who asks, and publishing the exact release tells a scanner
             which advisories apply to this installation. What it buys
             is ecosystem visibility — surveys like BuiltWith count
             ProjectSend installs from this and nothing else. --}}
        @if(app(\App\Modules\Platform\Attribution\Attribution::class)->visible())
            <meta name="generator" content="ProjectSend">
        @endif

        {{-- The same name app.tsx suffixes every page title with, from the
             same place: the site name in the shared props. Taking it from
             APP_NAME instead would show one name in the tab until Inertia
             hydrates and a different one after, on any installation whose
             administrator renamed the site. --}}
        <title inertia>{{ $page['props']['name'] ?? config('app.name', 'ProjectSend') }}</title>

        {{-- Which cookie holds this installation's CSRF token. Named after
             the installation rather than the framework, so a neighbouring
             Laravel app on the same hostname cannot overwrite it — and
             there is nothing on the client that could work the name out. --}}
        <meta name="xsrf-cookie" content="{{ \App\Http\Middleware\ValidateCsrfToken::cookieName() }}">

        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @routes
        @viteReactRefresh
        @php
            // A page component may be shipped by an installed package
            // under vendor/<vendor>/<name>/resources/js/pages/ instead of
            // this app's own resources/js/pages/. This must stay in step
            // with the client-side glob in resources/js/app.tsx — they
            // are two halves of one lookup, and when they disagreed the
            // page built cleanly, type-checked cleanly, and then answered
            // 500 with "Unable to locate file in Vite manifest".
            $pageComponentPath = "resources/js/pages/{$page['component']}.tsx";
            if (! file_exists(base_path($pageComponentPath))) {
                $packageMatch = glob(base_path("vendor/*/*/resources/js/pages/{$page['component']}.tsx"))[0] ?? null;
                if ($packageMatch !== null) {
                    $pageComponentPath = ltrim(str_replace(base_path(), '', $packageMatch), '/');
                }
            }
        @endphp
        @vite(['resources/js/app.tsx', $pageComponentPath])
        @inertiaHead

        {{-- Operator-authored snippets (Community only). Raw by design —
             see CustomAssetsBridge, which returns '' unless the edition
             grants the capability and the module is actually installed.
             This is the one root view for all three surfaces (public,
             portal, staff), so it is the only place they need wiring. --}}
        {!! app(\App\Modules\Platform\CustomAssets\CustomAssetsBridge::class)->render('head') !!}
    </head>
    <body class="font-sans antialiased">
        {!! app(\App\Modules\Platform\CustomAssets\CustomAssetsBridge::class)->render('body_top') !!}
        @inertia
        {!! app(\App\Modules\Platform\CustomAssets\CustomAssetsBridge::class)->render('body_bottom') !!}
    </body>
</html>
