@extends('templates.mail')

@section('title', 'Welcome to Marketflows')

@section('content')
    <p>
        Hey {{ $user->first_name }},<br/><br/>
        Please verify your email address. If the button below does not work, copy and paste the following url into your browser.<br/><br/>
        <a href="{{$verificationUrl}}" class="word-breaks">{{$verificationUrl}}</a>
        <a href="{{$verificationUrl}}" class="button">Verify email address</a>
    </p>
@endsection