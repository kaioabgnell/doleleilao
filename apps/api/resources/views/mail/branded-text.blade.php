{!! $brand->name !!}

@if (! empty($greeting)){!! $greeting !!}

@endif
@foreach ($introLines as $line){!! $line instanceof \Illuminate\Contracts\Support\Htmlable ? html_entity_decode(strip_tags($line->toHtml())) : $line !!}

@endforeach
@isset($actionText){!! $actionText !!}: {!! $actionUrl !!}

@endisset
@foreach ($outroLines as $line){!! $line !!}

@endforeach
@if (! empty($salutation)){!! $salutation !!}

@endif
—
{!! $brand->footer !!}
