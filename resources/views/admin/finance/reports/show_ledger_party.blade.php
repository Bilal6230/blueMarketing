@extends('admin.layouts.master')
@section('content')
@php($reportMode = 'party')
@include('admin.finance.reports.partials.modern_ledger_table')
@endsection

@section('js')
<script src="{{ asset('js/finance-report-modern.js') }}?v=1"></script>
@endsection
