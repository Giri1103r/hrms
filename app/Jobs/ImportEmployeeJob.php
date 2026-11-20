<?php

namespace App\Jobs;

use Exception;
use Carbon\Carbon;
use Shuchkin\SimpleXLSX;
use Illuminate\Bus\Queueable;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models\User;
use App\Rules\IdNumber;
use App\Models\Master\Shift;
use App\Models\Master\Company;
use App\Models\Master\Location;
use App\Models\Master\Bloodgroup;
use App\Models\Master\Department;
use App\Models\Employee\Employee;
use App\Models\Administration\UploadLog;
use App\Models\Administration\UploadLogError;

class ImportEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Header row of the sample file, in order. */
    public const HEADERS = [
        'SNo',
        'Employee ID',
        'Employee Name',
        'Gender',
        'Email',
        'Mobile No',
        'Joining Date (DD-MM-YYYY)',
        'Nationality',
        'ID Type',
        'ID Number',
        'Blood Group',
        'Company Name',
        'Location Name',
        'Shift Name',
        'Department Name',
        'Designation',
        'Employee Type',
        'Employee Status',
        'Reporting Manager ID',
        'User Roles',
    ];

    protected $details;

    public function __construct($details)
    {
        $this->details = $details;
    }

    public function handle()
    {
        UploadLog::where('id', $this->details['log_id'])->update(['upload_status' => 1]);

        $errors = [];
        $xlsx = SimpleXLSX::parse($this->details['path']);

        if (!$xlsx) {
            $errors[] = $this->error(0, 'The file could not be read as an Excel (.xlsx) file');
            return $this->finish($errors);
        }

        $roleIdsByName = DB::table('template_user_role')->where('status', 1)
            ->pluck('id', 'role_name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id]);
        $defaultRoleId = $roleIdsByName['user'] ?? null;

        $seenEmpIds = [];
        $seenEmails = [];
        $seenIdNumbers = [];
        $line = 0;

        foreach ($xlsx->rows() as $row) {
            $line++;
            $row = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $row);

            if ($line === 1) {
                $header = array_slice($row, 0, count(self::HEADERS));
                if ($header !== self::HEADERS) {
                    $errors[] = $this->error($line, 'Header columns do not match the sample file. Download the sample and keep its first row unchanged.');
                    break;
                }
                continue;
            }

            $values = array_slice(array_pad($row, count(self::HEADERS), ''), 1, count(self::HEADERS) - 1);
            if (implode('', array_map('strval', $values)) === '') {
                continue; // blank line
            }

            [$empId, $empName, $gender, $email, $mobile, $joining, $nationality, $idType, $idNumber,
                $bloodGroup, $companyName, $locationName, $shiftName, $departmentName, $designation,
                $employeeType, $employeeStatus, $managerId, $roleNames] = array_map('strval', $values);

            $email = strtolower($email);
            $idNumber = $idNumber !== '' ? IdNumber::normalize($idNumber) : '';
            $idKey = $idType . '|' . $idNumber;
            $problems = [];

            if ($idNumber !== '' && isset($seenIdNumbers[$idKey])) {
                $problems[] = "{$idType} number is repeated in this file (line {$seenIdNumbers[$idKey]})";
            }

            if ($empId !== '' && isset($seenEmpIds[strtolower($empId)])) {
                $problems[] = "Employee ID {$empId} is repeated in this file (line {$seenEmpIds[strtolower($empId)]})";
            }
            if ($email !== '' && isset($seenEmails[$email])) {
                $problems[] = "Email {$email} is repeated in this file (line {$seenEmails[$email]})";
            }

            $joiningDate = $this->parseDate($values[5]);
            if ($joining !== '' && $joiningDate === null) {
                $problems[] = 'Joining Date must be in DD-MM-YYYY format';
            } elseif ($joiningDate !== null && $joiningDate >= now()->toDateString()) {
                $problems[] = 'Joining Date must be a past date (before today)';
            }

            /* Resolve master names to ids, following company > location > department; shift is on its own. */
            $company = $companyName !== '' ? Company::where('company_name', $companyName)->where('status', 1)->first() : null;
            $location = ($company && $locationName !== '')
                ? Location::where('location_name', $locationName)->where('company_id', $company->id)->where('status', 1)->first() : null;
            $department = ($location && $departmentName !== '')
                ? Department::where('department_name', $departmentName)->where('location_id', $location->id)->where('status', 1)->first() : null;
            $shift = $shiftName !== '' ? Shift::where('shift_name', $shiftName)->where('status', 1)->first() : null;

            if ($companyName !== '' && !$company) {
                $problems[] = "Company '{$companyName}' not found";
            }
            if ($company && $locationName !== '' && !$location) {
                $problems[] = "Location '{$locationName}' not found under company '{$companyName}'";
            }
            if ($location && $departmentName !== '' && !$department) {
                $problems[] = "Department '{$departmentName}' not found under location '{$locationName}'";
            }
            if ($shiftName !== '' && !$shift) {
                $problems[] = "Shift '{$shiftName}' not found";
            }

            $bloodGroupId = null;
            if ($bloodGroup !== '') {
                $bloodGroupId = Bloodgroup::where('blood_group_name', $bloodGroup)->value('id');
                if (!$bloodGroupId) {
                    $problems[] = "Blood Group '{$bloodGroup}' not found";
                }
            }

            $roleIds = [];
            foreach (array_filter(array_map('trim', explode(',', $roleNames))) as $roleName) {
                if (!isset($roleIdsByName[strtolower($roleName)])) {
                    $problems[] = "User Role '{$roleName}' not found";
                    continue;
                }
                $roleIds[] = $roleIdsByName[strtolower($roleName)];
            }
            if ($roleNames === '' && $defaultRoleId) {
                $roleIds[] = $defaultRoleId;
            }

            $validator = Validator::make([
                'emp_id' => $empId,
                'emp_name' => $empName,
                'gender' => $gender,
                'email' => $email,
                'mobile_no' => $mobile,
                'joining_date' => $joining,
                'nationality' => $nationality,
                'id_type' => $idType,
                'id_number' => $idNumber,
                'company' => $companyName,
                'location' => $locationName,
                'shift' => $shiftName,
                'designation' => $designation,
                'employee_type' => $employeeType,
                'employee_status' => $employeeStatus,
                'reporting_manager' => $managerId,
                'user_role' => $roleIds,
            ], [
                'emp_id' => ['required', 'max:50', 'regex:/^[A-Za-z0-9_\-\/]+$/', Rule::unique('masters_employee', 'emp_id'), Rule::unique('users', 'employee_id')],
                'emp_name' => 'required|max:100',
                'gender' => 'required|in:Male,Female,Other',
                'email' => ['required', 'email', 'max:50', Rule::unique('masters_employee', 'email'), Rule::unique('users', 'email')],
                'mobile_no' => ['required', 'regex:/^[0-9]{10,15}$/'],
                'joining_date' => 'required',
                'nationality' => 'nullable|max:50',
                'id_type' => ['nullable', 'required_with:id_number', Rule::in(array_keys(IdNumber::TYPES))],
                'id_number' => [
                    'nullable',
                    'required_with:id_type',
                    'max:100',
                    new IdNumber($idType),
                    Rule::unique('masters_employee', 'id_number')->where('id_type', $idType),
                ],
                'company' => 'required',
                'location' => 'required',
                'shift' => 'required',
                'designation' => 'required|exists:masters_designation,designation_name',
                'employee_type' => 'required|exists:masters_employee_type,employee_type_name',
                'employee_status' => 'required|exists:masters_employee_status,employee_status_name',
                'reporting_manager' => 'nullable|exists:masters_employee,emp_id',
                'user_role' => 'required|array|min:1',
            ], [
                'emp_id.unique' => 'Employee ID :input already exists',
                'emp_id.regex' => 'Employee ID can only contain letters, numbers, -, _ and /',
                'email.unique' => 'Email :input is already used by another employee or user',
                'mobile_no.regex' => 'Mobile No must be 10 to 15 digits',
                'email.email' => 'Email :input is not a valid email address',
                'id_number.required_with' => 'ID Number is required when ID Type is given',
                'id_type.required_with' => 'ID Type is required when ID Number is given',
                'id_type.in' => "ID Type ':input' is not one of: " . implode(', ', array_keys(IdNumber::TYPES)),
                'id_number.unique' => 'ID Number :input is already registered to another employee',
                'designation.exists' => "Designation ':input' not found",
                'employee_type.exists' => "Employee Type ':input' not found",
                'employee_status.exists' => "Employee Status ':input' not found",
                'reporting_manager.exists' => "Reporting Manager ID ':input' not found",
                'user_role.required' => 'At least one User Role is required',
            ]);

            $problems = array_merge($problems, $validator->errors()->all());

            if ($problems) {
                $errors[] = $this->error($line, implode('; ', array_unique($problems)));
                continue;
            }

            try {
                DB::transaction(function () use (
                    $empId, $empName, $gender, $email, $mobile, $joiningDate, $nationality, $idType, $idNumber,
                    $bloodGroupId, $company, $location, $shift, $department, $designation, $employeeType,
                    $employeeStatus, $managerId, $roleIds
                ) {
                    $employeeModel = new Employee();
                    $employee = $employeeModel->storeEmployee([
                        'emp_id' => $empId,
                        'emp_name' => $empName,
                        'gender' => $gender,
                        'nationality' => $nationality ?: null,
                        'id_type' => $idType ?: null,
                        'id_number' => $idNumber ?: null,
                        'blood_group' => $bloodGroupId,
                        'email' => $email,
                        'mobile_no' => $mobile,
                        'joining_date' => $joiningDate,
                        'company' => $company->id,
                        'location' => $location->id,
                        'shift' => $shift->id,
                        'department' => $department?->id,
                        'designation' => $designation,
                        'employee_type' => $employeeType,
                        'employee_status' => $employeeStatus,
                        'reporting_manager' => $managerId ?: null,
                        'user_role' => implode(',', $roleIds),
                    ], $this->details['user_id']);

                    $loginId = (new User())->storeForEmployee($employee, $this->details['user_id']);
                    $employeeModel->updateUserId($employee->emp_id, $loginId);
                });
            } catch (Exception $ex) {
                report($ex);
                $errors[] = $this->error($line, 'Could not be saved: ' . $ex->getMessage());
                continue;
            }

            $seenEmpIds[strtolower($empId)] = $line;
            $seenEmails[$email] = $line;
            if ($idNumber !== '') {
                $seenIdNumbers[$idKey] = $line;
            }
        }

        return $this->finish($errors);
    }

    /**
     * SimpleXLSX returns date-formatted cells as "Y-m-d H:i:s"; typed text is
     * accepted as DD-MM-YYYY (or DD/MM/YYYY). Returns Y-m-d or null.
     */
    private function parseDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Exception $ex) {
                // try the next format
            }
        }
        return null;
    }

    private function error(int $line, string $message): array
    {
        return [
            'upload_id' => $this->details['log_id'],
            'line_no' => $line,
            'error' => mb_substr($message, 0, 1000),
        ];
    }

    private function finish(array $errors)
    {
        if ($errors) {
            UploadLogError::insert($errors);
        }

        UploadLog::where('id', $this->details['log_id'])->update([
            'upload_status' => $errors ? 3 : 2,
        ]);
    }
}
