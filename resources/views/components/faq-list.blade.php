{{-- Questions and answers as an accordion, the first one open. The answers stay
     in the page when closed, so search engines read them; pages give the same
     list as FAQPage data with App\Support\Schema::faq(). --}}
@props(['faqs', 'id' => 'faq'])

<div x-data="{ open: 0 }" {{ $attributes->merge(['class' => 'divide-y divide-line border-y border-line']) }}>
    @foreach ($faqs as $index => [$question, $answer])
        <div>
            <h3>
                <button type="button" @click="open = open === {{ $index }} ? null : {{ $index }}"
                        :aria-expanded="(open === {{ $index }}).toString()" aria-controls="{{ $id }}-{{ $index }}"
                        class="flex w-full items-center justify-between gap-5 py-5 text-left">
                    <span class="font-display text-[16.5px] font-semibold text-ink">{{ $question }}</span>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px] border-line-strong text-muted transition"
                          :class="open === {{ $index }} && 'rotate-45 border-ink bg-ink text-canvas'">
                        <x-icon name="plus" class="size-4" />
                    </span>
                </button>
            </h3>
            <div id="{{ $id }}-{{ $index }}" x-show="open === {{ $index }}" x-transition.opacity.duration.200ms @if ($index !== 0) x-cloak @endif>
                <p class="pb-6 pr-12 text-[14.5px] leading-relaxed text-muted">{{ $answer }}</p>
            </div>
        </div>
    @endforeach
</div>
