@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">Sign in to the {{ $company }} Brand Store</h1>
    <p style="margin:0 0 18px;color:#5c5749;">Click the button below to open your company's private store — products already personalised with the {{ $company }} brand.</p>
    <p style="margin:0 0 18px;">
        <a href="{{ $link }}" style="display:inline-block;background:#1f2c4d;color:#ffffff;font-weight:600;font-size:15px;padding:12px 28px;border-radius:999px;text-decoration:none;">Open the Brand Store →</a>
    </p>
    <p style="margin:0;color:#8a8577;font-size:13px;">The link works once and expires in 30 minutes. If you didn't request it, you can safely ignore this email.</p>
@endsection
