@extends('templates.mail')

@section('title', 'Account suspended')

@section('content')
    <p>
        Hi {{ $user->first_name }},<br/><br/>
        We are contacting you because you have unpaid statements and your account has been suspended.
        <br/><br/>
        <span class="bold">You will no longer be able to receive calls/messages. All numbers associated with your account will be released if this is not resolved in 3 days. To re-enable your account and preserve your numbers please pay all unpaid statements as soon a possible.</span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Billing</i>
        </span>
    </p>
@endsection