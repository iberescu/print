@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">Thanks — order confirmed!</h1>
    <p style="margin:0 0 18px;color:#5c5749;">Hi {{ $order->name }}, we've received your order <strong>{{ $order->number }}</strong> and it's heading to print.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6e1d4;border-radius:10px;">
        @foreach (($order->items ?? []) as $item)
            @php($thumb = $item['design']['preview'] ?? $item['image'] ?? null)
            @php($thumb = $thumb && str_starts_with($thumb, '/') ? url($thumb) : $thumb) {{-- mail clients need absolute URLs --}}
            <tr>
                <td style="padding:10px 14px;border-bottom:1px solid #f0ece0;font-size:14px;">
                    @if ($thumb)<img src="{{ $thumb }}" alt="" width="56" height="56" style="width:56px;height:56px;border-radius:8px;object-fit:cover;vertical-align:middle;margin-right:12px;border:1px solid #f0ece0;">@endif{{ $item['name'] ?? 'Item' }} <span style="color:#8a8577;">× {{ $item['quantity'] ?? 1 }}</span>
                </td>
                <td align="right" style="padding:10px 14px;border-bottom:1px solid #f0ece0;font-size:14px;font-weight:600;">
                    ${{ number_format((float) ($item['line_total'] ?? 0), 2) }}
                </td>
            </tr>
        @endforeach
        <tr><td style="padding:10px 14px;font-size:14px;color:#5c5749;">Subtotal</td><td align="right" style="padding:10px 14px;font-size:14px;">${{ number_format((float) $order->subtotal, 2) }}</td></tr>
        @if ((float) $order->discount > 0)
            <tr><td style="padding:0 14px 10px;font-size:14px;color:#2f6b4f;">Discount ({{ $order->coupon_code }})</td><td align="right" style="padding:0 14px 10px;font-size:14px;color:#2f6b4f;">−${{ number_format((float) $order->discount, 2) }}</td></tr>
        @endif
        <tr><td style="padding:0 14px 10px;font-size:14px;color:#5c5749;">Shipping{{ $order->shipping_method ? ' ('.$order->shipping_method.')' : '' }}</td><td align="right" style="padding:0 14px 10px;font-size:14px;">{{ (float) $order->shipping > 0 ? '$'.number_format((float) $order->shipping, 2) : 'Free' }}</td></tr>
        <tr><td style="padding:0 14px 10px;font-size:14px;color:#5c5749;">Estimated tax</td><td align="right" style="padding:0 14px 10px;font-size:14px;">${{ number_format((float) $order->tax, 2) }}</td></tr>
        <tr><td style="padding:12px 14px;border-top:1px solid #e6e1d4;font-size:15px;font-weight:700;">Total</td><td align="right" style="padding:12px 14px;border-top:1px solid #e6e1d4;font-size:15px;font-weight:700;">${{ number_format((float) $order->total, 2) }}</td></tr>
    </table>

    <p style="margin:18px 0 0;color:#5c5749;font-size:14px;">Shipping to: {{ $order->address['line'] ?? '' }}, {{ $order->address['city'] ?? '' }} {{ $order->address['state'] ?? '' }} {{ $order->address['postal'] ?? '' }}, {{ $order->address['country'] ?? '' }}</p>
    <p style="margin:14px 0 0;"><a href="{{ route('account.invoice', $order->number) }}" style="color:#398aff;">View / print invoice →</a> &nbsp;·&nbsp; <a href="{{ url('/account') }}" style="color:#398aff;">Track your order →</a></p>
@endsection
