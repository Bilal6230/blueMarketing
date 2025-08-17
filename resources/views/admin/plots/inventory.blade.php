@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-primary">{{ $title }}</h1>
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
                <div class="row">
                    <!-- Total Plots Overview -->
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-header bg-primary text-white">
                                <h3 class="card-title"><i class="fas fa-chart-pie"></i> Plots Overview</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="plotsChart" style="max-height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Size Distribution -->
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-header bg-success text-white">
                                <h3 class="card-title"><i class="fas fa-ruler-combined"></i> Size Distribution</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="sizeChart" style="max-height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Residential Plots -->
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-header bg-info text-white">
                                <h3 class="card-title"><i class="fas fa-home"></i> Residential Plots</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="residentialChart" style="max-height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Shops Plots -->
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-header bg-danger text-white">
                                <h3 class="card-title"><i class="fas fa-store"></i> Shops</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="shopsChart" style="max-height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <!-- Sold Shops -->
                    <div class="col-4">
                        <div class="p-2 bg-success text-white">
                            <h4 class="m-0"><i class="fas fa-store"></i> Sold Shops</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($soldShops->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>

                    <!-- Unsold Shops -->
                    <div class="col-4">
                        <div class="p-2 bg-danger text-white">
                            <h4 class="m-0"><i class="fas fa-store"></i> Unsold Shops</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($unsoldShops->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-warning text-white">
                            <h4 class="m-0"><i class="fas fa-store"></i> Hold Shops</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($holdShops->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <!-- Sold Residential Plots -->
                    <div class="col-4">
                        <div class="p-2 bg-success text-white">
                            <h4 class="m-0"><i class="fas fa-home"></i> Sold Residential Plots</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($soldResidential->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>

                    <!-- Unsold Residential Plots -->

                    <div class="col-4">
                        <div class="p-2 bg-danger text-white">
                            <h4 class="m-0"><i class="fas fa-home"></i> Unsold Residential Plots</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($unsoldResidential->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>
                    <!-- Hold Residential Plots -->

                    <div class="col-4">
                        <div class="p-2 bg-warning text-white">
                            <h4 class="m-0"><i class="fas fa-home"></i> Hold Residential Plots</h4>
                        </div>
                        <div class="plot-list p-2 border">
                            @foreach ($holdResidential->chunk(15) as $chunk)
                                <p>
                                    @foreach ($chunk as $plot)
                                        {{ $plot->name }} &nbsp; | &nbsp;
                                    @endforeach
                                </p>
                                <hr>
                            @endforeach
                        </div>
                    </div>



                </div>


            </div>
        </section>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function createPieChart(canvasId, labels, data, colors) {
            new Chart(document.getElementById(canvasId), {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Chart Data
        createPieChart('plotsChart', ['Total', 'Sold', 'Unsold', 'Hold'],
            [{{ $chartData['total'] }}, {{ $chartData['sold'] }}, {{ $chartData['unsold'] }}, {{ $chartData['hold'] }}],
            ['#007bff', '#28a745', '#dc3545', '#ffc107']);

        createPieChart('sizeChart', ['Total','Sold', 'Unsold', 'Hold'],
            [{{ $sizeChartData['totalSize'] }}, {{ $sizeChartData['soldSize'] }}, {{ $sizeChartData['unsoldSize'] }}, {{ $sizeChartData['holdSize'] }}],
            ['#007bff', '#28a745', '#dc3545', '#ffc107']);

        createPieChart('residentialChart', ['Sold', 'Unsold', 'Hold'],
            [{{ $residentialChartData['sold'] }}, {{ $residentialChartData['unsold'] }}, {{ $residentialChartData['hold'] }}],
            ['#28a745', '#dc3545', '#ffc107']);

        createPieChart('shopsChart', ['Sold', 'Unsold', 'Hold'],
            [{{ $shopsChartData['sold'] }}, {{ $shopsChartData['unsold'] }}, {{ $shopsChartData['hold'] }}],
            ['#28a745', '#dc3545', '#ffc107']);
    </script>
@endsection
