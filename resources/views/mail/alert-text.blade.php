Olá, {!! $name !!}!

{!! $intro !!}

@foreach ($items as $item)
{!! $item['heading'] !!}
{!! $item['text'] !!}

@endforeach
@if ($more > 0)
E mais {!! $more !!}.

@endif
{!! $buttonLabel !!}:
{!! $link !!}

—
Wishlisti · {!! $homeHost !!}
