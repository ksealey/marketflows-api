@extends('templates.mail')

@section('title', 'Payment processed')

@section('content')
    <p>
        Hi {{ $user->first_name }},<br/><br/>
        We have processed your payment for statement <a href="{{ $statementUrl }}">#{{ $statement->id }}</a>.<br/>

        Total: <span class="bold">${{ $payment->total }}</span><br/>
        Payment Method: <span class="bold">{{ $paymentMethod->brand }} ending in {{ $paymentMethod->last_4 }}</span><br/><br/> 

        You can view the full statement  <a href="{{ $statementUrl }}">here</a>.
    </p>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Billing</i>
    </span>
@endsection