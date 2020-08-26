@extends('templates.mail')

@section('title', 'Support ticket #{{ $supportTicket->id }} created')

@section('content')
    <p>
        Hi {{$user->first_name}},
        <br/></br>
        Support ticket #{{ $supportTicket->id }} has been created and will be assigned shortly.
        <br/>
        <br/>
        Ugency: <span class="bold">{{ $supportTicket->urgency }}</span>
        <br/>
        Subject: <span class="bold">{{ $supportTicket->subject }}</span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Support</i>
        </span>
    </p>
@endsection