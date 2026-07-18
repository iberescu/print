@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">{{ $reminder ? "Your team's Brand Store is waiting" : "Your private Brand Store is live" }}</h1>
    <p style="margin:0 0 18px;color:#5c5749;">
        {{ $reminder
            ? "A quick reminder: we built a private online store for {$company} — every product already carries your logo and brand. Share it with your team; anyone with an @{$domain} email can sign in and order."
            : "We built {$company} a private online store: your logo and brand colours on mugs, apparel, stationery and more — ready to order any time, for you and your whole team." }}
    </p>

    @if (count($products))
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
            <tr>
                @foreach ($products as $p)
                    <td style="padding-right:8px;">
                        <img src="{{ $p['img'] }}" alt="{{ $p['label'] }}" width="120" height="120" style="width:120px;height:120px;border-radius:10px;border:1px solid #e6e1d4;object-fit:cover;">
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <p style="margin:0 0 18px;">
        <a href="{{ $link }}" style="display:inline-block;background:#1f2c4d;color:#ffffff;font-weight:600;font-size:15px;padding:12px 28px;border-radius:999px;text-decoration:none;">Open your Brand Store →</a>
    </p>
    <p style="margin:0;color:#8a8577;font-size:13px;">
        This link signs you in as the owner. Team members order with their work email — anyone
        @@{{ $domain }} gets a sign-in link by mail. Nobody else can access the store.
    </p>
@endsection
