@extends('admin.layouts.admin')
@section('title', 'Apply Leave')
@section('pageurl', admin_url('leave-management/leave/list'))

@php use App\Models\Leave\LeaveRequest; @endphp

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                @if ($hasEmployee)
                    <div class="card">
                        <div class="card-body pb-1">
                            <h5 class="mb-1">My Leave Balance &mdash; {{ date('Y') }}</h5>
                            <p class="text-muted small mb-3">
                                Leave is credited at the start of every month. Unused leave can be used until 31 December and resets on 1 January.
                                Pending leave is already held from the balance; days beyond the balance are Loss of Pay.
                            </p>
                            <div class="row">
                                @forelse ($balances as $b)
                                    <div class="col-md-6 col-xl-4 mb-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">{{ $b['leave_type_name'] }}</h6>
                                                <span class="badge bg-primary">{{ LeaveRequest::formatDays($b['monthly_credit']) }} / month</span>
                                            </div>
                                            <div class="fs-3 fw-semibold">{{ LeaveRequest::formatDays($b['available']) }}
                                                <small class="fs-6 fw-normal text-muted">day(s) available</small>
                                            </div>
                                            <div class="small text-muted">
                                                Credited {{ LeaveRequest::formatDays($b['credited']) }}
                                                &middot; Used {{ LeaveRequest::formatDays($b['used']) }}
                                                &middot; Pending {{ LeaveRequest::formatDays($b['pending']) }}
                                                @if ($b['lop'] > 0)
                                                    &middot; <span class="text-danger">LOP {{ LeaveRequest::formatDays($b['lop']) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 mb-3 text-muted">No active leave types. Add one under Leave Management &rarr; Master &rarr; Leave Type.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <h4 class="card-title"></h4>
                    <div class="d-flex flex-wrap justify-content-end gap-2 p-2">
                        <x-button-filter dataId="" class="search me-1" href=""></x-button-filter>
                        <x-button-add dataId="" class="add btn btn-primary ms-1"
                            href="{{ admin_url('leave-management/leave/add') }}">{{ __('common.add') }}</x-button-add>
                    </div>
                    <div id="search" class="collapse">
                        <form action="" id="formsearch">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 mb-3 form-input">
                                        <label for="leave_status" class="form-label">Status</label>
                                        <select name="leave_status" id="leave_status" style="width: 100%" class="form-control single-select">
                                            <option value="">Select Status</option>
                                            @foreach ($statusList as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3 form-input">
                                        <label for="leave_type_id" class="form-label">Leave Type</label>
                                        <select name="leave_type_id" id="leave_type_id" style="width: 100%" class="form-control single-select">
                                            <option value="">Select Leave Type</option>
                                            @foreach ($leaveTypeList as $type)
                                                <option value="{{ encryptId($type->id) }}">{{ $type->leave_type_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3 form-input">
                                        <label for="leave_year" class="form-label">Year</label>
                                        <select name="leave_year" id="leave_year" style="width: 100%" class="form-control single-select">
                                            <option value="">All Years</option>
                                            @foreach ($yearList as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3 form-input">
                                        <label for="mine" class="form-label">Show</label>
                                        <select name="mine" id="mine" style="width: 100%" class="form-control single-select">
                                            <option value="">All Leave</option>
                                            <option value="approval">Waiting for My Approval</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mt-3">
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
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Days</th>
                                        <th>LOP</th>
                                        <th>Status</th>
                                        <th>Approver</th>
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
                    url: "{{ admin_url('leave-management/leave/list') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: function(d) {
                        d.leave_status = $('#leave_status').val();
                        d.leave_type_id = $('#leave_type_id').val();
                        d.leave_year = $('#leave_year').val();
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
                    { data: 'leave_type_name', name: 'leave_type_name' },
                    { data: 'duration', name: 'duration' },
                    { data: 'from_date', name: 'from_date' },
                    { data: 'to_date', name: 'to_date' },
                    { data: 'total_days', name: 'total_days' },
                    { data: 'lop_days', name: 'lop_days' },
                    { data: 'leave_status', name: 'leave_status' },
                    { data: 'approver', name: 'approver' },
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
