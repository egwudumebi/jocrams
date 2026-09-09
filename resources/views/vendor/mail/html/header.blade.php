@props(['url'])
@php($logoUrl = \App\Support\Settings\SiteBranding::emailLogoUrl())
@if ($logoUrl)
<tr>
<td class="header" style="padding: 25px 0; text-align: center;">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logoUrl }}" class="logo" alt="{{ \App\Support\Settings\SiteBranding::siteName() }}" style="max-width: 100%; border: none; height: auto; max-height: 75px; width: auto; margin-top: 15px; margin-bottom: 10px;">
</a>
</td>
</tr>
@endif
