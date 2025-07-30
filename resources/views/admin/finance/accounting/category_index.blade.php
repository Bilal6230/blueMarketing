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
                                        <form action="{{ route('accounting.category_store') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="row">
                                                <div class="col-sm-4">
                                                    <div class="input-group">
                                                        <label class="fbox">Accounts</label>
                                                        <div class="input-group">
                                                            <select class="form-control select2" name="accounts_id" id="accounts_id">
                                                                <option value="">Select an account</option>
                                                                @foreach ($headaccounts as $v)
                                                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
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
                                                            <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                                                                <option value="">Select a sub account</option>
                                                                @foreach ($subheadaccounts as $v)
                                                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('subaccounts_id')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="input-group">
                                                        <label class="fbox">Projects</label>
                                                        <div class="input-group">
                                                            <select class="form-control select2" name="projects_id" id="projects_id">
                                                                <option value="">Select a project</option>
                                                                @foreach ($projects as $v)
                                                                    <option value="{{ $v->id }}">{{ $v->project }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('projects_id')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal-footer justify-content-between">
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                            {{-- <div class="card-body table-responsive">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Head Account</th>
                                            <th>Sub Head Account</th>
                                            <th>Price</th>
                                            <th>Project</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $i)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    {{ $i->head_accounting_name }}
                                                </td>
                                                <td>
                                                    {{ $i->subhead_accounting_name }}
                                                </td>
                                                <td>
                                                    {{ $i->price }}
                                                </td>
                                                <td>
                                                    {{ $i->project_name }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div> --}}
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


        });
    </script>
@endsection
