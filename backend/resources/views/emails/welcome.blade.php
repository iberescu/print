@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">Welcome to RunMyPrint 👋</h1>
    <p style="margin:0 0 18px;color:#5c5749;">Thanks for joining — here's <strong>{{ $percent }}% off your first order</strong> to get you started.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:2px dashed #398aff;border-radius:12px;margin:0 0 22px;">
        <tr><td align="center" style="padding:22px 14px;">
            <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#8a8577;">Your code</p>
            <p style="margin:0;font-size:30px;font-weight:800;letter-spacing:.05em;color:#1a1a1a;">{{ $code }}</p>
            <p style="margin:8px 0 0;font-size:13px;color:#8a8577;">{{ $percent }}% off your first order · applied at checkout</p>
        </td></tr>
    </table>

    <p style="margin:0 0 20px;text-align:center;">
        <a href="{{ url('/category/business-cards') }}" style="display:inline-block;background:#398aff;color:#fff;text-decoration:none;padding:13px 30px;border-radius:999px;font-weight:600;">Start with business cards — from $6.85 →</a>
    </p>

    @if (! empty($products))
        <h2 style="margin:26px 0 4px;font-size:18px;">Your logo, already on our products</h2>
        <p style="margin:0 0 14px;color:#5c5749;font-size:14px;">We put your brand on these — add any to an order and use your code above.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;"><tr>
            @foreach ($products as $p)
                @php($href = ! empty($p['slug']) ? url('/product/'.$p['slug']) : url('/'))
                <td width="25%" valign="top" style="padding:0 5px;text-align:center;">
                    <a href="{{ $href }}" style="text-decoration:none;color:#1a1a1a;">
                        @if (! empty($p['img']))<img src="{{ $p['img'] }}" alt="{{ $p['name'] ?? $p['label'] ?? 'Your logo' }}" width="120" style="width:100%;max-width:130px;border-radius:8px;border:1px solid #f0ece0;display:block;margin:0 auto 6px;">@endif
                        <span style="display:block;font-size:12px;line-height:1.3;color:#1a1a1a;">{{ \Illuminate\Support\Str::limit($p['name'] ?? $p['label'] ?? '', 24) }}</span>
                        @if (! empty($p['fromPrice']))<span style="display:block;font-size:11px;color:#8a8577;">From ${{ number_format((float) $p['fromPrice'], 2) }}</span>@endif
                    </a>
                </td>
            @endforeach
        </tr></table>
    @endif

    <p style="margin:0 0 6px;color:#5c5749;font-size:14px;">While you're here, a few things you can do for free:</p>
    <ul style="margin:0 0 18px;padding-left:20px;color:#5c5749;font-size:14px;line-height:1.7;">
        <li><a href="{{ url('/logo-maker') }}" style="color:#398aff;">Design a logo</a> with our AI logo maker</li>
        <li><a href="{{ url('/qr-code-generator') }}" style="color:#398aff;">Make a QR code</a> with your logo in the middle</li>
        <li>Upload your brand and see it on cards, apparel and more</li>
    </ul>

    <p style="margin:0;color:#8a8577;font-size:12px;">You're receiving this because you signed up at runmyprint.com. Not you? Just ignore this email.</p>
@endsection
