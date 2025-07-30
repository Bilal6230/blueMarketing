@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <!-- Card 1 -->
                    <div class="col-md-4">
                        <div class="card shadow" style="background-color: #fd7e14; color: #fff; border-radius: 10px;">
                            <div class="card-body text-center">
                                <h5 style="font-weight: bold;">Total Due Amount ({{ \Carbon\Carbon::today()->format('d M Y') }})</h5>
                                <p class="h4">{{ Setting::roundformatAmount($sum_due_amount) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-md-4">
                        <div class="card shadow" style="background-color: #4caf50; color: #fff; border-radius: 10px;">
                            <div class="card-body text-center">
                                <h5 style="font-weight: bold;">Total Received</h5>
                                <p class="h4">{{ Setting::roundformatAmount($sum_received) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-md-4">
                        <div class="card shadow" style="background-color: #2196f3; color: #fff; border-radius: 10px;">
                            <div class="card-body text-center">
                                <h5 style="font-weight: bold;">Balance</h5>
                                <p class="h4">{{ Setting::roundformatAmount($sum_due_amount - $sum_received) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ $title }}</h3>
                            </div>

                            <!-- Table Content -->
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Broker</th>
                                            <th>Plot</th>
                                            <th>Customer</th>
                                            <th>Phone</th>
                                            <th>Due Amount</th>
                                            <th>Received</th>
                                            <th>Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ Setting::getShortDate($i->booking_date) }}</td>
                                                <td>{{ $i->broker->name ?? "" }}</td>
                                                <td>{{ Setting::getPlotTypeShort($i->plot_type) }} - <a href="#">{{ $i->plot->name ?? 'N/A' }}</a></td>
                                                <td>{{ $i->customer->first_name ?? 'N/A' }} {{ $i->customer->last_name ?? '' }}</td>
                                                <td>{{ $i->customer->phone_number ?? 'N/A' }}</td>
                                                <td>{{ Setting::roundformatAmount(getSumDueAmount($i->id)) }}</td>
                                                <td>{{ Setting::roundformatAmount(getSumRecovery($i->plot_id, 'amount_out')) }}</td>
                                                <td>{{ Setting::roundformatAmount(getSumDueAmount($i->id) - getSumRecovery($i->plot_id, 'amount_out')) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            $(document).on("click", '.btn-edit', function() {
                let id = $(this).attr("data-id");
                // Your edit logic here
            });
        });
    </script>
@endsection

@section('modal')
    <!-- Add your modals here if needed -->
@endsection
