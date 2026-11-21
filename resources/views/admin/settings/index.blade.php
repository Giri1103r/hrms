@extends('admin.layouts.admin')
@section('title', 'Settings')
@section('pageurl', admin_url('administration/settings/list'))

@push('style')
    <style>
        .settings-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--ct-body-color, #4b5563);
            border-radius: 8px;
            padding: 9px 12px;
            margin-bottom: 2px;
            font-weight: 500;
        }

        .settings-nav .nav-link i {
            width: 18px;
            text-align: center;
            opacity: .75;
        }

        .settings-nav .nav-link.active {
            background: var(--brand-soft);
            color: var(--brand-text);
        }

        .settings-nav .nav-link.active i {
            opacity: 1;
        }

        .settings-section + .settings-section {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--ct-border-color, #e5e7eb);
        }

        .settings-section h5 {
            margin: 0 0 .25rem;
        }

        .settings-section .section-hint {
            color: #6b7280;
            font-size: .85rem;
            margin-bottom: 1rem;
        }

        .settings-avatar {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand-soft-strong);
        }

        .settings-signature {
            max-height: 60px;
            max-width: 220px;
            border: 1px dashed var(--ct-border-color, #d1d5db);
            border-radius: 6px;
            padding: 4px;
            background: #fff;
        }

        /* Option cards: a radio input styled as a selectable tile. */
        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }

        .option-tile {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 12px 8px;
            border: 1px solid var(--ct-border-color, #e5e7eb);
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            font-size: .875rem;
            transition: border-color .15s, box-shadow .15s;
        }

        .option-tile input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .option-tile:has(input:checked) {
            border-color: var(--brand-orange);
            box-shadow: 0 0 0 2px rgba(var(--brand-orange-rgb), .25);
        }

        .option-tile:has(input:focus-visible) {
            outline: 2px solid var(--brand-orange);
            outline-offset: 2px;
        }

        .theme-swatch {
            width: 56px;
            height: 36px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            overflow: hidden;
            display: flex;
        }

        .theme-swatch span {
            flex: 1;
        }

        .accent-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
        }

        .session-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--ct-border-color, #f0f0f0);
        }

        .session-row:last-child {
            border-bottom: 0;
        }

        .session-row .session-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--brand-soft);
            color: var(--brand-text);
            display: flex;
            align-items: center;
            justify-content: center;
            flex: none;
        }

        .session-row .session-meta {
            flex: 1;
            min-width: 0;
        }

        .session-row .session-meta small {
            color: #6b7280;
        }
    </style>
@endpush

