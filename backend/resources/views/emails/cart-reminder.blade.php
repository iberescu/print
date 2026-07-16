@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">Your designs are waiting</h1>
    <p style="margin:0 0 18px;color:#5c5749;">You left these in your cart — they're saved and ready whenever you are.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6e1d4;border-radius:10px;">
        @foreach ($items as $item)
            @php($thumb = $item['design']['preview'] ?? $item['image'] ?? null)
            @php($thumb = $thumb && str_starts_with($thumb, '/') ? url($thumb) : $thumb) {{-- mail clients need absolute URLs --}}
            <tr>
                <td style="padding:10px 14px;border-bottom:1px solid #f0ece0;font-size:14px;">
                    @if ($thumb)<img src="{{ $thumb }}" alt="" width="56" height="56" style="width:56px;height:56px;border-radius:8px;object-fit:cover;vertical-align:middle;margin-right:12px;border:1px solid #f0ece0;">@endif{{ $item['name'] ?? 'Item' }} <span style="color:#8a8577;">× {{ $item['quantity'] ?? 1 }}</span>
                </td>
                <td align="right" style="padding:10px 14px;border-bottom:1px solid #f0ece0;font-size:14px;font-weight:600;">${{ number_format((float) ($item['line_total'] ?? 0), 2) }}</td>
            </tr>
        @endforeach
        <tr><td style="padding:12px 14px;font-size:15px;font-weight:700;">Subtotal</td><td align="right" style="padding:12px 14px;font-size:15px;font-weight:700;">${{ number_format((float) $subtotal, 2) }}</td></tr>
    </table>

    <p style="margin:18px 0 0;"><a href="{{ url('/cart') }}" style="display:inline-block;background:#398aff;color:#ffffff;font-weight:600;padding:11px 22px;border-radius:999px;text-decoration:none;">Finish your order →</a></p>
    <p style="margin:12px 0 0;color:#8a8577;font-size:12px;">Free shipping on orders over ${{ number_format((float) config('shop.free_shipping_threshold', 50), 0) }}.</p>
@endsection
