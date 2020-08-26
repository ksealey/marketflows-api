@extends('templates.mail')

@section('title', 'Account created')

@section('content')
    <p>
        Welcome to MarketFlows {{$user->first_name}},
        <br/><br/>
        
        You have been added to the account "{{$creator->account->name}}" by {{$creator->full_name}}.
        <br/><br/>

        To create your new password, click the button below. If the button does not work, copy and paste the following url into your browser.
        <br/><br/>

        <a href="{{$resetUrl}}" class="word-breaks">{{$resetUrl}}</a>
    </p>
    <a href="{{$resetUrl}}" class="button">Create Password</a>
@endsection