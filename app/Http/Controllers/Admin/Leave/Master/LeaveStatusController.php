<?php

namespace App\Http\Controllers\Admin\Leave\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Leave\Master\LeaveStatus;
use App\Models\User;
use App\Models\Administration\UploadLog;


class LeaveStatusController extends Controller
{


    private $user;
    private $uploadlog;

    private $leavestatus;


    public function __construct()
    {


        $this->user = new User();
        $this->leavestatus = new LeaveStatus();

        $this->uploadlog = new UploadLog();
    }


    public function index(Request $request)
    {
        if (Auth::check()) {
            if ($request->ajax()) {

                try {

                    $data =  $this->leavestatus->list();

                    $datatables = Datatables::of($data['data'])
                        ->addIndexColumn()
                        ->addColumn('status', function ($row) {
                            $text = "<span style='color:red'>In-Active<span>";
                            if ($row->status == 1) {
                                $text = "<span style='color:green;cursor:pointer' class= 'statusChange' data-id='" . encryptId($row->id) . "' data-type = '1' >Active<span>";
                            } else if ($row->status == 0) {
                                $text = "<span style='color:red;cursor:pointer' class= 'statusChange' data-id='" . encryptId($row->id) . "' data-type = '0' >In-Active<span>";
                            }
                            return $text;
                        })
                        ->addColumn('created_at', function ($row) {
                            return Displaydatetimeformat($row->created_at);
                        })
                        ->addColumn('created_by', function ($row) {
                            return getUsername($row->created_by);
                        })
                        ->addColumn('action', function ($row) {
                            $btn = '';
                            if (CheckUserPermission('view')) {
                                $btn = '<a href="' . admin_url('leave-management/master/leave-status/view/' . encryptId($row->id)) . '"   class="" title="View"><i class="fa-solid fa-eye"></i></a> ';
                            }
                            if (CheckUserPermission('edit')) {
                                $btn .= '<a href="' . admin_url('leave-management/master/leave-status/edit/' . encryptId($row->id)) . '" class=" " title="Edit"><i class="fa-solid fa-pen-to-square"></i> ';
                            }
                            return $btn;
                        })
                        ->rawColumns(['action', 'created_date', 'created_by', 'status'])
                        ->setFilteredRecords($data['filter_records'])
                        ->setTotalRecords($data['total_records'])
                        ->skipPaging()
                        ->make(true);
                    return $datatables;
                } catch (Exception $ex) {
                    report($ex);
                    return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
                }
            }
        }
        $data = array();
        return view('admin.leave.master.leave_status.list', $data);
    }

