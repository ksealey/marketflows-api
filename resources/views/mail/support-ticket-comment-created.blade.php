@extends('templates.mail')

@section('title', 'Comment added to support ticket')

@section('content')
    <p>
        New comment added to support ticket #{{ $supportTicket->id }} by {{ $user->first_name }} {{ $user->last_name }}.
        <br/>
        <br/>
        <span class="bold"><i>"{{ $supportTicketComment->comment }}"</i></span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Support</i>
        </span>
    </p>
@endsection