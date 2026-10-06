<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Laundrey')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
(function () {
    try {
        var saved = localStorage.getItem('laundrey-theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {}
})();
function toggleTheme() {
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('laundrey-theme', dark ? 'dark' : 'light'); } catch (e) {}
    document.querySelectorAll('[data-theme-icon-moon]').forEach(function (el) { el.classList.toggle('hidden', dark); });
    document.querySelectorAll('[data-theme-icon-sun]').forEach(function (el) { el.classList.toggle('hidden', !dark); });
    document.querySelectorAll('[data-theme-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
}
document.addEventListener('DOMContentLoaded', function () {
    var dark = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-icon-moon]').forEach(function (el) { el.classList.toggle('hidden', dark); });
    document.querySelectorAll('[data-theme-icon-sun]').forEach(function (el) { el.classList.toggle('hidden', !dark); });
    document.querySelectorAll('[data-theme-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
});
</script>
</head>
<body class="bg-paper font-sans text-sm text-ink antialiased">
<div class="flex min-h-screen">

@auth
{{-- Sidebar (signed in only) --}}
<aside class="fixed inset-y-0 left-0 hidden w-60 shrink-0 flex-col border-r border-line bg-white px-4 py-6 md:flex">
<div class="px-1">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
</div>
<nav class="mt-8 flex flex-1 flex-col gap-0.5 text-[13.5px]">
<a href="{{ route('dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('orders.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Orders</a>
<a href="{{ route('services.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('services.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Services & pricing</a>
<a href="{{ route('customers.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('customers.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Customers</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('operations.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('operations.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Operations</a>
@endif
</nav>
<button onclick="toggleTheme()" class="mb-1 mt-3 flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] font-medium text-ink-2 hover:bg-paper hover:text-ink">
<svg data-theme-icon-moon class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162z"/></svg>
<svg data-theme-icon-sun class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
<span data-theme-label>Dark mode</span>
</button>
<div class="border-t border-line pt-3">
<p class="truncate px-1 text-[13px] font-semibold">{{ auth()->user()->name }}</p>
<p class="px-1 font-mono text-[10px] uppercase tracking-[0.18em] text-muted">{{ auth()->user()->role }}</p>
<form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-md border border-line px-2 py-1.5 text-[13px] font-medium hover:border-line-strong hover:bg-paper">Log out</button></form>
</div>
</aside>
@endauth

<div class="flex min-h-screen min-w-0 flex-1 flex-col @auth md:ml-60 @endauth">
{{-- Topbar --}}
<header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-line bg-paper/95 px-4 py-2.5 backdrop-blur md:px-8">
@guest
<a href="{{ route('home') }}" class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></a>
@endguest
@auth
<p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">@yield('breadcrumb', 'Laundrey')</p>
@endauth
<div class="flex items-center gap-2">
@auth
<form method="GET" action="{{ route('orders.index') }}" class="absolute left-1/2 hidden -translate-x-1/2 items-center lg:flex">
<input name="cari" value="{{ request('cari') }}" placeholder="Search orders…" class="h-8 w-64 border-b border-line-strong bg-transparent text-center font-mono text-xs placeholder:text-muted focus:border-primary focus:outline-none">
</form>
<span class="font-mono text-xs text-ink-2">{{ auth()->user()->name }} <span class="text-muted">/ {{ auth()->user()->role }}</span></span>
@else
<button onclick="toggleTheme()" title="Toggle theme" class="mr-3 rounded-md border border-line p-1.5 text-muted hover:text-ink">
<svg data-theme-icon-moon class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162z"/></svg>
<svg data-theme-icon-sun class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
</button>
@endauth
</div>
</header>

@auth
{{-- Mobile nav (signed in only) --}}
<nav class="flex gap-1 overflow-x-auto border-b border-line bg-white px-3 py-2 text-[13px] font-medium md:hidden">
<a href="{{ route('dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="whitespace-nowrap px-2 py-1">Orders</a>
<a href="{{ route('services.index') }}" class="whitespace-nowrap px-2 py-1">Services</a>
<a href="{{ route('customers.index') }}" class="whitespace-nowrap px-2 py-1">Customers</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('operations.index') }}" class="whitespace-nowrap px-2 py-1">Operations</a>
@endif
</nav>
@endauth

<main class="mx-auto w-full flex-1 px-4 py-8 @hasSection('wide') max-w-none md:px-10 @else max-w-6xl md:px-8 md:py-10 @endif">
@if(session('sukses'))
<p class="mb-6 flex items-start justify-between gap-3 border-l-2 border-emerald-600 bg-white px-4 py-3 text-[13px]"><span>✓ {{ session('sukses') }}</span><button onclick="this.parentElement.remove()" class="shrink-0 font-mono text-muted hover:text-ink">×</button></p>
@endif
@if($errors->any())
<div class="mb-6 border-l-2 border-red-600 bg-white px-4 py-3 text-[13px]"><div class="flex items-start justify-between gap-3"><span>× Something went wrong</span><button onclick="this.closest('div').remove()" class="shrink-0 font-mono text-muted hover:text-ink">×</button></div>
<ul class="ml-4 mt-1 list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
@yield('content')
</main>
<footer class="mx-auto w-full pb-6 flex items-center justify-between border-t border-line pt-4 font-mono text-[11px] uppercase tracking-[0.18em] text-muted @hasSection('wide') max-w-none px-4 md:px-10 @else max-w-6xl px-4 md:px-8 @endif">
<span>Laundrey - laundry tracking</span><span>Est. 2026</span>
</footer>
</div>
</div>
</body>
</html>
