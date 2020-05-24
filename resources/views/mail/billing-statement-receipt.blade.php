@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Payment Received</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            We have processed the payment for statement {{ $statement->id }}({{ $billingPeriodStart }} - {{ $billingPeriodEnd }}) for the total of ${{ number_format($total, 2) }}.<br/><br/>
            <small>Thank you for your payment.</small>
        </p>
        <a href="{{ config('app.frontend_app_url') }}/billing/statements/{{ $statement->id }}" class="button">View Statement</a>
    </div>
@endsection