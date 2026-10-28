@props(['rows' => []])

@php
    use App\Support\Money;

    $data = collect($rows)->map(function ($row) {
        return [
            'label' => $row['label'] ?? '-',
            'value' => (float) ($row['value'] ?? 0),
            'color' => $row['color'] ?? '#0f6f6a',
        ];
    })->filter(fn ($row) => $row['value'] > 0)->values();

    $total = (float) $data->sum('value');
    $circumference = 2 * M_PI * 52;
    $offset = 0.0;
@endphp

<div {{ $attributes }}>
    @if ($data->isEmpty())
        <p class="py-8 text-center text-[13px] text-faint">{{ __('Nothing logged yet.') }}</p>
    @else
        <div class="flex flex-wrap items-center gap-6">
            <div class="relative shrink-0">
                <svg viewBox="0 0 130 130" class="size-[130px] -rotate-90" role="img" aria-label="{{ __('Spending by category') }}">
                    <circle cx="65" cy="65" r="52" fill="none" stroke="var(--surface-2)" stroke-width="16" />
                    @foreach ($data as $row)
                        @php
                            $fraction = $total > 0 ? $row['value'] / $total : 0;
                            $length = round($circumference * $fraction, 2);
                        @endphp
                        <circle cx="65" cy="65" r="52"
                                fill="none"
                                stroke="{{ $row['color'] }}"
                                stroke-width="16"
                                stroke-linecap="butt"
                                stroke-dasharray="{{ $length }} {{ round($circumference - $length, 2) }}"
                                stroke-dashoffset="{{ -$offset }}" />
                        @php $offset += $length; @endphp
                    @endforeach
                </svg>
            </div>

            <ul class="flex min-w-[10rem] flex-1 flex-col gap-2.5">
                @foreach ($data as $row)
                    <li class="flex items-center gap-2.5 text-[13px]">
                        <span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $row['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-muted">{{ $row['label'] }}</span>
                        <span class="num font-semibold text-ink">{{ Money::compact($row['value']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
