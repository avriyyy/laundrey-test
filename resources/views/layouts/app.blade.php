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
        if (saved === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {
        document.documentElement.classList.add('dark');
    }
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
@if(auth()->user()->role === 'superadmin')
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">Platform console</p>
@else
<p class="font-display text-lg font-bold tracking-tight">{{ auth()->user()->tenant->name }}<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">by Laundrey</p>
@endif
</div>
<nav class="mt-8 flex flex-1 flex-col gap-0.5 text-[13.5px]">
@if(auth()->user()->role === 'superadmin')
<a href="{{ route('admin.dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.tenants.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('orders.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Orders</a>
<a href="{{ route('services.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('services.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Services & pricing</a>
<a href="{{ route('customers.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('customers.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Customers</a>
<a href="{{ route('promos.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('promos.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Promos</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('operations.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('operations.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Operations</a>
@endif
@endif
</nav>
<button onclick="toggleTheme()" class="mb-1 mt-3 flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] font-medium text-ink-2 hover:bg-paper hover:text-ink">
<svg data-theme-icon-moon class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162z"/></svg>
<svg data-theme-icon-sun class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
<span data-theme-label>Dark mode</span>
</button>
<div class="border-t border-line pt-3">
<div class="flex items-center justify-between gap-2 px-1">
<div class="min-w-0"><p class="truncate text-[13px] font-semibold">{{ auth()->user()->role === 'admin' && auth()->user()->tenant ? auth()->user()->tenant->name : auth()->user()->name }}</p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">{{ auth()->user()->role === 'admin' ? 'TENANT' : auth()->user()->role }}</p></div>
@if(auth()->user()->role === 'admin')
<button onclick="openSettings()" title="Settings" class="shrink-0 rounded-md p-1.5 text-muted hover:bg-paper hover:text-ink"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg></button>
@endif
</div>
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
@if(auth()->user()->role === 'admin')
<form method="GET" action="{{ route('orders.index') }}" class="absolute left-1/2 hidden -translate-x-1/2 items-center lg:flex">
<input name="cari" value="{{ request('cari') }}" placeholder="Search orders…" class="h-8 w-64 border-b border-line-strong bg-transparent text-center font-mono text-xs placeholder:text-muted focus:border-primary focus:outline-none">
</form>
@endif
<span class="font-mono text-xs text-ink-2">{{ auth()->user()->name }} <span class="text-muted">/ {{ auth()->user()->role === 'admin' ? 'tenant' : auth()->user()->role }}</span></span>
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
@if(auth()->user()->role === 'superadmin')
<a href="{{ route('admin.dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="whitespace-nowrap px-2 py-1">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="whitespace-nowrap px-2 py-1">Orders</a>
<a href="{{ route('services.index') }}" class="whitespace-nowrap px-2 py-1">Services</a>
<a href="{{ route('customers.index') }}" class="whitespace-nowrap px-2 py-1">Customers</a>
<a href="{{ route('promos.index') }}" class="whitespace-nowrap px-2 py-1">Promos</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('operations.index') }}" class="whitespace-nowrap px-2 py-1">Operations</a>
@endif
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
@auth
@if(auth()->user()->role === 'admin' && isset($layoutTenant) && $layoutTenant)
<div id="settingsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-ink/40" onclick="closeSettings()"></div>
<div class="relative w-full max-w-md border border-line bg-white">
<div class="flex items-center justify-between border-b border-line px-5 py-3.5">
<p class="font-display text-base font-bold tracking-tight">General settings</p>
<button onclick="closeSettings()" class="font-mono text-muted hover:text-ink">×</button>
</div>
<form method="POST" action="{{ route('settings.update') }}" class="flex flex-col gap-4 px-5 py-5">@csrf @method('PUT')
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name</label><input name="name" value="{{ old('name', $layoutTenant->name) }}" required class="h-10 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name</label><input name="owner_name" value="{{ old('owner_name', auth()->user()->name) }}" required class="h-10 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Receipt prefix (3 capital letters)</label><input name="prefix" value="{{ old('prefix', $layoutTenant->prefix) }}" required maxlength="3" class="h-10 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone</label><input name="phone" value="{{ old('phone', $layoutTenant->phone) }}" class="h-10 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Address</label><textarea name="address" rows="2" class="w-full rounded-md border border-line-strong bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none">{{ old('address', $layoutTenant->address) }}</textarea></div>
<p class="text-xs text-muted">New receipts use the new prefix. Old receipts keep theirs.</p>
<div class="flex gap-2 border-t border-line pt-4"><button class="h-10 rounded-md bg-ink px-5 text-sm font-semibold text-white hover:bg-black">Save</button><button type="button" onclick="closeSettings()" class="h-10 rounded-md border border-line-strong px-5 text-sm font-medium hover:bg-paper">Cancel</button></div>
</form>
</div>
</div>
<script>
function openSettings() {
  var m = document.getElementById('settingsModal');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeSettings() {
  var m = document.getElementById('settingsModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSettings(); });
</script>
@endif
@endauth
</body>
</html>
