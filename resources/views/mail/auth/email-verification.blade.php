@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Verify your email address</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Hi {{ $user->first_name }},<br/><br/>
            Before you can get started, please verify your email address. To do this, you can click the button below. If the button does not work, copy and paste the following url into your browser.<br/>
            {{ config('app.frontend_app_url') }}/verify-email?uid={{ $verification->user_id }}&key={{ $verification->key }}

            <a href="{{ config('app.frontend_app_url') }}/verify-email?uid={{ $verification->user_id }}&key={{ $verification->key }}" class="button">Verify email address</a>
        </p>
    </div>
@endsection