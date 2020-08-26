@extends('templates.mail')

@section('title', 'Payment past due')

@section('content')
    <p>
        Hi {{ $user->first_name }},<br/><br/>
        We are contacting you because you have unpaid statements on your account. 
        <br/><br/>
        <span class="bold">Your phone numbers have been released and cannot be recovered.</span>
        <br/><br/>
        Please resolve all past due statements as soon as possible.
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Billing</i>
        </span>
    </p>
@endsection