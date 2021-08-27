@extends('templates.mail')

@section('title', 'Welcome')

@section('content')
    <p>
        Welcome {{$user->first_name}},
        <br/><br/>
        We're happy to have you. You now have the power to track, transcribe and learn from your calls. If experience any issue
        
        <br/><br/>Get started by creating your first company.
        <br/><br/>
    </p>
@endsection


