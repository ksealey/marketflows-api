@extends('templates.mail')

@section('title', 'Payment failed')

@section('content')
    <p>
        Hi {{ $user->first_name }},
        <br/><br/>
        We are contacting you because your payment could not be processed.
        <br/>
        <br/>
        Payment Method: <span class="bold">{{ $paymentMethod->brand }} ending in {{ $paymentMethod->last_4 }}</span>
        <br/>

        Total: <span class="bold">${{ number_format($statement->total, 2) }}</span>
        <br/>
        <br/>

        Please update your payment method as soon as possible to avoid any disruptions in service, including phone number releases. 
    </p>
    <a href="{{ $paymentMethodsUrl }}" class="button">Update Payment Method</a>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Billing</i>
    </span>
@endsection