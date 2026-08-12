<!DOCTYPE html>
@php
    // Account theme wins; guests fall back to the plain `theme` cookie so
    // the choice survives logout. Unknown values collapse to the default.
    $theme = auth()->user()?->theme ?? request()->cookie('theme');
    $theme = array_key_exists((string) $theme, \App\Models\User::THEMES) ? $theme : 'mono';
@endphp
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    {{ $slot }}
</body>
</html>
