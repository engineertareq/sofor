@php
    /**
     * Print-ready trip summary.
     *
     * dompdf only understands CSS 2.1 plus a little CSS3, so this file avoids
     * flexbox, grid and CSS custom properties and lays everything out with
     * tables. It also avoids emoji, because the bundled DejaVu fonts have no
     * emoji glyphs and would print empty boxes.
     */
    $stats = $ledger['stats'];
    $balances = $ledger['balances'];
    $members = $ledger['members'];
    $suggestions = $ledger['suggestions'];
    $categories = $ledger['categories'];
    $progress = $ledger['progress'];
    $me = $ledger['me'];

    $currency = $trip->currency;
    // DejaVu has no taka glyph, so the sign is set in its own small font (.tk).
    $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
        str_replace('৳', '<span class="tk">৳</span>', e(\App\Support\Money::format($value, $currency)))
    );
    $takaFont = fn (int $weight) => str_replace('\\', '/', resource_path('fonts/taka/taka-'.$weight.'.ttf'));
    $meId = $me['is_member'] ? ($me['member']->id ?? null) : null;

    $travellers = $members->filter(fn ($m) => $m->isTravelling())->values();
    $ledgerExpenses = $expenses->sortByDesc(fn ($e) => $e->spent_on?->timestamp ?? 0)->values();
    $contributions = $settlements->where('amount', '>', 0)->sortByDesc(fn ($s) => $s->settled_on?->timestamp ?? 0)->values();

    $isSettled = $suggestions === [];
    $transferCount = count($suggestions);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $trip->title }} - Sofor trip summary</title>
    <style>
        @page { margin: 28px 32px 52px 32px; }

        @font-face { font-family: 'Sofor Taka'; font-weight: normal; src: url('{{ $takaFont(400) }}') format('truetype'); }
        @font-face { font-family: 'Sofor Taka'; font-weight: bold; src: url('{{ $takaFont(700) }}') format('truetype'); }
        .tk { font-family: 'Sofor Taka'; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.6pt;
            line-height: 1.45;
            color: #17211f;
        }

        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .right { text-align: right; }
        .center { text-align: center; }
        .top { vertical-align: top; }
        .middle { vertical-align: middle; }
        .muted { color: #5b6660; }
        .faint { color: #8d968c; }
        .brand { color: #0f6f6a; }
        .pos { color: #0d7a4a; }
        .neg { color: #c0392b; }
        .strong { font-weight: bold; }
        .nowrap { white-space: nowrap; }

        .eyebrow {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 6.6pt;
            letter-spacing: 1.3px;
            text-transform: uppercase;
            color: #8d968c;
        }

        .rule-top { height: 4px; background-color: #0f6f6a; font-size: 1px; line-height: 1px; }
        .wordmark { font-size: 15pt; font-weight: bold; color: #0f6f6a; }
        .mark {
            display: inline-block;
            width: 22px; height: 22px;
            background-color: #0f6f6a;
            border-radius: 7px;
            color: #ffffff;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            line-height: 22px;
        }

        h1 { font-size: 19pt; margin: 0; line-height: 1.15; }
        h2 {
            font-size: 10pt;
            margin: 0 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 2px dashed #d9cdb6;
        }

        .meta { font-size: 8.2pt; color: #5b6660; }

        /* Stat strip ---------------------------------------------------- */
        table.stats { width: 100%; border-collapse: collapse; }
        table.stats td.slot { width: 25%; padding: 0 3px; vertical-align: top; }
        .card {
            border: 1px solid #ece4d5;
            border-radius: 9px;
            padding: 8px 9px;
            background-color: #f2f0ea;
        }
        .card .k { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.4pt; letter-spacing: 1px; text-transform: uppercase; color: #8d968c; }
        .card .v { font-size: 13pt; font-weight: bold; margin-top: 3px; }
        .tint-brand { background-color: #dcf0ed; border-color: #b8ddd8; }
        .tint-accent { background-color: #fdf1d7; border-color: #ecd9ab; }
        .tint-pos { background-color: #d9f2e4; border-color: #b6e0ca; }
        .tint-neg { background-color: #fbe2df; border-color: #eec4bf; }

        /* Tables -------------------------------------------------------- */
        table.tbl { width: 100%; border-collapse: collapse; }
        table.tbl th {
            background-color: #f2f0ea;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 6.4pt;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #5b6660;
            text-align: left;
            padding: 5px 7px;
            border-bottom: 1px solid #d9cdb6;
        }
        table.tbl td {
            padding: 5px 7px;
            border-bottom: 1px solid #ece4d5;
            vertical-align: top;
        }
        table.tbl tr.me td { background-color: #f4fbfa; }
        table.tbl tr.total td {
            background-color: #f2f0ea;
            font-weight: bold;
            border-top: 1.5px solid #d9cdb6;
            border-bottom: none;
        }
        .tag {
            display: inline-block;
            padding: 1px 5px;
            border: 1px solid #ece4d5;
            border-radius: 5px;
            background-color: #ffffff;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 6.4pt;
            color: #5b6660;
        }

        /* Category bars ------------------------------------------------- */
        table.bar { width: 100%; border-collapse: collapse; }
        table.bar td { height: 5px; font-size: 1px; line-height: 1px; padding: 0; }
        .bar-fill { background-color: #0f6f6a; border-radius: 3px; }
        .bar-rest { background-color: #ece4d5; border-radius: 3px; }

        .section { margin-top: 15px; }
        .note {
            border: 1px dashed #d9cdb6;
            border-radius: 8px;
            padding: 8px 10px;
            background-color: #fbf8f2;
            font-size: 8pt;
            color: #5b6660;
        }
        .stamp {
            display: inline-block;
            padding: 4px 8px;
            border: 2px dashed #0d7a4a;
            border-radius: 6px;
            color: #0d7a4a;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 7pt;
            letter-spacing: 1.2px;
            font-weight: bold;
        }
        .stamp-live { border-color: #0f6f6a; color: #0f6f6a; }
        .stamp-warn { border-color: #a4650a; color: #a4650a; }
    </style>
</head>
<body>

{{-- ============================ Masthead ============================ --}}
<div class="rule-top"></div>

<table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
    <tr>
        <td class="top">
            @php $uploadedLogo = app(\App\Services\BrandingService::class)->logoDataUri(); @endphp
            @if ($uploadedLogo)
                {{-- Inlined: dompdf cannot fetch the site's own routes. --}}
                <img src="{{ $uploadedLogo }}" alt="Sofor" style="height: 26px;">
            @else
                <span class="mark">&#9679;</span>
                <span class="wordmark" style="margin-left: 4px;">Sofor</span>
            @endif
            <div class="eyebrow" style="margin-top: 4px;">Trip summary</div>
        </td>
        <td class="top right">
            @if ($trip->isEnded())
                <span class="stamp stamp-warn">ENDED</span>
            @else
                <span class="stamp stamp-live">LIVE LEDGER</span>
            @endif
            <div class="eyebrow" style="margin-top: 5px;">
                Generated {{ now()->format('d M Y') }}
            </div>
        </td>
    </tr>
</table>

<h1 style="margin-top: 10px;">{{ $trip->title }}</h1>

<p class="meta" style="margin: 5px 0 0 0;">
    {{ $trip->destination ?: 'Destination to be decided' }}
    @if ($trip->starts_on)
        &nbsp;&middot;&nbsp;
        {{ $trip->starts_on->format('d M Y') }}@if ($trip->ends_on) to {{ $trip->ends_on->format('d M Y') }}@endif
    @endif
    &nbsp;&middot;&nbsp; Led by {{ $trip->owner?->name }}
    @if ($trip->isEnded() && $trip->ended_at)
        &nbsp;&middot;&nbsp; Closed {{ $trip->ended_at->format('d M Y') }}
    @endif
</p>

{{-- ========================= Stat strip ============================= --}}
<div class="section">
    <table class="stats">
        <tr>
            <td class="slot">
                <div class="card tint-brand">
                    <div class="k">Total logged</div>
                    <div class="v brand">{{ $fmt($stats['spent']) }}</div>
                    <div class="faint" style="font-size: 7.2pt;">{{ $stats['expense_count'] }} costs</div>
                </div>
            </td>
            <td class="slot">
                <div class="card">
                    <div class="k">Each share</div>
                    <div class="v">{{ $fmt($stats['per_person']) }}</div>
                    <div class="faint" style="font-size: 7.2pt;">{{ $stats['participants'] }} sharing</div>
                </div>
            </td>
            <td class="slot">
                <div class="card tint-accent">
                    <div class="k">Travellers</div>
                    <div class="v">{{ $stats['travellers'] }}</div>
                    <div class="faint" style="font-size: 7.2pt;">{{ $progress['settled_members'] }} of {{ $progress['total_members'] }} fully settled</div>
                </div>
            </td>
            <td class="slot">
                <div class="card {{ $stats['outstanding'] > 0.009 ? 'tint-neg' : 'tint-pos' }}">
                    <div class="k">Still to settle</div>
                    <div class="v {{ $stats['outstanding'] > 0.009 ? 'neg' : 'pos' }}">{{ $fmt($stats['outstanding']) }}</div>
                    <div class="faint" style="font-size: 7.2pt;">
                        {{ $transferCount > 0 ? $transferCount.' '.(\Illuminate\Support\Str::plural('transfer', $transferCount)).' needed' : 'All square' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>

@if ($stats['budget'])
    <p class="note" style="margin-top: 12px;">
        <span class="strong">{{ $fmt($stats['spent']) }}</span> of the
        <span class="strong">{{ $fmt($stats['budget']) }}</span> planned budget
        @if ($ledger['budget']['mode'] === 'person')
            ({{ $fmt($ledger['budget']['per_person']) }} a person)
        @endif
        used
        ({{ \App\Support\Money::percent($stats['budget_used']) }}).
        That is {{ $fmt($stats['avg_expense']) }} per cost on average.
    </p>
@endif

{{-- ========================= Settle up ============================== --}}
<div class="section">
    <h2>Settle up</h2>

    @if ($isSettled)
        <div class="note">
            <span class="stamp" style="margin-right: 6px;">ALL SQUARE</span>
            Every traveller is even. Nothing needs to move.
        </div>
    @else
        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 30%;">From</th>
                    <th style="width: 30%;">To</th>
                    <th style="width: 24%;">Contact</th>
                    <th class="right" style="width: 16%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suggestions as $suggestion)
                    <tr class="{{ $suggestion['from_id'] === $meId || $suggestion['to_id'] === $meId ? 'me' : '' }}">
                        <td>{{ $suggestion['from']->name }}{!! $suggestion['from_id'] === $meId ? ' <span class="tag">you</span>' : '' !!}</td>
                        <td>{{ $suggestion['to']->name }}{!! $suggestion['to_id'] === $meId ? ' <span class="tag">you</span>' : '' !!}</td>
                        <td class="faint" style="font-size: 7.6pt;">{{ $suggestion['from']->contactLabel() }}</td>
                        <td class="right mono strong">{{ $fmt($suggestion['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="faint" style="font-size: 7.4pt; margin-top: 5px;">
            {{ $transferCount === 1 ? 'This single transfer is' : 'These '.$transferCount.' transfers are' }}
            the shortest path back to zero. Sofor never touches the money.
        </p>
    @endif
</div>

{{-- ======================= Traveller balances ====================== --}}
<div class="section">
    <h2>Traveller balances</h2>

    <table class="tbl">
        <thead>
            <tr>
                <th style="width: 28%;">Traveller</th>
                <th class="right" style="width: 18%;">Covered</th>
                <th class="right" style="width: 16%;">Their share</th>
                <th class="right" style="width: 18%;">Contributed</th>
                <th class="right" style="width: 20%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($travellers as $member)
                @php
                    $row = $balances[$member->id] ?? null;
                    $balance = $row['balance'] ?? 0;
                @endphp
                @if ($row)
                    <tr class="{{ $member->id === $meId ? 'me' : '' }}">
                        <td>
                            {{ $member->name }}
                            @if ($member->id === $meId)
                                <span class="tag">you</span>
                            @endif
                            @unless ($member->include_in_split)
                                <span class="tag">not in split</span>
                            @endunless
                            @if ($member->status !== \App\Enums\MemberStatus::Active)
                                <span class="tag">{{ $member->status->label() }}</span>
                            @endif
                        </td>
                        <td class="right mono">{{ $fmt($row['paid']) }}</td>
                        <td class="right mono">{{ $fmt($row['share']) }}</td>
                        <td class="right mono">{{ $row['contributed'] > 0 ? $fmt($row['contributed']) : '–' }}</td>
                        <td class="right mono strong">
                            @if ($balance < -0.009)
                                <span class="neg">owes {{ $fmt(abs($balance)) }}</span>
                            @elseif ($balance > 0.009)
                                <span class="pos">gets back {{ $fmt($balance) }}</span>
                            @else
                                <span class="faint">settled</span>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td>Total</td>
                <td class="right mono">{{ $fmt($stats['paid_total']) }}</td>
                <td class="right mono">{{ $fmt($stats['share_total']) }}</td>
                <td class="right mono">{{ $fmt($stats['contribution_total']) }}</td>
                <td class="right mono">{{ $fmt($stats['outstanding']) }} outstanding</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- =========================== Cost ledger ========================== --}}
@if ($ledgerExpenses->isNotEmpty())
    <div class="section">
        <h2>Cost ledger</h2>

        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 11%;">Date</th>
                    <th style="width: 33%;">What it was for</th>
                    <th style="width: 15%;">Category</th>
                    <th style="width: 23%;">Paid by</th>
                    <th class="right" style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ledgerExpenses as $expense)
                    <tr>
                        <td class="mono nowrap" style="font-size: 7.6pt;">{{ $expense->spent_on?->format('d M y') }}</td>
                        <td>
                            {{ $expense->title }}
                            @if ($expense->notes)
                                <div class="faint" style="font-size: 7.4pt;">{{ $expense->notes }}</div>
                            @endif
                        </td>
                        <td><span class="tag">{{ $expense->categoryEnum()->label() }}</span></td>
                        <td style="font-size: 7.8pt;">{{ $expense->payer?->name ?? 'Unassigned' }}</td>
                        <td class="right mono strong">{{ $fmt($expense->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td colspan="4">{{ $stats['expense_count'] }} costs</td>
                    <td class="right mono">{{ $fmt($stats['spent']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif

{{-- ======================= Where the money went ===================== --}}
@if (! empty($categories))
    <div class="section">
        <h2>Where the money went</h2>

        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 30%;">Category</th>
                    <th class="right" style="width: 12%;">Costs</th>
                    <th style="width: 30%;">Share of spend</th>
                    <th class="right" style="width: 14%;">Percent</th>
                    <th class="right" style="width: 14%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $row)
                    <tr>
                        <td class="strong">{{ $row['label'] }}</td>
                        <td class="right mono">{{ $row['count'] }}</td>
                        <td class="middle">
                            <table class="bar">
                                <tr>
                                    <td class="bar-fill" style="width: {{ max(1, (int) round($row['percent'])) }}%;"></td>
                                    <td class="bar-rest"></td>
                                </tr>
                            </table>
                        </td>
                        <td class="right mono">{{ \App\Support\Money::percent($row['percent']) }}</td>
                        <td class="right mono strong">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td colspan="4">Total spend</td>
                    <td class="right mono">{{ $fmt($stats['spent']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif

{{-- ==================== Contributions received ====================== --}}
@if ($contributions->isNotEmpty())
    <div class="section">
        <h2>Contributions received</h2>

        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 11%;">Date</th>
                    <th style="width: 24%;">From</th>
                    <th style="width: 24%;">To</th>
                    <th style="width: 20%;">Method</th>
                    <th class="right" style="width: 21%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contributions as $settlement)
                    <tr>
                        <td class="mono nowrap" style="font-size: 7.6pt;">{{ $settlement->settled_on?->format('d M y') }}</td>
                        <td>{{ $settlement->from?->name ?? 'Unassigned' }}</td>
                        <td>{{ $settlement->to?->name ?? 'Unassigned' }}</td>
                        <td style="font-size: 7.8pt;">
                            {{ $settlement->methodEnum()->label() }}
                            @if ($settlement->reference)
                                <div class="faint mono" style="font-size: 6.8pt;">{{ $settlement->reference }}</div>
                            @endif
                        </td>
                        <td class="right mono strong">{{ $fmt($settlement->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td colspan="4">{{ $stats['settlement_count'] }} contributions from {{ $stats['contributors'] }} traveller(s)</td>
                    <td class="right mono">{{ $fmt($stats['contribution_total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif

{{-- ============================ Trip notes ========================== --}}
@if ($trip->description)
    <div class="section">
        <h2>Trip notes</h2>
        <p class="muted" style="margin: 0; font-size: 8.2pt;">{{ $trip->description }}</p>
    </div>
@endif

{{-- ============================== Footer ============================ --}}
<div class="section">
    <div class="note">
        <span class="strong">How to read this.</span>
        "Paid for the group" is what a traveller has covered so far. "Their share" is what they were
        responsible for. A positive balance means the group owes them; a negative one means they owe the group.
        Sofor only does the maths - no money moves through this document. Keep it as the record of the trip.
    </div>
</div>

<table style="width: 100%; border-collapse: collapse; margin-top: 14px;">
    <tr>
        <td class="top faint" style="font-size: 7.4pt;">
            Sofor trip summary &middot; {{ $trip->title }} &middot; generated {{ now()->format('d M Y, H:i') }}
        </td>
        <td class="top right faint" style="font-size: 7.4pt;">
            Build Your Digital Legacy
        </td>
    </tr>
</table>

{{-- The page footer ("Page 1 of 2") is drawn by TripController::summary after rendering. --}}
</body>
</html>