    public function Add(Request $request)
    {

        try {

            $data = array();
            return view('admin.leave.master.leave_status.add', $data);
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function Store(Request $request)
    {
        try {
            DB::beginTransaction();

            $rules = [
                'leave_status_name' => 'required|unique:masters_leave_status,leave_status_name',
            ];
            $messages = [
                'leave_status_name.required' => 'Please Enter Leave Status Name',
                'leave_status_name.unique' => 'Leave Status Name should be unique',
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }


            $this->leavestatus->store();
            DB::commit();

            Session::flash('success', 'Your data has been created successfully!');
            return redirect(admin_url('leave-management/master/leave-status/list'));
        } catch (Exception $ex) {

            report($ex);
            DB::rollBack();
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function View(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $leavestatus = $this->leavestatus->selectOne($id);

            if (!$leavestatus) {
                Session::flash('error', __('common.error_msg'));
                return redirect(admin_url('leave-management/master/leave-status/list'));
            }

            $data = array(
                'leavestatus' => $leavestatus,
            );

            return view('admin.leave.master.leave_status.view', $data);
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function Edit(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $leavestatus = $this->leavestatus->find($id);

            if (!$leavestatus) {
                Session::flash('error', __('common.error_msg'));
                return redirect(admin_url('leave-management/master/leave-status/list'));
            }

            $data = array(
                'leavestatus' => $leavestatus,
            );

            return view('admin.leave.master.leave_status.edit', $data);
        } catch (Exception $error) {
            report($error);

            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function Update(Request $request)
    {
        try {
            DB::beginTransaction();

            $id = decryptId($request->id);
            $rules = [
                'leave_status_name' => 'required|unique:masters_leave_status,leave_status_name,' . $id . ',id',
            ];
            $messages = [
                'leave_status_name.required' => 'Please Enter Leave Status Name',
                'leave_status_name.unique' => 'Leave Status Name should be unique',
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $this->leavestatus->updates($id);
            DB::commit();

            Session::flash('success', __('common.updated_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        } catch (Exception $ex) {
            report($ex);
            DB::rollBack();
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function StatusChange(Request $request)
    {

        try {
            $id = decryptId($request->id);

            $this->leavestatus->statuschange($id);

            return response()->json(['status' => 'success', 'msg' => 'Leave Status status changed'], 200);
        } catch (Exception $ex) {
            report($ex);
            return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
        }
    }

    public function Delete(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $this->leavestatus->deleterecord($id);

            return response()->json(['status' => 'success', 'msg' => 'Leave Status deleted successfully'], 200);
        } catch (Exception $ex) {
            report($ex);
            return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
        }
    }


    public function ExportExcel(Request $request)
    {

        try {

            $allData = $this->leavestatus->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Leave Status ID',
                'Leave Status Name',
                __("common.status"),
                __("common.created_by"),
                __("common.created_date"),
            ];

            $exportData = [];
            $i = 1;
            foreach ($allData as $data) {

                $export = [];
                $export[] =  $i;
                $export[] =  $data->ls_code;
                $export[] =  $data->leave_status_name;
                $export[] =  $data->status == 1 ? 'Active' : 'In-Active';
                $export[] =  getusername($data->created_by);
                $export[] =  Displaydateformat($data->created_at);

                $exportData[] = $export;

                $i++;
            }

            $writer = SimpleExcelWriter::streamDownload('Leave Status Master.xlsx')
                ->addHeader($header)
                ->addRows(
                    $exportData
                );
        } catch (Exception $ex) {

            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function ExportPdf(Request $request)
    {

        try {

            $allData = $this->leavestatus->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Leave Status ID',
                'Leave Status Name',
                __("common.status"),
                __("common.created_by"),
                __("common.created_date"),
            ];

            $data = array(
                'header' => $header,
                'content' => $allData,
                'pagetitle' => "Leave Status Details",
            );

            $property = [
                'tempDir' => public_path('pdf/temp'),
                'mode' => 'c',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,

            ];

            $mpdf = new \Mpdf\Mpdf($property);
            $mpdf->setAutoTopMargin = 'stretch';

            $view = view('admin.leave.master.leave_status.pdf', $data);
            $html = $view->render();

            $mpdf->WriteHTML($html);

            $filename = "Leave Status Master.pdf";

            /* Hand the PDF back as a response instead of echoing it: mPDF sends
               its own Content-Length and then returns, so letting the action fall
               through made the framework append a second body to the download. */
            return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $ex) {

            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-status/list'));
        }
    }

    public function Uniquecheck(Request $request)
    {
        if ($request->ajax()) {

            $leave_status_name = $request->leave_status_name;
            $id = $request->id;

            if ($id == '') {

                $record = $this->leavestatus->uniqueCheck($leave_status_name);
            } else {

                $id = decryptId($id);
                $record = $this->leavestatus->ExistuniqueCheck($leave_status_name, $id);
            }
            if ($record->count()) {
                return Response::json(false);
            }
            return Response::json(true);
        }
    }

    public function list(Request $request)
    {
        $id = decryptId($request->id);
        $leavestatuss = $this->leavestatus->ajaxList($id);

        return response()->json($leavestatuss);
    }
}
