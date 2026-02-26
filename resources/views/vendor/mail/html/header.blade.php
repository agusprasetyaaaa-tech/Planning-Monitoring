@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
                <img src="cid:logo.png" class="logo" alt="IT Interprima Logo"
                    style="max-height: 50px; width: auto; object-fit: contain;">
            @else
                {!! $slot !!}
            @endif
        </a>
    </td>
</tr>