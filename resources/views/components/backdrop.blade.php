{{-- Warm paper backdrop. Two soft washes and a faint ruled grid, so the
     page has depth without the cold aurora glow the old theme used.
     Sits behind everything and never intercepts a click. --}}
<div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute -right-40 -top-52 size-[36rem] rounded-full blur-[110px] opacity-70"
         style="background: radial-gradient(circle at 40% 40%, var(--brand-tint), transparent 68%)"></div>
    <div class="absolute -left-40 top-40 size-[32rem] rounded-full blur-[120px] opacity-70"
         style="background: radial-gradient(circle at 50% 50%, var(--accent-tint), transparent 70%)"></div>
    <div class="absolute inset-0 grid-lines opacity-[0.45]"
         style="mask-image: radial-gradient(ellipse at 50% 0%, black 8%, transparent 70%)"></div>
</div>
