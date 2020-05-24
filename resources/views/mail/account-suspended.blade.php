@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Your Account Has Been Suspended</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            Your account has been suspended for the following reason:<br/>

            <small style="color:red">{{$account->suspension_message}}</small>
        </p>
    </div>
@endsection