<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->invoice_number }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1917; padding: 24px; }
  .head { border-bottom: 2px solid #1c1917; padding-bottom: 12px; margin-bottom: 16px; }
  .head h1 { font-size: 22px; }
  .head p { color: #57534e; font-size: 11px; }
  .meta { width: 100%; margin-bottom: 16px; }
  .meta td { vertical-align: top; padding: 2px 0; }
  .mono { font-family: DejaVu Sans Mono, monospace; }
  table.items { width: 100%; border-collapse: collapse; margin: 12px 0; table-layout: fixed; }
  table.items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #57534e; border-bottom: 1px solid #1c1917; padding: 6px 8px; }
  table.items td { border-bottom: 1px solid #e6e3dc; padding: 8px; vertical-align: top; }
  table.items th:nth-child(1), table.items td:nth-child(1) { width: 40%; }
  table.items th:nth-child(2), table.items td:nth-child(2) { width: 20%; }
  table.items th:nth-child(3), table.items td:nth-child(3) { width: 20%; }
  table.items th:nth-child(4), table.items td:nth-child(4) { width: 20%; }
  .right { text-align: right; }
  .total { font-size: 16px; font-weight: bold; }
  .foot { margin-top: 16px; border-top: 1px dashed #999; padding-top: 10px; font-size: 10px; color: #57534e; }
  .actions { margin-bottom: 16px; }
  .actions a { display: inline-block; border: 1px solid #1c1917; padding: 8px 16px; font-size: 12px; text-decoration: none; color: #1c1917; margin-right: 8px; }
  @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="actions">
<a href="#" onclick="window.print(); return false;">Print</a>
</div>
<div class="head">
<h1>{{ $order->tenant->name }}.</h1>
<p>{{ $order->tenant->phone ?? '' }}{{ $order->tenant->address ? ' · '.$order->tenant->address : '' }}</p>
</div>
<table class="meta">
<tr>
<td><strong>INVOICE</strong><br><span class="mono">{{ $order->invoice_number }}</span><br>{{ $order->created_at->format('d M Y H:i') }}</td>
<td class="right">{{ $order->customer->name }}<br><span class="mono">{{ $order->customer->customerCode() }}</span><br>{{ $order->customer->phone ?? '' }}</td>
</tr>
</table>
<table class="items">
<thead><tr><th>Service</th><th>Weight</th><th class="right">Unit price</th><th class="right">Total</th></tr></thead>
<tbody>
<tr>
<td>{{ $order->service->service_name }}</td>
<td class="mono">{{ $order->weight_or_qty }} {{ $order->service->unit_type }}</td>
<td class="right mono">Rp{{ number_format($order->service->price_per_unit, 0, ',', '.') }}</td>
<td class="right mono">Rp{{ number_format($order->total_price, 0, ',', '.') }}@if($order->discount_percent > 0)<br><small>-{{ $order->discount_percent }}% {{ $order->promo->code ?? '' }}</small>@endif</td>
</tr>
</tbody>
</table>
<table class="meta">
<tr><td>Status: <strong>{{ $order->current_status }}</strong></td><td class="right total">Rp{{ number_format($order->total_price, 0, ',', '.') }} <small>({{ $order->payment_status }})</small></td></tr>
</table>
<div class="foot">Track this receipt online with the invoice number. Thank you.</div>
<table style="width:100%; margin-top:16px;">
<tr>
<td style="vertical-align:middle;">
{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(110)->generate(route('home', ['invoice' => $order->invoice_number])) !!}
</td>
<td style="vertical-align:middle; padding-left:12px; font-size:11px; color:#57534e;">
<strong>Scan to track</strong><br>
Point your camera here to open the tracking page with this receipt number filled in.
</td>
</tr>
</table>
</body>
</html>
