@props(['url'])
{{-- The platform logo (uploaded in Admin -> Settings, or Sofor's own), with
     the name as its alt text for mail apps that block images. --}}
@php $logo = app(\App\Services\BrandingService::class)->emailLogo(); @endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logo['url'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt="{{ config('app.name', 'Sofor') }}" class="logo" style="width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px;">
</a>
</td>
</tr>
