@php
    /**
     * The printed checkpoint list. dompdf understands CSS 2.1, so the layout
     * is tables only, and there are no emoji (DejaVu has no glyphs for them).
     */
    $ready = $travellers->filter(fn ($m) => $m->travellerDetail !== null)->values();
    $missing = $travellers->filter(fn ($m) => $m->travellerDetail === null)->values();
    $genders = \App\Models\TravellerDetail::GENDERS;
    $dates = $trip->starts_on
        ? $trip->starts_on->format('j M Y').($trip->ends_on && ! $trip->ends_on->isSameDay($trip->starts_on) ? ' - '.$trip->ends_on->format('j M Y') : '')
        : null;
    $vehicle = collect([$checkpoint['vehicle'] ?? null, $checkpoint['vehicle_number'] ?? null])->filter()->implode(', ');
    $driver = collect([$checkpoint['driver_name'] ?? null, $checkpoint['driver_phone'] ?? null])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $trip->title }} - traveller list</title>
    <style>
        @page { margin: 26px 28px 34px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.4pt; line-height: 1.4; color: #17211f; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .muted { color: #5b6660; }
        .faint { color: #8d968c; }
        .strong { font-weight: bold; }
        .eyebrow { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.4pt; letter-spacing: 1.2px; text-transform: uppercase; color: #8d968c; }
        h1 { font-size: 16pt; margin: 2px 0 0 0; }
        .rule { height: 3px; background-color: #0f6f6a; font-size: 1px; line-height: 1px; margin-bottom: 10px; }
        table.head { width: 100%; border-collapse: collapse; }
        table.head td { vertical-align: top; }
        table.facts { border-collapse: collapse; margin-top: 8px; }
        table.facts td { padding: 2px 14px 2px 0; vertical-align: top; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.list th {
            background-color: #f2f0ea; font-family: 'DejaVu Sans Mono', monospace; font-size: 6.2pt; letter-spacing: .8px;
            text-transform: uppercase; color: #5b6660; text-align: left; padding: 5px 6px; border: 1px solid #d9cdb6;
        }
        table.list td { padding: 6px; border: 1px solid #e3dccb; vertical-align: top; }
        table.list tr.alt td { background-color: #faf8f3; }
        .sign { margin-top: 18px; width: 100%; border-collapse: collapse; }
        .sign td { width: 33%; padding-top: 26px; vertical-align: bottom; }
        .line { border-top: 1px solid #8d968c; padding-top: 3px; font-size: 7pt; color: #5b6660; }
        .footer { margin-top: 10px; font-size: 6.8pt; color: #8d968c; }
    </style>
</head>
<body>
    <div class="rule"></div>

    <table class="head">
        <tr>
            <td>
                <div class="eyebrow">Traveller list</div>
                <h1>{{ $trip->title }}</h1>
                <table class="facts">
                    <tr>
                        @if ($trip->destination)
                            <td><span class="faint">Destination</span><br><span class="strong">{{ $trip->destination }}</span></td>
                        @endif
                        @if ($dates)
                            <td><span class="faint">Dates</span><br><span class="strong">{{ $dates }}</span></td>
                        @endif
                        <td><span class="faint">Group leader</span><br><span class="strong">{{ $leader?->name }}</span>@if ($leader?->phone) <span class="mono">{{ $leader->phone }}</span>@endif</td>
                        <td><span class="faint">Travellers</span><br><span class="strong">{{ $travellers->count() }}</span></td>
                    </tr>
                    @if ($vehicle || $driver || ($checkpoint['route'] ?? null) || ($checkpoint['stay'] ?? null))
                        <tr>
                            @if ($vehicle)
                                <td><span class="faint">Vehicle</span><br><span class="strong">{{ $vehicle }}</span></td>
                            @endif
                            @if ($driver)
                                <td><span class="faint">Driver</span><br><span class="strong">{{ $driver }}</span></td>
                            @endif
                            @if ($checkpoint['route'] ?? null)
                                <td><span class="faint">Route</span><br><span class="strong">{{ $checkpoint['route'] }}</span></td>
                            @endif
                            @if ($checkpoint['stay'] ?? null)
                                <td><span class="faint">Staying at</span><br><span class="strong">{{ $checkpoint['stay'] }}</span></td>
                            @endif
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width: 18px;">#</th>
                <th>Name</th>
                <th style="width: 58px;">Age / sex</th>
                <th>ID</th>
                <th style="width: 80px;">Mobile</th>
                <th>Address</th>
                <th style="width: 34px;">Blood</th>
                <th>Emergency contact</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ready as $index => $member)
                @php $d = $member->travellerDetail; @endphp
                <tr class="{{ $index % 2 ? 'alt' : '' }}">
                    <td class="muted">{{ $index + 1 }}</td>
                    <td class="strong">{{ $d->value('full_name') }}</td>
                    <td>
                        {{ $d->age() }} yrs
                        @if ($d->value('gender'))<br><span class="muted">{{ $genders[$d->value('gender')] ?? '' }}</span>@endif
                        <br><span class="faint">{{ \Illuminate\Support\Carbon::parse($d->value('date_of_birth'))->format('d/m/Y') }}</span>
                    </td>
                    <td><span class="faint">{{ $d->idLabel() }}</span><br><span class="mono strong">{{ $d->value('id_number') }}</span></td>
                    <td class="mono">{{ $d->value('phone') }}</td>
                    <td>{{ $d->value('address') }}</td>
                    <td class="strong">{{ $d->value('blood_group') }}</td>
                    <td>
                        {{ $d->value('emergency_name') }}
                        @if ($d->value('emergency_phone'))<br><span class="mono">{{ $d->value('emergency_phone') }}</span>@endif
                    </td>
                </tr>
            @endforeach
            @foreach ($missing as $offset => $member)
                <tr class="{{ ($ready->count() + $offset) % 2 ? 'alt' : '' }}">
                    <td class="muted">{{ $ready->count() + $offset + 1 }}</td>
                    <td class="strong">{{ $member->name }}</td>
                    <td colspan="6" class="faint">Details not added yet - write them in by hand.</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($checkpoint['notes'] ?? null)
        <p class="muted" style="margin-top: 10px;"><span class="strong">Notes:</span> {{ $checkpoint['notes'] }}</p>
    @endif

    <table class="sign">
        <tr>
            <td><div class="line">Group leader's signature</div></td>
            <td></td>
            <td><div class="line">Checked by (name, post, date)</div></td>
        </tr>
    </table>

    <p class="footer">Prepared with Sofor on {{ now()->format('j M Y, g:i a') }}. Personal details on this list are deleted from Sofor shortly after the trip.</p>
</body>
</html>
