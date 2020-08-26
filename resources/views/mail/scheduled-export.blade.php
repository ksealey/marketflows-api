@extends('templates.mail')

@section('title', 'Scheduled export')

@section('content')
    <p>
        Your scheduled export of the report "{{ $report->name }}" is ready. Please see the attached file.
    </p>
@endsection