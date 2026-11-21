<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | {{ config('app.name') }}</title>

    <link rel="shortcut icon" href="{{ url('public/assets/images/logo.png') }}">
    <link rel="manifest" href="{{ url('manifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- App css -->
    <link href="{{ url('public/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <!-- icons -->
    <link href="{{ url('public/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    @stack('style')
    <link href="{{ url('public/assets/css/brand-theme.css') }}" rel="stylesheet" type="text/css" />
</head>

<body class="auth-split-body">

    @yield('content')

    <!-- Vendor -->
    <script src="{{ url('public/assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ url('public/assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script src="{{ public_plugins('sweetalert/SweetAlertFull.js') }}"></script>
    <script src="{{ public_plugins('jqueryvalidation/jquery.validate.min.js') }}"></script>
    <script src="{{ public_plugins('jqueryvalidation/additional-methods.min.js') }}"></script>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('public/sw.js')
                .then(reg => console.log('Service Worker registered', reg))
                .catch(err => console.error('Service Worker failed', err));
        }
    </script>

    <script type="text/javascript" nonce="projectcab">
        var toastMixin = Swal.mixin({
            toast: true,
            showCloseButton: true,
            icon: 'success',
            title: 'General Title',
            animation: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        @if ($message = Session::get('success'))
            toastMixin.fire({
                icon: 'success',
                animation: true,
                title: '{{ $message }}'
            });
        @endif

        @if ($message = Session::get('error'))
            toastMixin.fire({
                icon: 'error',
                animation: true,
                title: '{{ $message }}',
            });
        @endif
    </script>

    @stack('script')

</body>

</html>
