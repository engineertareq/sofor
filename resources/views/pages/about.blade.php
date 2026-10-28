<x-layouts.site title="{{ __('About') }}" :heading="__('About Sofor')"
                 :subtitle="__('A Solutor product built in Dhaka for the way groups travel, spend and settle up.')">

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Why we built it') }}</h2>
        <p>
            {{ __('Every group trip in Bangladesh ends the same way: somebody covered the cottage, somebody else paid for the CNG fares, and three weeks later nobody remembers who owes what. Sofor exists to end that conversation. We keep one honest ledger that every traveller can see and trust.') }}
        </p>
        <p>
            {{ __('The maths is deliberately simple. Add a cost, say who paid it, and Sofor divides it across the travellers who shared it. If a trip cost ৳5000 and five people travelled, everybody sees ৳1000 - instantly, with the leftover poisha handled correctly.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What Sofor is not') }}</h2>
        <p>
            {{ __('Sofor never holds your money. We do not process payments, we do not take a cut and we do not ask for your wallet details. Travellers settle up directly through bKash, Nagad, bank transfer or cash, then record it here so everyone\'s numbers stay in sync.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What is coming next') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Phone and email verification for stronger accounts'),
                __('Live payment gateway integration for bKash and EPS'),
                __('Receipt photo attachments on every cost'),
                __('Offline-friendly ledger entry for hill tracts with weak signal'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="sparkles" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Talk to the team') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-3xl border border-line bg-surface-2 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('Office') }}</p>
                <p class="mt-1.5 text-[13px] text-ink">{{ __('Lift 6, Elite House') }}<br>{{ __('54 Motijheel C/A') }}<br>{{ __('Dhaka - 1000, Bangladesh') }}</p>
            </div>
            <div class="rounded-3xl border border-line bg-surface-2 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('Phone') }}</p>
                <p class="mt-1.5 text-[13px] text-ink">
                    <a href="tel:+8801922992279" class="hover:text-brand">+880 1922-992279</a><br>
                    <a href="tel:+8801910069929" class="hover:text-brand">+880 1910-069929</a>
                </p>
            </div>
        </div>
        <p class="text-[13px]">
            {{ __('Build Your Digital Legacy - that is the Solutor promise, and Sofor is one more piece of it.') }}
        </p>
    </section>
</x-layouts.site>
