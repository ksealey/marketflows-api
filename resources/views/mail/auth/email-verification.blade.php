@extends('templates.mail')

@section('title', 'Your verification code')

@section('content')
    <p>
        Hi,<br/><br/>
        Your verification code is below. It will be available for the next hour. You can request another one at a later time if this one expires.<br/></br>
        <div style="fonc-weight: bold; font-size: 30px; text-align:center; letter-spacing: 4px;">
            {{$emailVerification->code}}
        </div>
        
    </p>
@endsection