@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                {{-- Section menu --}}
                <div class="col-lg-3 mb-3">
                    <div class="card mb-0">
                        <div class="card-body p-2">
                            <nav class="nav flex-column settings-nav" role="tablist">
                                @php
                                    $menu = [
                                        'profile' => ['fa-solid fa-user', 'Profile'],
                                        'appearance' => ['fa-solid fa-palette', 'Appearance'],
                                        'security' => ['fa-solid fa-shield-halved', 'Account and Security'],
                                        'privacy' => ['fa-solid fa-user-lock', 'Privacy and Data'],
                                        'preferences' => ['fa-solid fa-sliders', 'App Preferences'],
                                    ];
                                @endphp
                                @foreach ($menu as $key => [$icon, $label])
                                    <a class="nav-link {{ $section === $key ? 'active' : '' }}" data-bs-toggle="pill"
                                        href="#pane-{{ $key }}" data-section="{{ $key }}" role="tab"
                                        aria-selected="{{ $section === $key ? 'true' : 'false' }}">
                                        <i class="{{ $icon }}"></i> {{ $label }}
                                    </a>
                                @endforeach
                            </nav>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">

                                {{-- ================= Profile ================= --}}
                                <div class="tab-pane fade {{ $section === 'profile' ? 'show active' : '' }}" id="pane-profile" role="tabpanel">
                                    <div class="settings-section">
                                        <h5>Photo</h5>
                                        <p class="section-hint">JPG, PNG or WEBP, up to 2 MB.</p>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <img src="{{ url(profileImage($user->id)) }}" alt="Profile photo" class="settings-avatar" id="avatarPreview">
                                            <form method="POST" action="{{ admin_url('administration/settings/avatar') }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                                                @csrf
                                                <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm" style="max-width: 240px;" accept=".jpg,.jpeg,.png,.webp">
                                                <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                                            </form>
                                            @if ($user->profile_image)
                                                <form method="POST" action="{{ admin_url('administration/settings/avatar') }}">
                                                    @csrf
                                                    <input type="hidden" name="remove" value="1">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                        @error('avatar') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="settings-section">
                                        <h5>Personal details</h5>
                                        <p class="section-hint">
                                            @if ($isEmployee)
                                                Changes here also update your employee record.
                                            @else
                                                Your name and contact details.
                                            @endif
                                        </p>
                                        <form method="POST" action="{{ admin_url('administration/settings/profile') }}" id="profileForm">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label require" for="name">Name</label>
                                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                                                        value="{{ old('name', $user->name) }}" maxlength="100">
                                                    @error('name') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label" for="username">Username</label>
                                                    <input type="text" id="username" class="form-control" value="{{ $user->username }}" readonly
                                                        title="Your username is used to sign in and cannot be changed here">
                                                </div>
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label require" for="email">Email</label>
                                                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                                                        value="{{ old('email', $user->email) }}" maxlength="50">
                                                    @error('email') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label require" for="mobile">Phone</label>
                                                    <input type="text" name="mobile" id="mobile" class="form-control @error('mobile') is-invalid @enderror"
                                                        value="{{ old('mobile', $user->mobile) }}" maxlength="15" inputmode="numeric">
                                                    @error('mobile') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label require" for="language">Language</label>
                                                    <select name="language" id="language" class="form-control single-select" style="width: 100%;">
                                                        @foreach ($languages as $language)
                                                            <option value="{{ $language->short_name }}"
                                                                @selected(old('language', $user->language ?: app()->getLocale()) === $language->short_name)>
                                                                {{ $language->english }}@if ($language->native && $language->native !== $language->english) — {{ $language->native }}@endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <small class="text-muted">Only English is fully translated so far; other languages translate the menu.</small>
                                                    @error('language') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-6 mb-3 form-input">
                                                    <label class="form-label" for="timezone">Time zone</label>
                                                    <select name="timezone" id="timezone" class="form-control single-select" style="width: 100%;">
                                                        <option value="">Server default ({{ config('app.timezone') }})</option>
                                                        @foreach ($timezones as $region => $zones)
                                                            <optgroup label="{{ $region }}">
                                                                @foreach ($zones as $zone => $label)
                                                                    <option value="{{ $zone }}" @selected(old('timezone', $user->timezone) === $zone)>{{ $label }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endforeach
                                                    </select>
                                                    <small class="text-muted">Used for the session times on this page.</small>
                                                    @error('timezone') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <button type="submit" class="btn btn-primary">Save profile</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="settings-section">
                                        <h5>Signature</h5>
                                        <p class="section-hint">Used on approvals and printed documents. JPG or PNG, up to 2 MB.</p>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            @if ($user->signature_upload)
                                                <img src="{{ url($user->signature_upload) }}" alt="Signature" class="settings-signature">
                                            @else
                                                <span class="text-muted small">No signature uploaded.</span>
                                            @endif
                                            <form method="POST" action="{{ admin_url('administration/settings/signature') }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                                                @csrf
                                                <input type="file" name="signature" class="form-control form-control-sm" style="max-width: 240px;" accept=".jpg,.jpeg,.png">
                                                <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                                            </form>
                                        </div>
                                        @error('signature') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- ================= Appearance ================= --}}
                                <div class="tab-pane fade {{ $section === 'appearance' ? 'show active' : '' }}" id="pane-appearance" role="tabpanel">
                                    <form method="POST" action="{{ admin_url('administration/settings/appearance') }}" id="appearanceForm">
                                        @csrf
                                        <div class="settings-section">
                                            <h5>Theme</h5>
                                            <p class="section-hint">System follows your device's light or dark setting.</p>
                                            <div class="option-grid">
                                                @foreach (\App\Support\UserPreferences::THEMES as $key => $label)
                                                    <label class="option-tile">
                                                        <input type="radio" name="theme" value="{{ $key }}" @checked(old('theme', $prefs['theme']) === $key)>
                                                        <span class="theme-swatch">
                                                            @if ($key === 'light')
                                                                <span style="background:#f6f3ee"></span><span style="background:#fff"></span>
                                                            @elseif ($key === 'dark')
                                                                <span style="background:#141414"></span><span style="background:#2a2a2a"></span>
                                                            @else
                                                                <span style="background:#f6f3ee"></span><span style="background:#141414"></span>
                                                            @endif
                                                        </span>
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="settings-section">
                                            <h5>Accent color</h5>
                                            <p class="section-hint">Buttons, links, highlights and the top bar.</p>
                                            <div class="option-grid">
                                                @foreach (\App\Support\UserPreferences::ACCENTS as $key => $accent)
                                                    <label class="option-tile">
                                                        <input type="radio" name="accent" value="{{ $key }}" data-css="{{ \App\Support\UserPreferences::accentCss($key) }}"
                                                            @checked(old('accent', $prefs['accent']) === $key)>
                                                        <span class="accent-dot" style="background: linear-gradient(135deg, {{ $accent['main'] }}, {{ $accent['end'] }});"></span>
                                                        {{ $accent['label'] }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="settings-section">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <h5>Font size</h5>
                                                    <p class="section-hint">Scales all text and spacing.</p>
                                                    <div class="btn-group w-100" role="group" aria-label="Font size">
                                                        @foreach (\App\Support\UserPreferences::FONT_SIZES as $key => $size)
                                                            <input type="radio" class="btn-check" name="font_size" id="font_{{ $key }}" value="{{ $key }}"
                                                                data-root="{{ $size['root'] }}" @checked(old('font_size', $prefs['font_size']) === $key)>
                                                            <label class="btn btn-outline-primary" for="font_{{ $key }}">{{ $size['label'] }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <h5>Density</h5>
                                                    <p class="section-hint">Compact fits more rows on screen.</p>
                                                    <div class="btn-group w-100" role="group" aria-label="Density">
                                                        @foreach (\App\Support\UserPreferences::DENSITIES as $key => $label)
                                                            <input type="radio" class="btn-check" name="density" id="density_{{ $key }}" value="{{ $key }}"
                                                                @checked(old('density', $prefs['density']) === $key)>
                                                            <label class="btn btn-outline-primary" for="density_{{ $key }}">{{ $label }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="settings-section">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="reduced_motion" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" name="reduced_motion" id="reduced_motion" value="1"
                                                    @checked(old('reduced_motion', $prefs['reduced_motion']))>
                                                <label class="form-check-label fw-semibold" for="reduced_motion">Reduce motion</label>
                                            </div>
                                            <p class="section-hint mb-0 ms-4 ps-2">Turns off animations and transitions.</p>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <small class="text-muted">Changes preview as you pick; save to keep them.</small>
                                            <button type="submit" class="btn btn-primary">Save appearance</button>
                                        </div>
                                    </form>
                                </div>

                                {{-- ================= Account and Security ================= --}}
                                <div class="tab-pane fade {{ $section === 'security' ? 'show active' : '' }}" id="pane-security" role="tabpanel">
                                    <div class="settings-section">
                                        <h5>Change password</h5>
                                        <p class="section-hint">
                                            At least 8 characters with a letter and a number. Changing it signs you out everywhere else.
                                            @if ($user->password_changed_at)
                                                Last changed {{ \Carbon\Carbon::parse($user->password_changed_at)->diffForHumans() }}.
                                            @endif
                                        </p>
                                        <form method="POST" action="{{ admin_url('administration/settings/password') }}" id="passwordForm" autocomplete="off">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-4 mb-3 form-input">
                                                    <label class="form-label require" for="current_password">Current password</label>
                                                    <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                                                        class="form-control @error('current_password') is-invalid @enderror">
                                                    @error('current_password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-4 mb-3 form-input">
                                                    <label class="form-label require" for="password">New password</label>
                                                    <input type="password" name="password" id="password" autocomplete="new-password"
                                                        class="form-control @error('password') is-invalid @enderror">
                                                    @error('password') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="col-md-4 mb-3 form-input">
                                                    <label class="form-label require" for="password_confirmation">Confirm new password</label>
                                                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" class="form-control">
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <button type="submit" class="btn btn-primary">Change password</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="settings-section">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                            <div>
                                                <h5>Active sessions</h5>
                                                <p class="section-hint">Browsers and devices signed in to your account.</p>
                                            </div>
                                            @if ($sessions->where('current', false)->isNotEmpty())
                                                <form method="POST" action="{{ admin_url('administration/settings/sessions/others/logout') }}" class="confirm-submit"
                                                    data-confirm="Sign out of every other browser and device?">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-right-from-bracket me-1"></i> Log out everywhere else
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        @forelse ($sessions as $session)
                                            <div class="session-row">
                                                <div class="session-icon">
                                                    <i class="fa-solid {{ str_contains($session['device'], 'Android') || str_contains($session['device'], 'iOS') ? 'fa-mobile-screen' : 'fa-desktop' }}"></i>
                                                </div>
                                                <div class="session-meta">
                                                    <div class="fw-semibold">
                                                        {{ $session['device'] }}
                                                        @if ($session['current'])
                                                            <span class="badge bg-success-subtle text-success ms-1">This device</span>
                                                        @endif
                                                    </div>
                                                    <small>
                                                        {{ $session['ip'] ?: 'Unknown IP' }} ·
                                                        {{ $session['current'] ? 'Active now' : 'Last active ' . $session['last_active']->format('d-m-Y h:i A') . ' (' . $session['last_active']->diffForHumans() . ')' }}
                                                    </small>
                                                </div>
                                                @unless ($session['current'])
                                                    <form method="POST" action="{{ admin_url('administration/settings/sessions/logout') }}">
                                                        @csrf
                                                        <input type="hidden" name="session" value="{{ $session['key'] }}">
                                                        <button type="submit" class="btn btn-sm btn-light" title="Sign out this session">Sign out</button>
                                                    </form>
                                                @endunless
                                            </div>
                                        @empty
                                            <p class="text-muted small mb-0">Session details are not available.</p>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- ================= Privacy and Data ================= --}}
                                <div class="tab-pane fade {{ $section === 'privacy' ? 'show active' : '' }}" id="pane-privacy" role="tabpanel">
                                    <div class="settings-section">
                                        <h5>Download my data</h5>
                                        <p class="section-hint">
                                            A JSON file with your account details{{ $isEmployee ? ', employee record, document list' : '' }}
                                            and signed-in sessions. Passwords and security codes are never included.
                                        </p>
                                        <a href="{{ admin_url('administration/settings/data/download') }}" class="btn btn-primary">
                                            <i class="fa-solid fa-download me-1"></i> Download my data
                                        </a>
                                    </div>
                                </div>

                                {{-- ================= App Preferences ================= --}}
                                <div class="tab-pane fade {{ $section === 'preferences' ? 'show active' : '' }}" id="pane-preferences" role="tabpanel">
                                    <form method="POST" action="{{ admin_url('administration/settings/preferences') }}">
                                        @csrf
                                        <div class="settings-section">
                                            <h5>Default page size</h5>
                                            <p class="section-hint">How many rows lists show before you page through them.</p>
                                            <div class="btn-group" role="group" aria-label="Default page size">
                                                @foreach (\App\Support\UserPreferences::PAGE_SIZES as $size)
                                                    <input type="radio" class="btn-check" name="page_size" id="page_size_{{ $size }}" value="{{ $size }}"
                                                        @checked((int) old('page_size', $prefs['page_size']) === $size)>
                                                    <label class="btn btn-outline-primary px-4" for="page_size_{{ $size }}">{{ $size }}</label>
                                                @endforeach
                                            </div>
                                            @error('page_size') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            <div class="text-end mt-3">
                                                <button type="submit" class="btn btn-primary">Save preferences</button>
                                            </div>
                                        </div>
                                    </form>

                                    @if (CheckUserRole(ROLE_ADMIN) || CheckUserRole(ROLE_SUPERADMIN))
                                        <div class="settings-section">
                                            <h5>Roles and permissions</h5>
                                            <p class="section-hint">Who can see and change each module. Managed in Administration.</p>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a href="{{ admin_url('administration/role/list') }}" class="btn btn-outline-primary">
                                                    <i class="fa-solid fa-user-tag me-1"></i> User roles
                                                </a>
                                                <a href="{{ admin_url('administration/permission/list') }}" class="btn btn-outline-primary">
                                                    <i class="fa-solid fa-key me-1"></i> Permissions
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(document).ready(function() {
            /* Keep the open section in the address bar so refresh and Back return to it. */
            $('.settings-nav .nav-link').on('shown.bs.tab', function() {
                var url = new URL(window.location.href);
                url.searchParams.set('section', $(this).data('section'));
                history.replaceState(null, '', url);
            });

            /* Live preview of appearance choices; saving makes them permanent. */
            var root = document.documentElement;
            var accentKeys = ['--brand-orange', '--brand-orange-dark', '--brand-yellow', '--brand-orange-rgb', '--brand-text',
                '--brand-soft', '--brand-soft-strong', '--brand-gradient'];

            $('#appearanceForm').on('change', 'input', function() {
                var name = this.name;
                if (name === 'theme') {
                    root.setAttribute('data-app-theme', this.value);
                    window.applyAppTheme && window.applyAppTheme();
                } else if (name === 'accent') {
                    accentKeys.forEach(function(key) { root.style.removeProperty(key); });
                    (this.dataset.css || '').split(';').forEach(function(rule) {
                        var i = rule.indexOf(':');
                        if (i > 0) root.style.setProperty(rule.slice(0, i).trim(), rule.slice(i + 1).trim());
                    });
                } else if (name === 'font_size') {
                    root.style.fontSize = this.dataset.root || '';
                } else if (name === 'density') {
                    root.setAttribute('data-density', this.value);
                } else if (name === 'reduced_motion' && this.type === 'checkbox') {
                    root.toggleAttribute('data-reduced-motion', this.checked);
                }
            });

            /* Avatar preview before upload */
            $('#avatarInput').on('change', function() {
                if (this.files && this.files[0]) {
                    $('#avatarPreview').attr('src', URL.createObjectURL(this.files[0]));
                }
            });

            $('.confirm-submit').on('submit', function(e) {
                if (!window.confirm($(this).data('confirm'))) e.preventDefault();
            });

            $('#profileForm').validate({
                rules: {
                    name: { required: true, maxlength: 100 },
                    email: { required: true, email: true, maxlength: 50 },
                    mobile: { required: true, digits: true, minlength: 10, maxlength: 15 },
                },
                messages: {
                    name: { required: 'Please enter your name' },
                    email: { required: 'Please enter your email' },
                    mobile: { required: 'Please enter your phone', minlength: 'Phone must be 10 to 15 digits', maxlength: 'Phone must be 10 to 15 digits' },
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback d-block');
                    element.closest('.form-input').append(error);
                },
                highlight: function(element) { $(element).addClass('is-invalid'); },
                unhighlight: function(element) { $(element).removeClass('is-invalid'); },
            });

            $('#passwordForm').validate({
                rules: {
                    current_password: { required: true },
                    password: { required: true, minlength: 8 },
                    password_confirmation: { required: true, equalTo: '#password' },
                },
                messages: {
                    current_password: { required: 'Please enter your current password' },
                    password: { required: 'Please enter a new password', minlength: 'At least 8 characters' },
                    password_confirmation: { required: 'Please confirm the new password', equalTo: 'The new passwords do not match' },
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback d-block');
                    element.closest('.form-input').append(error);
                },
                highlight: function(element) { $(element).addClass('is-invalid'); },
                unhighlight: function(element) { $(element).removeClass('is-invalid'); },
            });
        });
    </script>
@endpush
