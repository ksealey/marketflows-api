@extends('templates.mail')

@section('title', 'Payment past due')

@section('content')
    <p>
        Hi {{ $user->first_name }},<br/><br/>
        We are contacting you because you have unpaid statements on your account.
        <br/><br/>
        <span class="bold">You will no longer be able to receive calls and all numbers associated with your account will be released in 3 days. To re-enable your account and preserve your numbers please pay all unpaid statements as soon a possible.</span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Billing</i>
        </span>
    </p>
@endsection