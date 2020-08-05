@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Your Numbers Have Been Released</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            This is to notify that your numbers have been released due to your account suspension.
        </p>
    </div>
@endsection