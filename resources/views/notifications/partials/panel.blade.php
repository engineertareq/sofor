{{-- The alerts panel (resources/js/alerts.js on resources/js/sheet.js): a bottom sheet on a
     phone, a card under the bell from a laptop up. The rows come from NotificationController::feed
     (notifications/partials/item); this is the frame around them and the states between.
     A row's form is caught here, once, by @submit. --}}
<div id="app-alerts" x-data="sheet('alerts')" x-cloak class="fixed inset-0 z-[80]"
     :class="shown ? 'visible' : 'invisible pointer-events-none transition-[visibility] duration-[420ms] lg:duration-200'"
     role="dialog" aria-modal="true" aria-labelledby="app-alerts-title">
    {{-- Dims the page on a phone; on a laptop it is clear, only there to close the card on a click outside. --}}
    <div class="absolute inset-0 sheet-backdrop transition-opacity duration-300 lg:bg-transparent lg:backdrop-blur-none"
         :class="shown ? 'opacity-100' : 'opacity-0'" @click="hide()"></div>

    <section x-ref="sheet" tabindex="-1" :style="sheetStyle"
             @touchstart="grab($event)" @touchmove="pull($event)" @touchend="letGo($event)" @touchcancel="letGo($event)"
             class="absolute inset-x-0 bottom-0 mx-auto flex h-[85dvh] max-w-lg flex-col rounded-t-[1.75rem] bg-canvas shadow-[0_-18px_40px_-18px_rgb(23_33_31/0.45)] outline-none
                    lg:inset-x-auto lg:bottom-auto lg:right-8 lg:top-[4.4rem] lg:mx-0 lg:h-[min(36rem,calc(100dvh-6rem))] lg:w-[24.5rem] lg:max-w-none lg:rounded-3xl lg:border lg:border-line lg:bg-surface lg:shadow-[0_24px_60px_-24px_rgb(23_33_31/0.4)]">

        <div class="flex min-h-0 flex-1 flex-col" @submit="open($event)"
             x-data="alerts({ feedUrl: @js(route('notifications.feed')), readAllUrl: @js(route('notifications.read-all')), unread: @js($unread) })">

            <div class="shrink-0 touch-none px-5 pb-3 pt-2 lg:touch-auto lg:px-4 lg:pt-3.5">
                <span class="mx-auto block h-[5px] w-10 rounded-full bg-line-strong lg:hidden" aria-hidden="true"></span>

                <div class="mt-2 flex items-center gap-2 lg:mt-0">
                    <h2 id="app-alerts-title" class="font-display text-[20px] font-semibold text-ink lg:text-[16px]">{{ __('Alerts') }}</h2>
                    <span x-show="unread > 0" class="rounded-full bg-negative px-2 py-0.5 text-[11px] font-bold leading-4 text-white">
                        <span x-text="unreadLabel"></span> {{ __('New') }}
                    </span>

                    <div class="ml-auto flex items-center gap-1">
                        <button type="button" x-show="unread > 0" @click="readAll()"
                                class="inline-flex h-8 items-center gap-1 rounded-full px-2.5 text-[12px] font-semibold text-brand transition hover:bg-brand-tint active:scale-95">
                            <x-icon name="check" class="size-3.5" /> {{ __('Mark all read') }}
                        </button>
                        <button type="button" @click="hide()" aria-label="{{ __('Close') }}"
                                class="flex size-8 items-center justify-center rounded-full bg-surface-2 text-muted transition hover:text-ink active:scale-90">
                            <x-icon name="close" class="size-4" />
                        </button>
                    </div>
                </div>

                {{-- Everything, or only what has not been read --}}
                <div class="mt-3 grid grid-cols-2 gap-1 rounded-xl bg-surface-2 p-1" role="tablist" aria-label="{{ __('Alerts') }}">
                    @foreach (['all' => __('All'), 'unread' => __('Unread')] as $value => $label)
                        <button type="button" role="tab" @click="show(@js($value))" :aria-selected="filter === @js($value)"
                                class="h-8 rounded-lg text-[12.5px] font-semibold transition"
                                :class="filter === @js($value) ? 'bg-surface text-ink shadow-[0_1px_2px_rgb(24_24_27/0.1)] lg:bg-canvas' : 'text-muted hover:text-ink'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div x-ref="scroller" data-sheet-list :aria-busy="state === 'loading'"
                 class="min-h-0 flex-1 overflow-y-auto overscroll-contain border-t border-line px-2 py-1.5">

                {{-- The first time: rows shaped like the real ones while they load. --}}
                <ul x-show="state === 'loading' || state === 'idle'" class="flex flex-col gap-0.5" aria-hidden="true">
                    @foreach (range(1, 5) as $i)
                        <li class="flex items-start gap-3 px-3 py-3">
                            <span class="size-10 shrink-0 animate-pulse rounded-[0.85rem] bg-surface-2"></span>
                            <span class="flex-1 pt-1">
                                <span class="block h-3 w-4/5 animate-pulse rounded-full bg-surface-2"></span>
                                <span class="mt-2 block h-3 w-3/5 animate-pulse rounded-full bg-surface-2"></span>
                                <span class="mt-2 block h-2.5 w-1/4 animate-pulse rounded-full bg-surface-2"></span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <ul x-ref="items" x-show="state === 'ready'" class="flex flex-col gap-0.5"></ul>

                {{-- Nothing to show --}}
                <div x-show="state === 'ready' && empty" class="flex flex-col items-center px-6 py-12 text-center">
                    <span x-show="filter === 'all'" class="flex size-14 items-center justify-center rounded-3xl bg-brand-tint text-brand">
                        <x-icon name="bell" class="size-6" />
                    </span>
                    <span x-show="filter === 'unread'" class="flex size-14 items-center justify-center rounded-3xl bg-positive-tint text-positive">
                        <x-icon name="check-circle" class="size-6" />
                    </span>
                    <div x-show="filter === 'all'">
                        <p class="mt-4 font-display text-[15px] font-semibold text-ink">{{ __('No alerts yet') }}</p>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ __('When a cost is added, a payment recorded or a trip ends, you will see it here.') }}</p>
                    </div>
                    <p x-show="filter === 'unread'" class="mt-4 font-display text-[15px] font-semibold text-ink">{{ __('You are all caught up.') }}</p>
                </div>

                {{-- Could not load --}}
                <div x-show="state === 'error'" role="alert" class="flex flex-col items-center px-6 py-12 text-center">
                    <span class="flex size-14 items-center justify-center rounded-3xl bg-negative-tint text-negative">
                        <x-icon name="alert" class="size-6" />
                    </span>
                    <p class="mt-4 font-display text-[15px] font-semibold text-ink">{{ __('Your alerts could not be loaded.') }}</p>
                    <button type="button" @click="load(true)"
                            class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-full border border-line bg-surface px-4 text-[12.5px] font-semibold text-ink transition hover:border-line-strong active:scale-95">
                        <x-icon name="refresh" class="size-3.5" /> {{ __('Try again') }}
                    </button>
                </div>

                {{-- The end of the list: in view, the next page loads. --}}
                <div x-ref="end" class="h-px" aria-hidden="true"></div>
                <div x-show="loadingMore" class="flex justify-center py-3">
                    <span class="size-5 animate-spin rounded-full border-2 border-line border-t-brand"></span>
                </div>
            </div>

            <div class="shrink-0 border-t border-line px-3 pb-[calc(0.5rem+env(safe-area-inset-bottom))] pt-2 lg:pb-2">
                <a href="{{ route('notifications.index') }}" @click="hide()"
                   class="flex h-10 items-center justify-center gap-1 rounded-xl text-[13px] font-semibold text-brand transition hover:bg-brand-tint active:bg-brand-tint">
                    {{ __('See all alerts') }} <x-icon name="chevron-right" class="size-4" />
                </a>
            </div>
        </div>
    </section>
</div>
