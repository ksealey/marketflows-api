@extends('templates.mail')

@section('title', 'Account unsuspended')

@section('content')
    <p>
        Hi {{$user->first_name}},
        <br/><br/>
        
        We are contacting you to let you know that your account has been unsuspended. You will now be able to purchase numbers and receive incoming calls/messages.
        <br/><br/>
    </p>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Billing</i>
    </span>
@endsection