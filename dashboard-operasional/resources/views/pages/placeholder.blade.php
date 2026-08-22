@extends('layouts.app')
@section('title', $title)

@section('content')

    <x-page-header :title="$title" />

    <x-card padding="p-16">
        <x-empty-state
            icon="bx-wrench"
            title="This page is under development"
            :description="$description" />
    </x-card>

@endsection