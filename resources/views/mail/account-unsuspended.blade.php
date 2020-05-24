@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Your Account Has Been Unsuspended</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            Your account has been unsuspended. You will now have access to all the previously available functionality.
        </p>
    </div>
@endsection

