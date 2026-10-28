{{--
    The e-mails about something that needs the user: messages waiting for
    the hosts' approval, an invite request for the admin. $items: what it
    is about, each with a heading (who) and a text (what).
--}}
@extends('mail.layout')

@section('card')
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
                                    <td style="font-size:16px; line-height:26px; color:#57534e; padding-bottom:24px;">
                                        {{ $intro }}
                                    </td>
                                </tr>
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding-bottom:12px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="background-color:#fafaf9; border-left:3px solid #f59e0b; border-radius:8px; padding:14px 16px;">
                                                        <div style="font-size:13px; line-height:18px; font-weight:600; color:#1c1917; padding-bottom:4px;">{{ $item['heading'] }}</div>
                                                        <div style="font-size:15px; line-height:22px; color:#57534e; white-space:pre-line;">{{ $item['text'] }}</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($more > 0)
                                    <tr>
                                        <td style="font-size:14px; line-height:20px; color:#78716c; padding-bottom:12px;">
                                            E mais {{ $more }}.
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td align="left" style="padding-top:12px; padding-bottom:28px;">
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
                                    <td style="border-top:1px solid #f5f5f4; padding-top:20px; font-size:12px; line-height:18px; color:#a8a29e;">
                                        O botão não funcionou? Copie e cole este endereço no navegador:<br>
                                        <a href="{{ $link }}" style="color:#b45309; word-break:break-all;">{{ $link }}</a>
                                    </td>
                                </tr>
@endsection
