@extends('templates.mail')

@section('title', 'New Bug Report')

@section('content')
    <p>
        Hi {{$user->full_name}},<br/><br/>
        Your bug report has been received.<br/><br/>
        <b>ID:</b> {{$bugReport->id}}<br/>
        <b>URL:</b> {{$bugReport->url}}<br/>
        <b>Details:</b> <br/>{{$bugReport->details}}<br/>
    </p>
    <span class="closing">
        Thank you,<br/>
        <i class="closing-person">- MarketFlows Development</i>
    </span>
@endsection