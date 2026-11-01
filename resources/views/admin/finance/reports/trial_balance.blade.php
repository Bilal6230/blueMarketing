@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><h1 class="m-0">Trial Balance</h1></div></div>
    <section class="content"><div class="container-fluid">
        <div class="card"><div class="card-body">
            <form method="GET" action="{{ route('finance.reports.trial_balance.show') }}" class="row align-items-end">
                <div class="col-md-3"><label>From</label><input type="date" name="from" value="{{ $from }}" class="form-control" required></div>
                <div class="col-md-3"><label>To</label><input type="date" name="to" value="{{ $to }}" class="form-control" required></div>
                <div class="col-md-2"><button class="btn btn-primary btn-block" type="submit">Show</button></div>
            </form>
        </div></div>
    </div></section>
</div>
@endsection
