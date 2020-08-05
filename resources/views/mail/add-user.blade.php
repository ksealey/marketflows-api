@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>{{$creator->first_name . ' ' . $creator->last_name }} has added you to their account</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            You have been added to the account "{{ $creator->account->name }}" by {{$creator->first_name . ' ' . $creator->last_name }}</br></br>
            To log in, click the button below. If the button does not work, copy and paste the following url into your browser. {{ config('app.frontend_app_url') }}/login<br/><br/>
            Enter your email address along with the following temporary password: {{ $tempPassword }}</br>
        </p>
        <a href="{{ config('app.frontend_app_url') }}/login" class="button">Log In</a>
    </div>
@endsection