@extends('admin.layouts.admin')
@section('title', __('leftmenu.menu_30'))
@section('pageurl', admin_url('attendance-management/permission/list'))

{{-- Add and Edit share one form: resources/views/admin/attendance/permission/partials/form.blade.php --}}
@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="align-back-btc d-flex justify-content-end align-items-center">
                                <x-button-back href="{{ admin_url('attendance-management/permission/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="basic-form">
                                @include('admin.attendance.permission.partials.form')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
