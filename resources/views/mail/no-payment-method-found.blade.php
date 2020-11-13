@extends('templates.mail')

@section('title', 'No Payment Method Found')

@section('content')
    <p>
        Hi {{ $user->first_name }},
        <br/><br/>
        We are contacting you because we attempted to charge your account but no payment method was found.
        <br/>
        <br/>

        Please add a payment method as soon as possible to avoid any disruptions in service, including phone number releases. 
    </p>
    <a href="{{ $paymentMethodsUrl }}" class="button">Add Payment Method</a>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Billing</i>
    </span>
@endsection