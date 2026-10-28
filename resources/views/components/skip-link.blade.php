@props(['target' => 'main'])

{{-- Keyboard users land here first and can jump past the header and nav.
     Hidden until focused, then it becomes a marigold sticker button. --}}
<a href="#{{ $target }}"
   class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-2xl focus:border-[1.5px] focus:border-ink focus:bg-accent focus:px-4 focus:py-2.5 focus:font-display focus:text-sm focus:font-semibold focus:text-ink focus:shadow-sticker-sm">
    {{ __('Skip to content') }}
</a>
