@extends('templates.mail')

@section('title', 'Payment past due')

@section('content')
    <p>
        Hi {{ $user->first_name }},<br/><br/>
        We are contacting you because you have unpaid statements on your account. To avoid any disruption in service please update your payment method as soon as possible.
        <br/><br/>
        <span class="bold">If any statement goes unpaid, you will no longer be able to receive calls or texts in 4 days and all numbers associated with your account will be released in 7 days.</span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Billing</i>
        </span>
    </p>
@endsection