Hello {{ $user->first_name }},

<a href="/reset-password/?user_id={{$user->id}}&token={{$user->password_reset_token}}" target="__blank">Click Here</a>