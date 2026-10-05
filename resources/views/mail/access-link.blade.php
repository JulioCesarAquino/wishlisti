{{--
    The e-mails with a link to set a password: a new host's invite, and a
    forgotten password. Plain tables and inline styles: what Gmail, Outlook
    and phones all render the same.
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
                                <tr>
                                    <td style="padding-bottom:20px;">
                                        <span style="display:inline-block; background-color:#fef3c7; color:#92400e; font-size:12px; font-weight:600; letter-spacing:0.3px; padding:6px 12px; border-radius:999px;">{{ $badge }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:24px; line-height:32px; font-weight:700; color:#1c1917; letter-spacing:-0.4px; padding-bottom:12px;">
                                        Olá, {{ $name }}!
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:16px; line-height:26px; color:#57534e; padding-bottom:32px;">
                                        {{ $intro }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-bottom:16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="border-radius:10px; background-color:#1c1917;">
                                                    <a href="{{ $link }}" target="_blank" style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">{{ $buttonLabel }} &rarr;</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; line-height:20px; color:#a8a29e; padding-bottom:28px;">
                                        {{ $note }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border-top:1px solid #f5f5f4; padding-top:20px; font-size:12px; line-height:18px; color:#a8a29e;">
                                        O botão não funcionou? Copie e cole este endereço no navegador:<br>
                                        <a href="{{ $link }}" style="color:#b45309; word-break:break-all;">{{ $link }}</a>
                                    </td>
                                </tr>
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
