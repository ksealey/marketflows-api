@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Billing Statement Available</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            The biilling statement {{ $statement->id }}({{ $billingPeriodStart }} - {{ $billingPeriodEnd }}) is now available. Your payment will be processed shortly.
        </p>
        <a href="{{ config('app.frontend_app_url') }}/billing/statements/{{ $statement->id }}" class="button">View Statement</a>
    </div>
@endsection