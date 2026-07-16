<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1e9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1c2434;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1e9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                <tr>
                    <td style="background:#ffffff;border-radius:14px 14px 0 0;border:1px solid #e6e1d4;border-bottom:3px solid #1c2b45;padding:16px 28px;">
                        <a href="{{ url('/') }}" style="text-decoration:none;">
                            <img src="{{ url('/storage/brand/logo.png') }}" alt="RunMyPrint" width="126" height="47" style="display:block;width:126px;height:47px;border:0;">
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;padding:28px;border:1px solid #e6e1d4;border-top:0;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border-radius:0 0 14px 14px;border:1px solid #e6e1d4;border-top:0;padding:16px 28px;color:#8a8577;font-size:12px;line-height:1.6;">
                        {{ config('shop.company.brand') }} · {{ config('shop.company.address') }}<br>
                        Questions? Just reply to this email or write to {{ config('shop.company.email') }}.
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
