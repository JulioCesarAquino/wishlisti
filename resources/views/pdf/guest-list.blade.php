@php
    use App\Models\Guests\Guest;

    $number = 0;
    $isChild = fn (Guest $guest): bool => $childAgeLimit !== null && $guest->age !== null && $guest->age < $childAgeLimit;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Convidados · {{ $event->title }}</title>
    <style>
        @page { margin: 18mm 16mm 22mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #1f2328;
            line-height: 1.35;
        }

        .eyebrow {
            font-size: 7.5pt;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
            color: #6b7280;
        }

        h1 {
            font-size: 20pt;
            line-height: 1.15;
            margin: 2pt 0 6pt;
        }

        .details { color: #374151; }
        .details div { margin-bottom: 1.5pt; }
        .muted { color: #6b7280; }

        .summary {
            margin: 12pt 0 4pt;
            padding: 7pt 0;
            border-top: 1.2pt solid #1f2328;
            border-bottom: 0.5pt solid #d1d5db;
            width: 100%;
        }

        .summary td { vertical-align: middle; }
        .summary strong { font-size: 11pt; }

        .legend {
            padding-top: 3pt;
            text-align: right;
            font-size: 7.5pt;
            color: #6b7280;
        }

        .legend .box { margin: 0 2pt 0 8pt; }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6pt;
        }

        table.list th {
            font-size: 7pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: normal;
            text-align: left;
            padding: 4pt 4pt;
            border-bottom: 0.5pt solid #9ca3af;
        }

        table.list td {
            padding: 4.5pt 4pt;
            border-bottom: 0.5pt solid #e5e7eb;
            vertical-align: middle;
        }

        tr { page-break-inside: avoid; }

        .col-number { width: 18pt; color: #9ca3af; font-size: 7.5pt; text-align: right; }
        .col-box { width: 16pt; }
        .col-phone { width: 95pt; color: #374151; }

        .box {
            display: inline-block;
            width: 10pt;
            height: 10pt;
            border: 0.9pt solid #1f2328;
            border-radius: 1.5pt;
            text-align: center;
            font-size: 8pt;
            line-height: 10pt;
            vertical-align: middle;
        }

        .box-on { background: #1f2328; color: #ffffff; }
        .box-off { border-color: #9ca3af; color: #9ca3af; }

        .companion td.name { padding-left: 16pt; }
        .companion td.name:before { content: '↳ '; color: #9ca3af; }

        .tag {
            font-size: 6.5pt;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
            color: #92400e;
            background: #fef3c7;
            padding: 0.5pt 3pt;
            border-radius: 2pt;
            margin-left: 4pt;
        }

        .extra { color: #6b7280; font-size: 8pt; margin-left: 4pt; }

        .declined td { color: #6b7280; }

        h2 {
            font-size: 9pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #6b7280;
            margin: 18pt 0 0;
        }

        .empty { padding: 20pt 0; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
    <div class="eyebrow">Lista de convidados · {{ $event->typeLabel() }}</div>
    <h1>{{ $event->title }}</h1>

    <div class="details">
        @if ($date)
            <div>{{ $date }}</div>
        @endif
        @foreach ($locations as $location)
            <div>
                <strong>{{ $location['name'] }}</strong>@if ($location['time']) · {{ $location['time'] }}@endif
                @if ($location['address'])
                    <span class="muted">— {{ $location['address'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <table class="summary">
        <tr>
            <td>
                <strong>{{ $people }}</strong> {{ $people === 1 ? 'pessoa confirmada' : 'pessoas confirmadas' }}
                @if ($children !== null)
                    <span class="muted">· {{ $children }} {{ $children === 1 ? 'criança' : 'crianças' }} com menos de {{ $childAgeLimit }} anos</span>
                @endif
                @if ($pending > 0)
                    <span class="muted">· {{ $pending }} sem resposta</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="legend">
                <span class="box box-on">✓</span>confirmado
                <span class="box"></span>sem resposta
                @if ($declined->isNotEmpty())
                    <span class="box box-off">✕</span>não vai
                @endif
            </td>
        </tr>
    </table>

    @if ($coming->isEmpty() && $declined->isEmpty())
        <div class="empty">Ninguém na lista ainda.</div>
    @endif

    @foreach (['coming' => $coming, 'declined' => $declined] as $section => $groups)
        @continue($groups->isEmpty())

        @if ($section === 'declined')
            <h2>Não vão</h2>
        @endif

        <table class="list">
            <thead>
                <tr>
                    <th class="col-number">Nº</th>
                    <th class="col-box"></th>
                    <th>Nome</th>
                    @if ($showWhatsapp)
                        <th class="col-phone">WhatsApp</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group)
                    @foreach ([$group['guest'], ...$group['companions']] as $guest)
                        @php
                            $status = $group['guest']->rsvp_status;
                            $companion = $guest->companion_of_guest_id !== null;
                        @endphp
                        <tr class="{{ $companion ? 'companion' : '' }} {{ $section === 'declined' ? 'declined' : '' }}">
                            <td class="col-number">{{ ++$number }}</td>
                            <td class="col-box">
                                @if ($status === Guest::RSVP_CONFIRMED)
                                    <span class="box box-on">✓</span>
                                @elseif ($status === Guest::RSVP_DECLINED)
                                    <span class="box box-off">✕</span>
                                @else
                                    <span class="box"></span>
                                @endif
                            </td>
                            <td class="name">
                                {{ $guest->name }}
                                @if ($isChild($guest))
                                    <span class="tag">criança · {{ $guest->age }} {{ $guest->age === 1 ? 'ano' : 'anos' }}</span>
                                @endif
                                @if (! $companion && $group['unnamed'] > 0)
                                    <span class="extra">+ {{ $group['unnamed'] }} {{ $group['unnamed'] === 1 ? 'pessoa' : 'pessoas' }}</span>
                                @endif
                            </td>
                            @if ($showWhatsapp)
                                <td class="col-phone">{{ $guest->whatsapp }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="muted" style="font-size: 7pt; margin-top: 14pt;">Lista gerada em {{ $generatedAt }}.</p>
</body>
</html>
