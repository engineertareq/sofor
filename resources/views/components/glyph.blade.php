@props(['name' => 'compass', 'class' => 'size-6', 'label' => null])
{{-- Sofor's own picture icons (App\Support\Glyphs), used where the app once showed emoji. --}}
{!! \App\Support\Glyphs::svg($name, $class, $label) !!}
