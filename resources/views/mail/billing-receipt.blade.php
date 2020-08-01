@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Receipt</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            We have processed your payment.<br/><br/>
        </p>
    </div>
@endsection