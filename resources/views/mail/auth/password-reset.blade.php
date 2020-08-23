@extends('templates.mail')

@section('content')
    <div class="content">
        <p>
            Hi {{ $user->first_name }},
            <br/><br/>
            It looks like you requested a password reset. To reset your password click the button below. If the button does not work, copy and paste the following url into your browser.
            <br/></br>
            <a href="{{$resetUrl}}" class="word-breaks">{{$resetUrl}}</a>
            <a href="{{$resetUrl}}" class="button">Reset password</a>
        </p>
    </div>
@endsection