@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Payment Method Failed</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            The payment method ending in {{ $paymentMethod->last_4 }} failed while attempting to charge the total ${{ number_format($statement->total, 2) }} for statement {{ $statement->id }}. If an additional payment method is available on your account, it will be used instead.
        </p>
        <a href="{{ config('app.frontend_app_url') }}/billing/payment-methods" class="button">Update Payment Method</a>
    </div>
@endsection