@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
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
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            @can('create lead')
                                <div class="card-header">
                                    <div>
                                        @php
                                            $fromDate = old('fdate') ?: now()->subMonths(6)->format('d-m-Y');
                                            $toDate = old('tdate') ?: now()->format('d-m-Y');
                                        @endphp




                                        <form action="{{ route('finance.reports.details_index') }}" method="POST"
                                            enctype="multipart/form-data" target="_blank">
                                            @csrf
                                            <div class="row">
                                                <div class="col-sm-2">
                                                    <div class="input-group">
                                                        <label class="fbox">From Date</label>
                                                        <div class="input-group">
                                                            <input type="text" name="fdate" class="date form-control"
                                                                data-input value="{{ $fromDate }}">
                                                            <input type="hidden" id="hiddenFromDate" name="hiddenFromDate"
                                                                value="{{ $fromDate }}">
                                                            @error('fdate')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="input-group">
                                                        <label class="fbox">To Date</label>
                                                        <div class="input-group">
                                                            <input type="text" name="tdate" class="date form-control"
                                                                data-input value="{{ $toDate }}">
                                                            <input type="hidden" id="hiddenToDate" name="hiddenToDate"
                                                                value="{{ $toDate }}">

                                                            @error('tdate')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-4">
                                                    <div class="input-group">
                                                        <label class="fbox">Accounts</label>
                                                        <div class="input-group">
                                                            <select class="form-control select2" name="accounts_id"
                                                                id="accounts_id">
                                                                <option value="">Select Head</option>
                                                                @foreach ($headaccounts as $head)
                                                                    <option value="{{ $head->id }}"
                                                                        {{ old('accounts_id') == $head->id ? 'selected' : '' }}>
                                                                        {{ $head->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('accounts_id')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="input-group">
                                                        <label class="fbox">SubAccounts</label>
                                                        <div class="input-group">
                                                            <select class="form-control select2" name="subaccounts_id[]"
                                                                id="subaccounts_id" multiple>
                                                                <option value="">Select Sub Head</option>
                                                                @foreach ($subheadaccounts as $sub)
                                                                    <option value="{{ $sub->id }}"
                                                                        {{ collect(old('subaccounts_id'))->contains($sub->id) ? 'selected' : '' }}>
                                                                        {{ $sub->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                            @error('subaccounts_id')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer justify-content-between">
                                                <button type="submit" class="btn btn-primary">Show</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan


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

            $('#projects_id').change(function() {
                var projectID = $(this).val();

                console.log(projectID);
                if (projectID) {
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: projectID,
                            action: 'get_head',
                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#accounts_id').empty();
                            $('#subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.project_id == projectID;
                            });

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $('#accounts_id').append(
                                '<option value="">Select an option</option>');
                            $.each(filteredData, function(key, value) {
                                $('#accounts_id').append('<option value="' + value
                                    .head_accounting_id + '">' + value
                                    .head_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#accounts_id').empty();
                    $('#subaccounts_id').empty();
                }
            });

            // Similar change event for 'accounts_id' dropdown to fetch subaccounts based on account selection
            $('#accounts_id').change(function() {
                var accountID = $(this).val();
                var projectID = $('#projects_id').val();


                if (accountID) {
                    // Implement AJAX call to fetch subheadaccounts based on the selected account
                    $.ajax({
                        url: '{{ route('get_account') }}', // Replace with your actual route
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            projectID: projectID,
                            accountID: accountID,
                            action: 'get_child',

                            _token: '{{ csrf_token() }}' // Include CSRF token for Laravel
                        },
                        success: function(data) {
                            $('#subaccounts_id').empty();
                            console.log(data);
                            // Filter data to match selected project ID
                            var filteredData = data.filter(function(item) {
                                return item.head_accounting_id == accountID;
                            });
                            $('#subaccounts_id').append(
                                '<option value="">Select an option</option>');

                            // Append filtered head accounting options to 'Accounts' dropdown
                            $.each(filteredData, function(key, value) {
                                $('#subaccounts_id').append('<option value="' + value
                                    .subhead_accounting_id + '">' + value
                                    .subhead_accounting.name + '</option>');
                            });

                            // You may implement a similar AJAX call to fetch subheadaccounts based on the selected account
                        }
                    });
                } else {
                    $('#subaccounts_id').empty();
                }
            });

        });
    </script>
    <script>
        window.addEventListener("load", function() {
            const fInput = document.querySelector("input[name='fdate']"); // original input
            const hiddenFrom = document.getElementById('hiddenFromDate');

            // Use the value from hidden input if exists, otherwise 6 months ago
            const fromValue = hiddenFrom.value || new Date(new Date().setMonth(new Date().getMonth() - 6));

            flatpickr(fInput, {
                dateFormat: "d-m-Y",
                defaultDate: fromValue,
                allowInput: true,
                onChange: function(selectedDates, dateStr) {
                    hiddenFrom.value = dateStr || '';
                }
            });

            // TO DATE
            const tInput = document.querySelector("input[name='tdate']");
            const hiddenTo = document.getElementById('hiddenToDate');
            const toValue = hiddenTo.value || new Date();

            flatpickr(tInput, {
                dateFormat: "d-m-Y",
                defaultDate: toValue,
                allowInput: true,
                onChange: function(selectedDates, dateStr) {
                    hiddenTo.value = dateStr || '';
                }
            });
        });
    </script>
@endsection
