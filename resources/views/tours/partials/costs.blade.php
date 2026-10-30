{{-- The costs tab of the organiser's dashboard (tours/show): what the tour cost. --}}
@php use App\Support\Money; @endphp

<section x-show="tab === 'costs'" x-cloak role="tabpanel" class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-[13px] text-muted">
            {{ __('Total') }} <span class="num font-display text-[17px] font-semibold text-ink">{{ Money::format($account['spent'], $tour->currency) }}</span>
        </p>
        <x-btn type="button" icon="plus" @click="$dispatch('tour-cost')">{{ __('Add cost') }}</x-btn>
    </div>

    @if ($tour->expenses->isEmpty())
        <x-empty icon="receipt" :title="__('No costs yet')" :text="__('Log the bus, the hotel, the guide and the food here, and the account works out what is left.')" />
    @else
        <ul class="divide-y divide-line overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            @foreach ($tour->expenses as $expense)
                <li class="flex items-center gap-3 px-4 py-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-2">
                        <x-glyph :name="$expense->category->icon()" class="size-7" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13.5px] font-semibold text-ink">{{ $expense->title }}</p>
                        <p class="mt-0.5 truncate text-[12px] text-muted">
                            {{ $expense->spent_on->translatedFormat('j M') }} · {{ $expense->category->label() }}
                            @if ($expense->note) · {{ $expense->note }} @endif
                        </p>
                    </div>
                    <p class="num shrink-0 font-display text-[15px] font-semibold text-ink">{{ Money::format($expense->amount, $tour->currency) }}</p>
                    <form method="POST" action="{{ route('tours.costs.destroy', [$tour, $expense]) }}"
                          onsubmit="return confirm(@js(__('Remove this cost?')))">
                        @csrf @method('DELETE')
                        <button type="submit" class="flex size-8 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}">
                            <x-icon name="trash" class="size-3.5" />
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>
