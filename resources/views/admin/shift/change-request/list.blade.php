@extends('admin.layouts.admin')
@section('title', 'Shift Change Request')
@section('pageurl', admin_url('shift-management/shift-change-request/list'))

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <h4 class="card-title"></h4>
                    <div class="d-flex flex-wrap justify-content-end gap-2 p-2">
                        <x-button-filter dataId="" class="search me-1" href=""></x-button-filter>
                        <x-button-add dataId="" class="add btn btn-primary ms-1"
                            href="{{ admin_url('shift-management/shift-change-request/add') }}">{{ __('common.add') }}</x-button-add>
                    </div>
                    <div id="search" class="collapse">
                        <form action="" id="formsearch">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3 form-input">
                                        <label for="request_status" class="form-label">Request Status</label>
                                        <select name="request_status" id="request_status" style="width: 100%" class="form-control single-select">
                                            <option value="">Select Status</option>
                                            @foreach ($statusList as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3 form-input">
                                        <label for="requested_shift_id" class="form-label">Shift Needed</label>
                                        <select name="requested_shift_id" id="requested_shift_id" style="width: 100%" class="form-control single-select">
                                            <option value="">Select Shift</option>
                                            @foreach ($shiftList as $shift)
                                                <option value="{{ encryptId($shift->id) }}">{{ $shift->shift_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3 form-input">
                                        <label for="mine" class="form-label">Show</label>
                                        <select name="mine" id="mine" style="width: 100%" class="form-control single-select">
                                            <option value="">All Requests</option>
                                            <option value="approval">Waiting for My Approval</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mt-3">
                                        <x-button-search></x-button-search>
                                        <x-button-reset></x-button-reset>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <hr>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="datatable-list"
                                class="table primary-table-bordered table-bordered table-striped display responsive nowrap w-100 mt-2 datatable-list">
                                <thead class="thead-primary">
                                    <tr>
                                        <th>{{ __('common.sno') }}</th>
                                        <th>Request No</th>
                                        <th>Employee ID</th>
                                        <th>Employee Name</th>
                                        <th>Current Shift</th>
                                        <th>Shift Needed</th>
                                        <th>Status</th>
                                        <th>Approver</th>
                                        <th>{{ __('common.created_by') }}</th>
                                        <th>{{ __('common.created_date') }}</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@stop

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(function() {
            var table = $('.datatable-list').DataTable({
                autoWidth: false,
                responsive: true,
                processing: false,
                serverSide: true,
                searching: true,
                ordering: false,
                layout: {
                    top2Start: 'buttons',
                    top2End: { search: { placeholder: '' } },
                    topStart: '',
                    topEnd: '',
                    bottomStart: '',
                    bottomEnd: '',
                    bottom2Start: 'info',
                    bottom2End: 'paging'
                },
                ajax: {
                    url: "{{ admin_url('shift-management/shift-change-request/list') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: function(d) {
                        d.request_status = $('#request_status').val();
                        d.requested_shift_id = $('#requested_shift_id').val();
                        d.mine = $('#mine').val();
                    },
                    error: function(xhr) {
                        if (xhr.status === 419) {
                            alert('Session has expired. You will be redirected to the login page.');
                            window.location.href = "{{ url('') }}";
                        }
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', searchable: false },
                    { data: 'request_no', name: 'request_no' },
                    { data: 'emp_id', name: 'emp_id' },
                    { data: 'emp_name', name: 'emp_name' },
                    { data: 'current_shift_name', name: 'current_shift_name' },
                    { data: 'requested_shift_name', name: 'requested_shift_name' },
                    { data: 'request_status', name: 'request_status' },
                    { data: 'approver', name: 'approver' },
                    { data: 'created_by', name: 'created_by' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'action', name: 'action', searchable: false },
                ],
                language: {
                    paginate: {
                        first: '<i title="{{ __('common.first') }}" class="fa fa-angle-double-left" aria-hidden="true"></i>',
                        last: '<i title="{{ __('common.last') }}" class="fa fa-angle-double-right" aria-hidden="true"></i>',
                        next: '<i title="{{ __('common.next') }}" class="fa fa-angle-right" aria-hidden="true"></i>',
                        previous: '<i title="{{ __('common.previous') }}" class="fa fa-angle-left" aria-hidden="true"></i>',
                    },
                    "info": "{{ __('common.dt_info') }}",
                    "infoEmpty": "{{ __('common.dt_infoEmpty') }}",
                    "infoFiltered": "{{ __('common.dt_infoFiltered') }}",
                },
                aLengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                buttons: [{
                    "extend": 'pageLength',
                    "text": '{{ __('common.show') }} 10 {{ __('common.records') }}'
                }],
            });

            table.on('length.dt', function(e, settings, len) {
                $('.buttons-page-length').find('span')
                    .text('{{ __('common.show') }} ' + len + ' {{ __('common.records') }}');
            });

            $(document).on('click', '#searchform', function() { table.draw(); });

            $(document).on('click', '#resetform', function() {
                $('#formsearch .single-select').val('').trigger('change');
                setTimeout(function() { table.draw(); }, 150);
            });
        });
    </script>
@endpush
