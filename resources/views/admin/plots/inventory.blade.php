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
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="section-title"><i class="fas fa-chart-line"></i> Statistics Overview</h2>
                    </div>
                    
                    <!-- Total Plots -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card total-plots">
                            <div class="stat-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">{{ $chartData['total'] }}</h3>
                                <p class="stat-label">Total Plots</p>
                                <div class="stat-breakdown">
                                    <span class="breakdown-item">
                                        <i class="fas fa-home"></i> Residential: {{ $residentialChartData['sold'] + $residentialChartData['unsold'] + $residentialChartData['hold'] }}
                                    </span>
                                    <span class="breakdown-item">
                                        <i class="fas fa-store"></i> Shops: {{ $shopsChartData['sold'] + $shopsChartData['unsold'] + $shopsChartData['hold'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sold Plots -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card sold-plots">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">{{ $chartData['sold'] }}</h3>
                                <p class="stat-label">Sold Plots</p>
                                <div class="stat-breakdown">
                                    <span class="breakdown-item">
                                        <i class="fas fa-home"></i> Residential: {{ $residentialChartData['sold'] }}
                                    </span>
                                    <span class="breakdown-item">
                                        <i class="fas fa-store"></i> Shops: {{ $shopsChartData['sold'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="stat-percentage">
                                {{ round(($chartData['sold'] / $chartData['total']) * 100, 1) }}%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Unsold Plots -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card unsold-plots">
                            <div class="stat-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">{{ $chartData['unsold'] }}</h3>
                                <p class="stat-label">Unsold Plots</p>
                                <div class="stat-breakdown">
                                    <span class="breakdown-item">
                                        <i class="fas fa-home"></i> Residential: {{ $residentialChartData['unsold'] }}
                                    </span>
                                    <span class="breakdown-item">
                                        <i class="fas fa-store"></i> Shops: {{ $shopsChartData['unsold'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="stat-percentage">
                                {{ round(($chartData['unsold'] / $chartData['total']) * 100, 1) }}%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hold Plots -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card hold-plots">
                            <div class="stat-icon">
                                <i class="fas fa-pause-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">{{ $chartData['hold'] }}</h3>
                                <p class="stat-label">Hold Plots</p>
                                <div class="stat-breakdown">
                                    <span class="breakdown-item">
                                        <i class="fas fa-home"></i> Residential: {{ $residentialChartData['hold'] }}
                                    </span>
                                    <span class="breakdown-item">
                                        <i class="fas fa-store"></i> Shops: {{ $shopsChartData['hold'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="stat-percentage">
                                {{ round(($chartData['hold'] / $chartData['total']) * 100, 1) }}%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="section-title"><i class="fas fa-chart-pie"></i> Visual Analytics</h2>
                    </div>
                    
                    <!-- Total Plots Overview -->
                    <div class="col-md-3">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Plots Overview</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="plotsChart"></canvas>
                            </div>
                            <div class="chart-footer">
                                <div class="chart-stats">
                                    <span class="chart-stat"><i class="fas fa-circle text-primary"></i> Total: {{ $chartData['total'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-success"></i> Sold: {{ $chartData['sold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-danger"></i> Unsold: {{ $chartData['unsold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-warning"></i> Hold: {{ $chartData['hold'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Size Distribution -->
                    <div class="col-md-3">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-ruler-combined"></i> Size Distribution</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="sizeChart"></canvas>
                            </div>
                            <div class="chart-footer">
                                <div class="chart-stats">
                                    <span class="chart-stat"><i class="fas fa-circle text-primary"></i> Total: {{ $sizeChartData['totalSize'] }} sq.ft</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-success"></i> Sold: {{ $sizeChartData['soldSize'] }} sq.ft</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-danger"></i> Unsold: {{ $sizeChartData['unsoldSize'] }} sq.ft</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-warning"></i> Hold: {{ $sizeChartData['holdSize'] }} sq.ft</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Residential Plots -->
                    <div class="col-md-3">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-home"></i> Residential Plots</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="residentialChart"></canvas>
                            </div>
                            <div class="chart-footer">
                                <div class="chart-stats">
                                    <span class="chart-stat"><i class="fas fa-circle text-success"></i> Sold: {{ $residentialChartData['sold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-danger"></i> Unsold: {{ $residentialChartData['unsold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-warning"></i> Hold: {{ $residentialChartData['hold'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shops Plots -->
                    <div class="col-md-3">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-store"></i> Shops</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="shopsChart"></canvas>
                            </div>
                            <div class="chart-footer">
                                <div class="chart-stats">
                                    <span class="chart-stat"><i class="fas fa-circle text-success"></i> Sold: {{ $shopsChartData['sold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-danger"></i> Unsold: {{ $shopsChartData['unsold'] }}</span>
                                    <span class="chart-stat"><i class="fas fa-circle text-warning"></i> Hold: {{ $shopsChartData['hold'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shops Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="section-title"><i class="fas fa-store"></i> Shops Overview</h2>
                    </div>
                    <!-- Sold Shops -->
                    <div class="col-md-4">
                        <div class="status-card sold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-store"></i> Sold Shops ({{ $soldShops->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($soldShops->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Unsold Shops -->
                    <div class="col-md-4">
                        <div class="status-card unsold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-store"></i> Unsold Shops ({{ $unsoldShops->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($unsoldShops->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hold Shops -->
                    <div class="col-md-4">
                        <div class="status-card hold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-store"></i> Hold Shops ({{ $holdShops->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($holdShops->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Residential Plots Section -->
                <div class="row">
                    <div class="col-12">
                        <h2 class="section-title"><i class="fas fa-home"></i> Residential Plots Overview</h2>
                    </div>
                    <!-- Sold Residential Plots -->
                    <div class="col-md-4">
                        <div class="status-card sold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-home"></i> Sold Residential ({{ $soldResidential->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($soldResidential->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Unsold Residential Plots -->
                    <div class="col-md-4">
                        <div class="status-card unsold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-home"></i> Unsold Residential ({{ $unsoldResidential->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($unsoldResidential->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hold Residential Plots -->
                    <div class="col-md-4">
                        <div class="status-card hold">
                            <div class="status-header">
                                <h4 class="m-0"><i class="fas fa-home"></i> Hold Residential ({{ $holdResidential->count() }})</h4>
                            </div>
                            <div class="status-body">
                                @foreach ($holdResidential->chunk(10) as $chunk)
                                    <div class="plot-row">
                                        @foreach ($chunk as $plot)
                                            <span class="plot-tag">{{ $plot->name }}</span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('css')
<style>
  
</style>
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
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }

        // Chart Data
        createPieChart('plotsChart', ['Total', 'Sold', 'Unsold', 'Hold'],
            [{{ $chartData['total'] }}, {{ $chartData['sold'] }}, {{ $chartData['unsold'] }}, {{ $chartData['hold'] }}],
            ['#667eea', '#11998e', '#ff416c', '#f7971e']);

        createPieChart('sizeChart', ['Total','Sold', 'Unsold', 'Hold'],
            [{{ $sizeChartData['totalSize'] }}, {{ $sizeChartData['soldSize'] }}, {{ $sizeChartData['unsoldSize'] }}, {{ $sizeChartData['holdSize'] }}],
            ['#667eea', '#11998e', '#ff416c', '#f7971e']);

        createPieChart('residentialChart', ['Sold', 'Unsold', 'Hold'],
            [{{ $residentialChartData['sold'] }}, {{ $residentialChartData['unsold'] }}, {{ $residentialChartData['hold'] }}],
            ['#11998e', '#ff416c', '#f7971e']);

        createPieChart('shopsChart', ['Sold', 'Unsold', 'Hold'],
            [{{ $shopsChartData['sold'] }}, {{ $shopsChartData['unsold'] }}, {{ $shopsChartData['hold'] }}],
            ['#11998e', '#ff416c', '#f7971e']);
    </script>
@endsection