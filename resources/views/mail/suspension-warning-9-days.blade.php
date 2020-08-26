@extends('templates.mail')

@section('title', 'Payment past due')

@section('content')
<p>
        Hi {{ $user->first_name }},<br/><br/>
        We are contacting you because you have unpaid statements on your account.
        <br/><br/>
        <span class="bold">All numbers associated with your account will be released in 1 day. To re-enable your account and preserve your numbers please pay all unpaid statements within the next 24 hours.</span>
        <span class="closing">
            Thank you,<br/>
            <i class="closing-person">- MarketFlows Billing</i>
        </span>
    </p>
@endsection