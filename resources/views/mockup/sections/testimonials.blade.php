@php
    $quotes = \App\Support\SectionContent::quotes($section['items']);
    $lead = array_shift($quotes);
@endphp
@include('mockup.partials.head')
@if ($lead)
<div class="quotes {{ $quotes ? '' : 'quotes--solo' }}">
    <figure class="quote-lead">
        <blockquote style="margin:0"><p>{{ $lead['quote'] }}</p></blockquote>
        @if ($lead['author'])
            <figcaption class="cite"><span class="avatar">{{ \App\Support\SectionContent::initials($lead['author']) }}</span><span><strong>{{ $lead['author'] }}</strong>@if ($lead['role'])<span>{{ $lead['role'] }}</span>@endif</span></figcaption>
        @endif
    </figure>
    @if ($quotes)
        <div class="quote-side">
            @foreach ($quotes as $quote)
                <figure class="quote-small">
                    <p>{{ $quote['quote'] }}</p>
                    @if ($quote['author'])
                        <figcaption class="cite"><span><strong>{{ $quote['author'] }}</strong>@if ($quote['role'])<span>{{ $quote['role'] }}</span>@endif</span></figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif
</div>
@endif
