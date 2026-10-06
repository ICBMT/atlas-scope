<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'AtlasScope — see your application in 3D')</title>
    <meta name="description" content="AtlasScope scans a Laravel, C++, C# or Python project and turns it into an explorable 3D map of routes, controllers, models, classes, namespaces, build targets and database tables.">
    <link rel="icon" href="{{ \Atlas\Scope\Support\Assets::url('favicon.svg') }}" type="image/svg+xml">

    {{--
        The renderer ships compiled inside the package, so an installation needs
        no Node toolchain: `vendor:publish --tag=atlas-assets` serves it from
        public/, and until then a route streams it. The version query comes from
        the build itself, so a rebuilt bundle never serves a stale copy.
    --}}
    <link rel="stylesheet" href="{{ \Atlas\Scope\Support\Assets::url('atlas.css') }}">
    <script type="module" src="{{ \Atlas\Scope\Support\Assets::url('atlas.js') }}"></script>
    @stack('head')
</head>
<body class="@yield('body-class')">
    @yield('content')
    @stack('scripts')
</body>
</html>
