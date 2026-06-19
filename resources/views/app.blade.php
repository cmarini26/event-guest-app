<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>guestlist. — Event guest management</title>
    <meta name="description" content="Collect RSVPs, track dietary preferences, send invitation emails, and export your guest list — all from one clean dashboard.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="guestlist. — Event guest management">
    <meta property="og:description" content="The simplest way to manage your event guests. No ticketing. No payments. Just clean RSVPs.">
    <meta property="og:url" content="{{ config('app.url') }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="guestlist. — Event guest management">
    <meta name="twitter:description" content="The simplest way to manage your event guests.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
