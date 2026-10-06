<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CustomerWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = User::query()->where('tenant_id', auth()->user()->tenant_id)->where('role', 'pelanggan')->withCount('orders')->withSum('orders as spent_sum', 'total_price');

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('phone', 'like', '%'.$kataKunci.'%')
                    ->orWhere('id', $kataKunci);
            });
        }

        $customers = $kueri->orderBy('name')->paginate(12)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $kataKunci = trim($request->query('q', ''));

        if (strlen($kataKunci) < 2) {
            return response()->json(['data' => []]);
        }

        $rows = User::where('tenant_id', auth()->user()->tenant_id)->where('role', 'pelanggan')
            ->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('phone', 'like', '%'.$kataKunci.'%');
            })
            ->withCount('orders')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'code' => $u->customerCode(),
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'orders' => $u->orders_count,
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
        ]);

        User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? 'walkin-'.now()->format('YmdHis').'-'.str()->random(6).'@laundrey.local',
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
        ]);

        return redirect()->route('customers.index')->with('sukses', 'Customer recorded.');
    }

    public function show(int $customer): View
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        $orders = Order::with('service')->where('user_id', $customer->id)->orderBy('created_at', 'desc')->paginate(10);
        $spent = (float) Order::where('user_id', $customer->id)->where('payment_status', 'paid')->sum('total_price');
        $unpaid = (float) Order::where('user_id', $customer->id)->where('payment_status', 'unpaid')->sum('total_price');

        return view('customers.show', compact('customer', 'orders', 'spent', 'unpaid'));
    }

    public function edit(int $customer): View
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, int $customer): RedirectResponse
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,'.$customer->id],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email,'.$customer->id],
        ]);

        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('sukses', 'Customer updated.');
    }

    public function destroy(int $customer): RedirectResponse
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        if ($customer->orders()->exists()) {
            return back()->withErrors(['customer' => 'Customer has order history and cannot be deleted']);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('sukses', 'Customer deleted.');
    }
}
