<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $order->number }} — {{ config('shop.company.brand') }}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a1a1a; margin: 0; background: #f5f5f5; }
  .toolbar { max-width: 760px; margin: 16px auto 0; text-align: right; padding: 0 12px; }
  .btn { display: inline-block; background: #398aff; color: #fff; text-decoration: none; padding: 9px 18px; border-radius: 999px; font-weight: 600; font-size: 14px; border: 0; cursor: pointer; }
  .sheet { max-width: 760px; margin: 12px auto 40px; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 18px; }
  .brand { font-size: 22px; font-weight: 800; }
  .muted { color: #6b6b6b; font-size: 13px; line-height: 1.5; }
  h1 { font-size: 26px; letter-spacing: .05em; margin: 0 0 4px; }
  .addrs { display: flex; flex-wrap: wrap; gap: 40px; margin: 24px 0; }
  .addrs h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #8a8577; margin: 0 0 6px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { text-align: left; padding: 10px 8px; font-size: 14px; border-bottom: 1px solid #eee; }
  th { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #8a8577; }
  td.r, th.r { text-align: right; }
  .totals { width: 300px; margin-left: auto; margin-top: 10px; }
  .totals td { border: 0; padding: 5px 8px; }
  .totals .grand td { border-top: 2px solid #111; font-weight: 800; font-size: 16px; padding-top: 10px; }
  .note { color: #8a8577; font-size: 12px; margin-top: 22px; }
  @media print { body { background: #fff; } .toolbar { display: none; } .sheet { box-shadow: none; margin: 0; max-width: none; border-radius: 0; } }
</style>
</head>
<body>
@php
  $ship = $order->address ?? [];
  $bill = $order->billing ?: $ship;
  $fmt = fn ($a) => collect([
      $a['company'] ?? null,
      $a['name'] ?? null,
      $a['line'] ?? null,
      trim(trim(($a['city'] ?? '').', '.($a['state'] ?? '')), ', ').' '.($a['postal'] ?? ''),
      $a['country'] ?? null,
  ])->map(fn ($l) => trim((string) $l, ' ,'))->filter()->all();
@endphp
<div class="toolbar"><button class="btn" onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet">
  <div class="top">
    <div>
      <div class="brand">{{ config('shop.company.brand') }}</div>
      <div class="muted">{{ config('shop.company.name') }}<br>{{ config('shop.company.address') }}<br>{{ config('shop.company.email') }}</div>
    </div>
    <div style="text-align:right;">
      <h1>INVOICE</h1>
      <div class="muted"><strong>{{ $order->number }}</strong><br>{{ $order->created_at?->format('M j, Y') }}<br>Status: {{ ucfirst($order->status) }}</div>
    </div>
  </div>

  <div class="addrs">
    <div>
      <h3>Bill to</h3>
      <div class="muted">@foreach ($fmt($bill) as $l){{ $l }}<br>@endforeach{{ $order->email }}</div>
    </div>
    <div>
      <h3>Ship to</h3>
      <div class="muted">@foreach ($fmt($ship) as $l){{ $l }}<br>@endforeach</div>
    </div>
  </div>

  <table>
    <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead>
    <tbody>
    @foreach (($order->items ?? []) as $it)
      <tr><td>{{ $it['name'] ?? 'Item' }}</td><td class="r">{{ $it['quantity'] ?? 1 }}</td><td class="r">${{ number_format((float) ($it['line_total'] ?? 0), 2) }}</td></tr>
    @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal</td><td class="r">${{ number_format((float) $order->subtotal, 2) }}</td></tr>
    @if ((float) $order->discount > 0)
      <tr><td>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td><td class="r">−${{ number_format((float) $order->discount, 2) }}</td></tr>
    @endif
    <tr><td>Shipping{{ $order->shipping_method ? ' ('.$order->shipping_method.')' : '' }}</td><td class="r">{{ (float) $order->shipping > 0 ? '$'.number_format((float) $order->shipping, 2) : 'FREE' }}</td></tr>
    <tr><td>Estimated tax</td><td class="r">${{ number_format((float) $order->tax, 2) }}</td></tr>
    <tr class="grand"><td>Total</td><td class="r">${{ number_format((float) $order->total, 2) }}</td></tr>
  </table>

  <p class="note">Estimated sales tax is based on the shipping state (state base rate). Thank you for your business!</p>
</div>
</body>
</html>
