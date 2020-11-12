@extends('templates.mail')

@section('title', 'New Feature Suggestion')

@section('content')
    <p>
        Hi {{$user->full_name}},<br/><br/>
        Your suggested feature has been received. You will receive any updates regarding this feature.<br/><br/>
        <b>ID:</b> {{$suggestedFeature->id}}<br/>
        <b>URL:</b> {{$suggestedFeature->url}}<br/>
        <b>Details:</b> <br/>{{$suggestedFeature->details}}<br/>
    </p>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Development</i>
    </span>
@endsection