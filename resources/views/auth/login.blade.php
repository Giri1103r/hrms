@extends('layouts.auth-split')
@section('title', 'Login')

@push('style')
<style>
    :root {
        --auth-orange: #f7924a;
        --auth-yellow: #fdc830;
        --auth-gradient: linear-gradient(90deg, #f7924a 0%, #fdc830 100%);
        --auth-ink: #1f2937;
        --auth-muted: #9aa1ad;
    }

    .auth-split-body {
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        background:
            radial-gradient(circle at 85% 15%, rgba(253, 200, 48, 0.25), transparent 40%),
            radial-gradient(circle at 10% 90%, rgba(247, 146, 74, 0.18), transparent 45%),
            #f1ede6;
    }

    .auth-card {
        /* The theme CSS zeroes body side padding, so the gutter lives here. */
        width: calc(100% - 32px);
        max-width: 1180px;
        min-height: 640px;
        display: grid;
        grid-template-columns: minmax(0, 42fr) minmax(0, 58fr);
        border-radius: 40px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 30px 80px rgba(80, 60, 20, 0.18);
    }

    /* ---------- left: form ---------- */
    .auth-form-panel {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 48px 56px 0;
        background-color: #fff;
        /* faint hexagon pattern */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='98' viewBox='0 0 56 98'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34L28 66zm0 32L0 82V66l28 16 28-16v16L28 98z' fill='none' stroke='%23f2f2f2' stroke-width='1.2'/%3E%3C/svg%3E");
    }

    .auth-logo img {
        height: 76px;
        width: auto;
    }

    .auth-avatar {
        width: 116px;
        height: 116px;
        margin: 26px 0 30px;
        border-radius: 50%;
        background: #eef0f4;
        box-shadow: 0 10px 25px rgba(31, 41, 55, 0.08);
        overflow: hidden;
    }

    .auth-form {
        width: 100%;
        max-width: 400px;
    }

    .auth-heading {
        display: inline-block;
        margin: 0 0 28px;
        padding-bottom: 6px;
        font-size: 1.85rem;
        font-weight: 500;
        color: var(--auth-ink);
        border-bottom: 3px solid var(--auth-yellow);
    }

    .form-input {
        margin-bottom: 22px;
    }

    .auth-field {
        position: relative;
        display: flex;
        align-items: stretch;
        height: 58px;
        border-radius: 999px;
        background: #fff;
        border: 1.5px solid transparent;
        box-shadow: 0 8px 22px rgba(31, 41, 55, 0.08);
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .auth-field:focus-within {
        border-color: var(--auth-yellow);
        box-shadow: 0 8px 22px rgba(253, 200, 48, 0.25);
    }

    .auth-field:has(.is-invalid),
    .auth-field:has(.is-invalid):focus-within {
        border-color: #f06548;
        box-shadow: 0 8px 22px rgba(240, 101, 72, 0.18);
    }

    .auth-field-icon {
        flex: none;
        display: grid;
        place-items: center;
        width: 66px;
        color: #fff;
        background: var(--auth-gradient);
        border-radius: 999px 0 0 999px;
    }

    .auth-field input {
        flex: 1;
        min-width: 0;
        border: none;
        outline: none;
        padding: 0 18px;
        font-size: 0.95rem;
        color: var(--auth-ink);
        background: transparent;
    }

    .auth-field input::placeholder {
        color: #b7bcc5;
    }

    .auth-field input[type="password"] {
        letter-spacing: 0.18em;
        color: #e8a400;
    }

    .auth-field input[type="password"]::placeholder {
        letter-spacing: normal;
    }

    .show-pass {
        flex: none;
        display: grid;
        place-items: center;
        width: 52px;
        border: none;
        background: none;
        color: var(--auth-muted);
        cursor: pointer;
    }

    .show-pass:hover {
        color: var(--auth-orange);
    }

    .form-input .invalid-feedback,
    .form-input .server-error {
        display: block;
        margin: 6px 0 0 22px;
        font-size: 0.8rem;
        color: #f06548;
    }

    .auth-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: -4px 4px 22px;
        font-size: 0.85rem;
        color: #6b7280;
    }

    .auth-options .form-check-input:checked {
        background-color: var(--auth-orange);
        border-color: var(--auth-orange);
    }

    .auth-submit {
        width: 100%;
        height: 58px;
        border: none;
        border-radius: 999px;
        background: var(--auth-gradient);
        color: #fff;
        font-size: 1.05rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        box-shadow: 0 12px 26px rgba(247, 146, 74, 0.35);
        transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
    }

    .auth-submit:hover {
        filter: brightness(1.04);
        box-shadow: 0 14px 30px rgba(247, 146, 74, 0.45);
    }

    .auth-submit:active {
        transform: translateY(1px);
    }

    .auth-forgot {
        margin-top: auto;
        padding-top: 40px;
    }

    .auth-forgot a {
        display: inline-block;
        padding: 12px 22px 14px;
        border: 1.5px solid var(--auth-yellow);
        border-bottom: none;
        border-radius: 22px 22px 0 0;
        background: #fff;
        color: var(--auth-ink);
        font-size: 0.95rem;
        font-weight: 500;
        text-decoration: none;
    }

    .auth-forgot a:hover {
        color: var(--auth-orange);
    }

    /* ---------- right: illustration ---------- */
    .auth-art-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        background: radial-gradient(circle at 50% 45%, #fff3d6 0%, #fde7b8 55%, #fbd99a 100%);
    }

    .auth-art-panel svg {
        width: 100%;
        max-width: 620px;
        height: auto;
    }

    @media (max-width: 991.98px) {
        .auth-card {
            grid-template-columns: minmax(0, 1fr);
            max-width: 520px;
            min-height: 0;
            border-radius: 28px;
        }

        .auth-art-panel {
            display: none;
        }

        .auth-form-panel {
            padding: 36px 24px 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .auth-field,
        .auth-submit {
            transition: none;
        }
    }
</style>
@endpush

@section('content')
    <main class="auth-card">

        <!-- Left: sign-in form -->
        <section class="auth-form-panel">
            <a href="{{ url('/') }}" class="auth-logo">
                <img src="{{ url('public/assets/images/logo.png') }}" alt="{{ config('app.name') }}">
            </a>

            <div class="auth-avatar" aria-hidden="true">
                <svg viewBox="0 0 116 116" width="116" height="116">
                    <circle cx="58" cy="58" r="58" fill="#eef0f4" />
                    <path d="M18 116c2-24 18-36 40-36s38 12 40 36z" fill="#2d3a5c" />
                    <path d="M48 80l10 18 10-18z" fill="#fff" />
                    <path d="M56 84h4l3 22-5 6-5-6z" fill="#4a7bd0" />
                    <rect x="51" y="62" width="14" height="20" rx="6" fill="#f2b796" />
                    <ellipse cx="58" cy="50" rx="17" ry="20" fill="#f6c3a5" />
                    <path d="M40 48c-1-15 8-24 18-24s20 8 18 24c-3-6-6-10-18-11-11 1-15 5-18 11z" fill="#f2793b" />
                </svg>
            </div>

            <form id="login_form_validate" class="auth-form" action="{{ admin_url('logintry') }}" method="post" novalidate>
                @csrf
                <h1 class="auth-heading">Sign In</h1>

                <div class="form-input">
                    <div class="auth-field">
                        <span class="auth-field-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                        </span>
                        <label for="email" class="visually-hidden">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            placeholder="Enter your email" autocomplete="username" required autofocus>
                    </div>
                    @error('email')
                        <span class="server-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-input">
                    <div class="auth-field">
                        <span class="auth-field-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                <path d="M12 15v2" />
                            </svg>
                        </span>
                        <label for="dlab-password" class="visually-hidden">Password</label>
                        <input type="password" id="dlab-password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>
                        <button type="button" class="show-pass" aria-label="Show password" aria-pressed="false">
                            <i class="fa fa-eye-slash"></i>
                        </button>
                    </div>
                    @error('password')
                        <span class="server-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="auth-options">
                    <div class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" id="customCheckBox1" name="remember">
                        <label class="form-check-label" for="customCheckBox1">Remember me</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="{{ env('GOOGLE_RECAPTCHA_KEY') }}"></div>
                    <div class="recaptcha-error" style="color:red;"></div>
                </div>

                <button class="auth-submit" type="submit">Sign In</button>
            </form>

            <div class="auth-forgot">
                <a href="{{ admin_url('password/forgot') }}">Forgot Password ?</a>
            </div>
        </section>

        <!-- Right: illustration -->
        <section class="auth-art-panel" aria-hidden="true">
            <svg viewBox="0 0 640 520" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="artHeader" x1="0" x2="1">
                        <stop offset="0" stop-color="#fdc830" />
                        <stop offset="1" stop-color="#f7924a" />
                    </linearGradient>
                    <linearGradient id="artGear" x1="0" x2="1" y1="0" y2="1">
                        <stop offset="0" stop-color="#fdc830" />
                        <stop offset="1" stop-color="#f7a13d" />
                    </linearGradient>
                </defs>

                <!-- floor shadow -->
                <ellipse cx="330" cy="478" rx="270" ry="16" fill="#e9c98a" opacity="0.45" />

                <!-- gears -->
                <g fill="url(#artGear)">
                    <path d="M520 38l9 2 4 14 12 5 12-8 7 6-7 12 5 12 14 3 1 9-13 5-2 13 10 10-5 8-14-4-10 8 1 14-9 3-7-12h-13l-6 12-9-3 1-14-10-8-14 5-5-8 10-10-3-13-13-5 1-9 14-3 5-12-7-12 7-6 12 8 12-5 4-14z" />
                </g>
                <circle cx="535" cy="100" r="22" fill="#fde7b8" />
                <g fill="url(#artGear)">
                    <path d="M590 140l6 1 2 8 7 3 7-5 4 4-5 7 3 7 8 2v6l-8 2-3 7 5 7-4 4-7-5-7 3-2 8h-6l-2-8-7-3-7 5-4-4 5-7-3-7-8-2v-6l8-2 3-7-5-7 4-4 7 5 7-3z" />
                </g>
                <circle cx="593" cy="169" r="11" fill="#fde7b8" />

                <!-- dashboard window -->
                <rect x="170" y="70" width="350" height="300" rx="8" fill="#ffffff" />
                <rect x="170" y="70" width="350" height="34" rx="8" fill="url(#artHeader)" />
                <rect x="170" y="92" width="350" height="12" fill="url(#artHeader)" />
                <rect x="186" y="82" width="44" height="10" rx="3" fill="#fff" />

                <rect x="190" y="122" width="78" height="44" rx="4" fill="#e3e6ec" />
                <rect x="282" y="126" width="128" height="7" rx="3.5" fill="#e3e6ec" />
                <rect x="282" y="141" width="128" height="7" rx="3.5" fill="#e3e6ec" />
                <rect x="282" y="156" width="96" height="7" rx="3.5" fill="#e3e6ec" />

                <!-- stock bar chart -->
                <rect x="190" y="186" width="220" height="150" rx="10" fill="#f6f7fa" stroke="#e3e6ec" />
                <rect x="208" y="200" width="110" height="9" rx="4.5" fill="#d9dde5" />
                <rect x="214" y="268" width="22" height="52" rx="3" fill="#fdc830" />
                <rect x="246" y="244" width="22" height="76" rx="3" fill="#f7924a" />
                <rect x="278" y="282" width="22" height="38" rx="3" fill="#fdc830" />
                <rect x="310" y="232" width="22" height="88" rx="3" fill="#f7924a" />
                <rect x="342" y="258" width="22" height="62" rx="3" fill="#fdc830" />
                <rect x="372" y="248" width="22" height="72" rx="3" fill="#b6c8ea" />
                <path d="M206 320h192" stroke="#d9dde5" stroke-width="2" />

                <!-- checklist -->
                <rect x="428" y="186" width="28" height="28" rx="4" fill="#fdc830" />
                <path d="m435 200 5 5 10-10" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="428" y="226" width="28" height="28" rx="4" fill="#fdc830" />
                <path d="m435 240 5 5 10-10" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="428" y="266" width="28" height="28" rx="4" fill="#fbd26b" />

                <!-- user avatars on the window -->
                <circle cx="478" cy="138" r="30" fill="#eef0f4" />
                <path d="M456 160c2-11 10-16 22-16s20 5 22 16a30 30 0 0 1-44 0z" fill="#2d3a5c" />
                <ellipse cx="478" cy="129" rx="9" ry="11" fill="#f6c3a5" />
                <path d="M469 127c-1-9 4-13 9-13s10 4 9 13c-2-4-4-6-9-6s-7 2-9 6z" fill="#f2793b" />
                <circle cx="486" cy="226" r="22" fill="#eef0f4" />
                <path d="M470 242c2-8 8-11 16-11s14 3 16 11a22 22 0 0 1-32 0z" fill="#e8483f" />
                <ellipse cx="486" cy="220" rx="7" ry="8.5" fill="#f6c3a5" />
                <path d="M478 222c-2-9 2-14 8-14s10 5 8 14c-2-5-4-7-8-7s-6 2-8 7z" fill="#2b2b3a" />
                <circle cx="486" cy="280" r="18" fill="#eef0f4" />
                <path d="M486 271v18M477 280h18" stroke="#9aa1ad" stroke-width="2.5" stroke-linecap="round" />

                <!-- inventory boxes -->
                <g>
                    <rect x="62" y="372" width="96" height="96" rx="4" fill="#e7b36a" />
                    <rect x="62" y="372" width="96" height="16" fill="#d79c4c" />
                    <rect x="100" y="372" width="20" height="40" fill="#f6dcae" />
                    <rect x="158" y="396" width="80" height="72" rx="4" fill="#efc07c" />
                    <rect x="158" y="396" width="80" height="13" fill="#dda558" />
                    <rect x="188" y="396" width="20" height="32" fill="#f8e3bb" />
                    <rect x="84" y="300" width="74" height="72" rx="4" fill="#efc07c" />
                    <rect x="84" y="300" width="74" height="13" fill="#dda558" />
                    <rect x="111" y="300" width="20" height="32" fill="#f8e3bb" />
                </g>

                <!-- person with tablet -->
                <g>
                    <path d="M300 470l6-104h22l2 104z" fill="#26304f" />
                    <path d="M336 470l-2-104h22l8 104z" fill="#26304f" />
                    <rect x="292" y="466" width="22" height="8" rx="4" fill="#1b2238" />
                    <rect x="348" y="466" width="22" height="8" rx="4" fill="#1b2238" />
                    <path d="M296 380c0-52 10-78 36-82h4c26 4 34 30 34 82z" fill="#2d3a5c" />
                    <path d="M325 298l9 24 9-24z" fill="#fff" />
                    <path d="M332 304h4l3 40-5 6-5-6z" fill="#4a7bd0" />
                    <rect x="326" y="270" width="16" height="30" rx="7" fill="#f2b796" />
                    <ellipse cx="334" cy="258" rx="17" ry="20" fill="#f6c3a5" />
                    <path d="M316 256c-2-16 8-25 18-25s20 9 18 25c-3-7-7-11-18-12-11 1-15 5-18 12z" fill="#f2793b" />
                    <!-- arm + tablet -->
                    <path d="M366 318c14 12 22 30 18 46l-12 2c-2-14-8-24-16-32z" fill="#2d3a5c" />
                    <rect x="352" y="340" width="40" height="52" rx="5" transform="rotate(-12 372 366)" fill="#2d3a5c" />
                    <rect x="357" y="345" width="30" height="42" rx="3" transform="rotate(-12 372 366)" fill="#b6c8ea" />
                    <path d="M298 320c-10 16-12 34-6 50l11-2c-3-14-1-28 6-40z" fill="#2d3a5c" />
                    <circle cx="298" cy="372" r="7" fill="#f2b796" />
                </g>

                <!-- desk + seated person at monitor -->
                <g>
                    <rect x="430" y="380" width="170" height="8" rx="3" fill="#c79a63" />
                    <rect x="446" y="388" width="6" height="84" fill="#a87c4a" />
                    <rect x="578" y="388" width="6" height="84" fill="#a87c4a" />
                    <rect x="446" y="316" width="78" height="54" rx="5" fill="#4a7bd0" />
                    <rect x="451" y="321" width="68" height="44" rx="2" fill="#eaf1ff" />
                    <rect x="458" y="330" width="22" height="26" rx="2" fill="#d4dcea" />
                    <rect x="486" y="332" width="26" height="5" rx="2.5" fill="#b6c8ea" />
                    <rect x="486" y="342" width="20" height="5" rx="2.5" fill="#b6c8ea" />
                    <rect x="480" y="370" width="10" height="10" fill="#3a63ad" />

                    <rect x="572" y="318" width="40" height="64" rx="8" fill="#eadfd3" />
                    <rect x="556" y="406" width="58" height="10" rx="5" fill="#d9cbbb" />
                    <rect x="582" y="416" width="6" height="44" fill="#9aa1ad" />
                    <path d="M560 470l25-10 25 10" stroke="#9aa1ad" stroke-width="4" fill="none" stroke-linecap="round" />

                    <path d="M552 412c-20 0-38 2-46 4v52h14l2-40c12-2 24-2 34-4z" fill="#26304f" />
                    <rect x="500" y="464" width="22" height="8" rx="4" fill="#1b2238" />
                    <path d="M556 410c4-36 0-62-18-70-16 4-22 20-22 42 0 14 4 26 10 30z" fill="#2d3a5c" />
                    <path d="M522 362c-8 6-14 14-16 22l8 4c4-8 8-12 14-14z" fill="#2d3a5c" />
                    <circle cx="506" cy="388" r="6" fill="#f2b796" />
                    <ellipse cx="546" cy="318" rx="15" ry="17" fill="#f6c3a5" />
                    <path d="M531 316c-1-14 7-21 15-21s16 7 15 21c-3-6-6-9-15-10-9 1-12 4-15 10z" fill="#2b2b3a" />
                </g>
            </svg>
        </section>

    </main>
@endsection

@push('script')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <script type="text/javascript">
        $('#login_form_validate').on('submit', function(e) {
            if (grecaptcha.getResponse() == "") {
                e.preventDefault();
                $(".recaptcha-error").html('The recaptcha field is required');
                return false;
            } else {
                $(".recaptcha-error").empty();
                return true;
            }
        });

        $('.show-pass').on('click', function() {
            var $input = $('#dlab-password');
            var show = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $(this).attr('aria-pressed', show)
                .attr('aria-label', show ? 'Hide password' : 'Show password')
                .find('i').toggleClass('fa-eye-slash', !show).toggleClass('fa-eye', show);
        });

        $(function() {
            $('#login_form_validate').validate({
                rules: {
                    email: {
                        required: true,
                    },
                    password: {
                        required: true,
                        'minlength': 8,
                        'maxlength': 50,
                    },
                },
                messages: {
                    email: {
                        required: "Please enter your email",
                    },
                    password: {
                        required: "Please enter your Password",
                        maxlength: "Maximum character limit reached",
                    },
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-input').append(error);
                },
                highlight: function(element) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid');
                }
            });
        });
    </script>
@endpush
