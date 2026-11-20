<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Language;
use App\Models\User;
use App\Support\UserPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The signed-in user's own Settings page: Profile, Appearance, Account and
 * Security, Privacy and Data, and App Preferences. Every action works on the
 * current user only.
 */
class SettingsPageController extends Controller
{
    public const SECTIONS = ['profile', 'appearance', 'security', 'privacy', 'preferences'];

    public function index(Request $request)
    {
        $user = Auth::user();
        $section = in_array($request->query('section'), self::SECTIONS, true) ? $request->query('section') : 'profile';

        return view('admin.settings.index', [
            'user' => $user,
            'section' => $section,
            'prefs' => UserPreferences::for($user),
            'languages' => Language::where('status', 1)->orderBy('sort_order')->get(),
            'timezones' => UserPreferences::timezones(),
            'sessions' => $this->sessionsFor($user),
            'isEmployee' => (bool) $user->employee_id,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $employee = Employee::where('login_id', $user->id)->first();

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:100',
            'email' => [
                'required', 'email', 'max:50',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('masters_employee', 'email')->ignore($employee?->id),
            ],
            'mobile' => ['required', 'regex:/^[0-9]{10,15}$/'],
            'language' => ['required', Rule::exists('template_language', 'short_name')->where('status', 1)],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'email.unique' => 'This email is already used by another user or employee',
            'mobile.regex' => 'Phone must be 10 to 15 digits',
        ]);
        if ($validator->fails()) {
            return $this->back('profile')->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($request, $user, $employee) {
                $user->update([
                    'name' => trim($request->name),
                    'first_name' => trim($request->name),
                    'email' => strtolower(trim($request->email)),
                    'mobile' => $request->mobile,
                    'language' => $request->language,
                    'timezone' => $request->timezone ?: null,
                    'updated_by' => $user->id,
                ]);

                // Keep the linked employee record in step so the two never disagree.
                if ($employee) {
                    $employee->update([
                        'emp_name' => $user->name,
                        'email' => $user->email,
                        'mobile_no' => $user->mobile,
                        'updated_by' => $user->id,
                    ]);
                }
            });

            session()->put('locale', $request->language);
            Session::flash('success', 'Profile updated');
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
        }

