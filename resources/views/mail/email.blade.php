@extends('layouts.mail.mail')
@section('styles')
    <style>
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
    </style>
@endsection
@section('title')
    {{ $details['subject'] }}
@endsection

@section('content')
    <h1 class="text-center">{{ $details['title'] }}</h1>
    <h3 class="text-left" style="font-weight: 400">{{ $details['body'] }}</h3>
@endsection
