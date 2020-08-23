@extends('templates.mail')

@section('title', 'Account created')

@section('content')
    <div class="content">
        <p>
            Welcome to MarketFlows {{$user->first_name}},
            <br/><br/>
            
            You have been added to the account "{{$creator->account->name}}" by {{$creator->full_name}}.
            <br/><br/>

            Login Email Address: <span class="bold">{{$user->email}}</span>
            <br/>

            Login Temporary Password: <span class="bold">{{$tempPassword}}</span>
            <br/><br/>

            To log in, click the button below. If the button does not work, copy and paste the following url into your browser.
            <br/><br/>

            <a href="{{$loginUrl}}" class="word-wraps">{{$loginUrl}}</a>
        </p>
        <a href="{{$loginUrl}}" class="button">Log In</a>
    </div>
@endsection