<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TenantWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Tenant::query()->withCount(['users', 'services', 'orders']);

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('prefix', 'like', '%'.$kataKunci.'%');
            });
        }

        $tenants = $kueri->orderBy('name')->paginate(12)->withQueryString();
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');

        return view('tenants.index', compact('tenants', 'totalOrders', 'totalRevenue'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->loadCount(['users', 'services', 'orders']);
        $orders = Order::with(['customer', 'service'])->where('tenant_id', $tenant->id)->orderBy('created_at', 'desc')->paginate(10);
        $revenue = (float) Order::where('tenant_id', $tenant->id)->where('payment_status', 'paid')->sum('total_price');
        $admins = $tenant->users()->where('role', 'tenant')->get(['id', 'name', 'email']);

        return view('tenants.show', compact('tenant', 'orders', 'revenue', 'admins'));
    }

    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $admin = $request->validate([
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data, $admin) {
            $tenant = Tenant::create($data);

            User::create([
                'tenant_id' => $tenant->id,
                'name' => $admin['admin_name'],
                'email' => $admin['admin_email'],
                'password' => Hash::make($admin['admin_password']),
                'role' => 'tenant',
            ]);
        });

        return redirect()->route('admin.tenants.index')->with('sukses', 'Tenant added with login account.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($this->validated($request, $tenant->id));

        return redirect()->route('admin.tenants.show', $tenant)->with('sukses', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('sukses', 'Tenant deleted with all its data.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignore = null): array
    {
        if ($request->input('prefix')) {
            $request->merge(['prefix' => strtoupper((string) $request->input('prefix'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix'.($ignore ? ','.$ignore : '')],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'prefix.regex' => 'Prefix must be 3 capital letters',
            'prefix.unique' => 'Prefix already taken by another laundry',
        ]);
    }
}
