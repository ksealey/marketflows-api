@extends('templates.mail')

@section('content')
    <div class="header-block">
        <h3>Your Automated Report Is Ready</h3>
    </div>
    <div class="content">
        <p style="color: #76819B;">
            Your automated report "{{$report->name}}" is ready. See attached.<br/>
        </p>
    </div>
@endsection