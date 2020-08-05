@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Reset Your Password</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            It looks like you requested a password reset. To reset your password click the button below. If the button does not work, copy and paste the following url into your browser.<br/>
            {{ config('app.frontend_app_url') }}/reset-password?user_id={{$user->id}}&token={{$user->password_reset_token}}

            <a href="{{ config('app.frontend_app_url') }}/reset-password?user_id={{$user->id}}&token={{$user->password_reset_token}}" class="button">Reset your password</a>
        </p>
    </div>
@endsection