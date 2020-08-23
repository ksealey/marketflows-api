@extends('templates.mail')

@section('content')
    <div class="content">
        <p>
            Welcome, {{ $user->first_name }}!<br/><br/>
            Before you get started, please verify your email address. If the button below does not work, copy and paste the following url into your browser.<br/><br/>
            <a href="{{$verificationUrl}}">{{$verificationUrl}}</a>

            <a href="{{$verificationUrl}}" class="button">Verify email address</a>
        </p>
    </div>
@endsection