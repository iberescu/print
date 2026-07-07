@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">You're in — welcome to the partner program</h1>
    <p style="margin:0 0 18px;color:#5c5749;">Hi {{ $affiliate->name }}, your application for <strong>{{ $affiliate->website ?: $affiliate->company ?: 'your site' }}</strong> is approved at <strong>${{ number_format($affiliate->cpm_cents / 100, 2) }} CPM</strong>. Here's everything you need to go live.</p>

    <p style="margin:0 0 6px;font-weight:600;font-size:14px;">Your embed snippet</p>
    <pre style="margin:0 0 18px;background:#0d1523;color:#9cc6ff;border-radius:10px;padding:14px;font-size:12px;overflow-x:auto;">&lt;script async src="https://www.runmyprint.com/affiliate-widget.js"&gt;&lt;/script&gt;
&lt;div data-rmp-affiliate="{{ $affiliate->key }}"
     data-logo-url="VISITOR_LOGO_URL"&gt;&lt;/div&gt;</pre>

    <p style="margin:0 0 8px;color:#5c5749;font-size:14px;">Pass each visitor's brand image as <code>data-logo-url</code> (a public URL) or their site as <code>data-website</code>. The widget renders their personalized ad and you earn per viewable impression.</p>
    <p style="margin:16px 0 0;"><a href="{{ url('/partner') }}" style="display:inline-block;background:#398aff;color:#ffffff;font-weight:600;padding:11px 22px;border-radius:999px;text-decoration:none;">Open your partner dashboard →</a></p>
    <p style="margin:12px 0 0;color:#8a8577;font-size:12px;">Sign in to the dashboard with this widget key — keep it private, it identifies your earnings.</p>
@endsection
