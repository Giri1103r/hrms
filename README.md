# Human Resource Management System

## Leave Management

Leave Type and Leave Status masters, plus **Apply Leave**, which handles monthly credit, approval by the reporting manager and a yearly reset.

### Setup

```bash
php artisan migrate
php artisan db:seed --class=LeaveMasterPermissionSeeder   # Admin: Leave Type / Leave Status masters
php artisan db:seed --class=LeavePermissionSeeder         # Admin, User, Manager: Apply Leave menu
```

The migrations add `monthly_credit` to `masters_leave_type` and create the `leave_request` and `leave_request_log` tables.

> `LeavePermissionSeeder` only grants access to an existing menu. It does nothing if `template_left_menu` has no row with link `leave-management/leave/list`.

### Leave balance

- Each Leave Type has a **Monthly Credit** setting, which defaults to 1 day.
- A month's credit can be used from the first day of that month.
- Employees who join mid-year start earning credit from their joining month.
- Unused leave carries over from month to month until 31 Dec. The balance resets on 1 Jan.
- A **Pending** leave holds its days against the balance. A **Cancelled** leave gives them back.
- Any days beyond the available balance are saved as **Loss of Pay (LOP)**.

### Applying for leave

| Duration | Fields |
|---|---|
| One Day | Date |
| Half Day | Date + First Half / Second Half |
| More Than One Day | From date, To date |

- Sundays and active holidays are not counted.
- These requests are blocked:
  - single-day leave on a Sunday or holiday
  - leave that overlaps an existing leave
  - leave dated before the employee's joining date
  - leave that spans two calendar years
- The form shows a live summary of the balance and of paid vs LOP days.
- The list page shows the employee's balance for each leave type.

### Approval workflow

1. An employee applies. The request is **Pending** and goes to their reporting manager.
2. The manager **Confirms** or **Cancels** it. Each step sends a notification and is written to the request log.
3. Leave entered by an **Admin** or by the employee's **own manager** is confirmed directly, with no approval step.

Statuses: `1` Pending · `2` Confirmed · `3` Cancelled

### Routes (`leave-management/`)

| Route | Purpose |
|---|---|
| `master/leave-type/*` | Leave Type master (CRUD, export, status) |
| `master/leave-status/*` | Leave Status master (CRUD, export, status) |
| `leave/list` | Leave list with balances |
| `leave/add`, `leave/edit/{id}` | Apply for or edit a leave |
| `leave/view/{id}` | Leave details and history |
| `leave/approval/{id}` | Manager confirms or cancels |
| `leave/calculate` | AJAX: balance and paid vs LOP preview |

### Tables

- **`leave_request`**: one row per application. Holds `duration_type`, `from_date`/`to_date`, `leave_year`, `total_days = paid_days + lop_days`, `leave_status`, `is_direct` and `approver_user_id`.
- **`leave_request_log`**: every action on a request (`applied`, `updated`, `confirmed`, `cancelled`, `direct_confirmed`).

The main logic is in `app/Models/Leave/LeaveBalance.php` and `app/Models/Leave/LeaveRequest.php`.