        return $this->back('profile');
    }

    public function updateAvatar(Request $request)
    {
        $user = Auth::user();

        if ($request->boolean('remove')) {
            $user->update(['profile_image' => null]);
            Session::flash('success', 'Profile photo removed');
            return $this->back('profile');
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'avatar.required' => 'Please choose a photo',
            'avatar.image' => 'The photo must be an image',
            'avatar.mimes' => 'The photo must be JPG, PNG or WEBP',
            'avatar.max' => 'The photo must be 2 MB or smaller',
        ]);
        if ($validator->fails()) {
            return $this->back('profile')->withErrors($validator);
        }

        try {
            $file = $request->file('avatar');
            $folder = public_path('uploads/profile');
            if (!File::exists($folder)) {
                File::makeDirectory($folder, 0755, true);
            }
            $fileName = time() . Str::random(10) . '.' . strtolower($file->getClientOriginalExtension());
            $file->move($folder, $fileName);

            $user->update(['profile_image' => $fileName]);
            Session::flash('success', 'Profile photo updated');
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
        }

        return $this->back('profile');
    }

    public function updateSignature(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'signature' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'signature.required' => 'Please choose a signature image',
            'signature.mimes' => 'The signature must be JPG or PNG',
            'signature.max' => 'The signature must be 2 MB or smaller',
        ]);
        if ($validator->fails()) {
            return $this->back('profile')->withErrors($validator);
        }

        try {
            $file = $request->file('signature');
            $folder = 'uploads/signatureupload';
            if (!File::exists(public_path($folder))) {
                File::makeDirectory(public_path($folder), 0755, true);
            }
            $fileName = time() . Str::random(6) . '.' . strtolower($file->getClientOriginalExtension());
            $file->move(public_path($folder), $fileName);

            $path = 'public/' . $folder . '/' . $fileName;
            $user->update(['signature_upload' => $path]);
            Employee::where('login_id', $user->id)->update(['signature_upload' => $path]);

            Session::flash('success', 'Signature updated');
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
        }

        return $this->back('profile');
    }

    public function updateAppearance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'theme' => ['required', Rule::in(array_keys(UserPreferences::THEMES))],
            'accent' => ['required', Rule::in(array_keys(UserPreferences::ACCENTS))],
            'font_size' => ['required', Rule::in(array_keys(UserPreferences::FONT_SIZES))],
            'density' => ['required', Rule::in(array_keys(UserPreferences::DENSITIES))],
            'reduced_motion' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->back('appearance')->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $user->theme = $request->theme;
        $user->preferences = array_merge($user->preferences ?? [], [
            'accent' => $request->accent,
            'font_size' => $request->font_size,
            'density' => $request->density,
            'reduced_motion' => $request->boolean('reduced_motion'),
        ]);
        $user->save();

        Session::flash('success', 'Appearance saved');
        return $this->back('appearance');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => ['required', 'confirmed', 'min:8', 'max:64', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'different:current_password'],
        ], [
            'current_password.required' => 'Please enter your current password',
            'password.required' => 'Please enter a new password',
            'password.confirmed' => 'The new passwords do not match',
            'password.min' => 'The new password must be at least 8 characters',
            'password.regex' => 'The new password must contain at least one letter and one number',
            'password.different' => 'The new password must be different from the current one',
        ]);
        $validator->after(function ($validator) use ($request, $user) {
            if ($request->filled('current_password') && !Hash::check($request->current_password, $user->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect');
            }
        });
        if ($validator->fails()) {
            return $this->back('security')->withErrors($validator);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        // A password change signs out every other device.
        $signedOut = $this->deleteOtherSessions($user, $request);

        Session::flash('success', 'Password changed' . ($signedOut ? " and {$signedOut} other session(s) signed out" : ''));
        return $this->back('security');
    }

    public function logoutSession(Request $request)
    {
        $user = Auth::user();
        $sessionId = decryptId((string) $request->input('session'));

        if ($sessionId && $sessionId !== $request->session()->getId()) {
            DB::table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete();
            Session::flash('success', 'That session has been signed out');
        }

        return $this->back('security');
    }

    public function logoutOtherSessions(Request $request)
    {
        $user = Auth::user();
        $signedOut = $this->deleteOtherSessions($user, $request);

        // Invalidate "remember me" cookies on other devices too.
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        Session::flash('success', $signedOut ? "Signed out of {$signedOut} other session(s)" : 'No other sessions were signed in');
        return $this->back('security');
    }

    public function downloadData()
    {
        $user = Auth::user();
        $employee = Employee::where('login_id', $user->id)->first();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => collect($user->toArray())
                ->except(['password', 'remember_token', 'otp', 'otp_token', 'permission'])
                ->all(),
            'employee' => $employee ? collect($employee->toArray())->except(['error_status', 'error_remarks'])->all() : null,
            'documents' => $employee
                ? $employee->documents()->with('documentType')->get()->map(fn ($doc) => [
                    'document_type' => $doc->documentType->document_name ?? null,
                    'file_name' => $doc->file_orgname,
                    'uploaded_at' => (string) $doc->created_at,
                ])->all()
                : [],
            'sessions' => $this->sessionsFor($user)->map(fn ($s) => [
                'device' => $s['device'],
                'ip_address' => $s['ip'],
                'last_active' => $s['last_active']->toIso8601String(),
            ])->all(),
        ];

        $fileName = 'my-data-' . Str::slug($user->username ?: $user->id) . '-' . now()->format('Ymd') . '.json';

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    public function updatePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page_size' => ['required', Rule::in(UserPreferences::PAGE_SIZES)],
        ]);
        if ($validator->fails()) {
            return $this->back('preferences')->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $user->preferences = array_merge($user->preferences ?? [], [
            'page_size' => (int) $request->page_size,
        ]);
        $user->save();

        Session::flash('success', 'Preferences saved');
        return $this->back('preferences');
    }

    /** The user's sessions from the database session store, current one first. */
    private function sessionsFor(User $user)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $currentId = session()->getId();
        $timezone = UserPreferences::for($user)['timezone'] ?: config('app.timezone');

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => [
                'key' => encryptId($row->id),
                'current' => $row->id === $currentId,
                'ip' => $row->ip_address,
                'device' => $this->describeAgent((string) $row->user_agent),
                'last_active' => \Carbon\Carbon::createFromTimestamp($row->last_activity)->setTimezone($timezone),
            ])
            ->sortByDesc('current')
            ->values();
    }

    private function deleteOtherSessions(User $user, Request $request): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /** "Chrome on Windows" style label from a user agent string. */
    private function describeAgent(string $agent): string
    {
        if ($agent === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }

    private function back(string $section)
    {
        return redirect(admin_url('settings') . '?section=' . $section);
    }
}
