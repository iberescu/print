@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 6px;font-size:22px;">We replied to your support message</h1>
    <p style="margin:0 0 18px;color:#5c5749;">Our team answered your question:</p>
    <div style="border-left:3px solid #398aff;background:#f7f9ff;padding:12px 16px;border-radius:0 10px 10px 0;font-size:14px;line-height:1.6;">{{ $reply }}</div>
    <p style="margin:18px 0 0;color:#5c5749;font-size:14px;">You can continue the conversation from the chat bubble on <a href="{{ url('/') }}" style="color:#398aff;">runmyprint.com</a>.</p>
@endsection
