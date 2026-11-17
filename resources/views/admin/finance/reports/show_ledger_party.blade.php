@extends('admin.layouts.master')
@section('content')
<link rel="stylesheet" href="{{ asset('css/finance-report-modern.css') }}?v=1">
@php($reportMode = 'party')
@include('admin.finance.reports.partials.modern_ledger_table')
@endsection

@section('js')
<script src="{{ asset('js/finance-report-modern.js') }}?v=1"></script>
@endsection
