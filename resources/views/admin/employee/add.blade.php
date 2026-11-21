@extends('admin.layouts.admin')
@section('title', 'Employee Master Add')
@section('pageurl', admin_url('employee-management/employee/list'))

{{-- Add and Edit share one form: resources/views/admin/employee/partials/form.blade.php --}}
@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="align-back-btc d-flex justify-content-end align-items-center">
                                <x-button-back href="{{ admin_url('employee-management/employee/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="basic-form">
                                @include('admin.employee.partials.form')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
