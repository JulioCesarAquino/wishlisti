{{--
    The frame of the Wishlisti e-mails: the brand, a card (the 'card'
    section) and the footer. Plain tables and inline styles: what Gmail,
    Outlook and phones all render the same.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased;">
    {{-- What the inbox shows next to the subject. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f4;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    {{-- Brand --}}
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <a href="{{ $homeUrl }}" style="text-decoration:none; color:#1c1917;">
                                <img src="{{ $logoUrl }}" width="44" height="44" alt="" style="display:block; margin:0 auto 8px; border:0;">
                                <span style="font-size:18px; font-weight:700; letter-spacing:-0.3px; color:#1c1917;">Wishlisti</span>
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff; border-radius:16px; border:1px solid #e7e5e4; padding:40px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@yield('card')
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:24px 16px 0; font-size:12px; line-height:18px; color:#a8a29e;">
                            {{ $reason }}
                            <a href="{{ $homeUrl }}" style="color:#78716c; text-decoration:underline;">{{ $homeHost }}</a>.<br>
                            Wishlisti &middot; listas de presentes e páginas para o seu evento
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
