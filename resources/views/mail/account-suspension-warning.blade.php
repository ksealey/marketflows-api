@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Your Numbers Will Be Released</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            This is a reminder that your numbers will be released on {{ $releaseNumbersAt->format('M j, Y') }}. To stop this action, please take the neccessary steps to have your account re-enabled. This may include paying any past-due statements or adding a valid payment method.
        </p>
    </div>
@endsection