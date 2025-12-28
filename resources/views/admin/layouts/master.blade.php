<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title . ' - ' . Setting::getValue('app_name') }}</title>
    <link rel="icon" href="{{ asset(Setting::getValue('app_favicon')) }}" type="image/png" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo/blue-marketing-logo.png') }}">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- daterange picker -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('template/admin/dist/css/adminlte.min.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <!-- Bootstrap Color Picker -->
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}">

    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">

    <!-- Bootstrap Color Picker -->
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}">


    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css">

    <!-- Bootstrap4 Duallistbox -->
    <link rel="stylesheet"
        href="{{ asset('template/admin/plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css') }}">
    <!-- BS Stepper -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/bs-stepper/css/bs-stepper.min.css') }}">
    <!-- dropzonejs -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/dropzone/min/dropzone.min.css') }}">

    <!-- Custom style -->
    <link rel="stylesheet" href="{{ asset('template/admin/dist/css/custom.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('template/admin/dist/css/theme.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    @stack('style')
    <style>
        div#load_screen {
            background: #fff;
            opacity: 1;
            position: fixed;
            z-index: 999999;
            top: 0px;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }

        div#load_screen .loader {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
                .card-loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 8px;
        }

        .custom_card {
            position: relative;
        }

        .voucher-tab-wrapper {
            position: relative;
        }

        .voucher-tab-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            border: none;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .voucher-tab-remove:hover {
            background: #c82333;
        }

        .voucher-tab-remove i {
            font-size: 10px;
        }
    </style>
</head>

<body class="control-sidebar-slide-open  sidebar-mini sidebar-collapse">
    @php
        if (!$errors->isEmpty()) {
            alert()
                ->error('Pemberitahuan', implode('<br>', $errors->all()))
                ->toToast()
                ->toHtml();
        }
    @endphp
    <div class="wrapper">
        <!-- Preloader -->
        {{-- <div class="preloader flex-column justify-content-center align-items-center">
            <img class="animation__shake" src="{{ asset(Setting::getValue('app_logo')) }}"
                alt="{{ Setting::getName('app_name') }}" height="60" width="60">
        </div> --}}
        <div id="load_screen" class="preloader flex-column justify-content-center align-items-center">
            <div class="loader">
                <div class="loader-content">
                    <svg width="64" height="64" viewBox="0 0 135 135" xmlns="http://www.w3.org/2000/svg"
                        fill="#4361ee">
                        <path
                            d="M67.447 58c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm9.448 9.447c0 5.523 4.477 10 10 10 5.522 0 10-4.477 10-10s-4.478-10-10-10c-5.523 0-10 4.477-10 10zm-9.448 9.448c-5.523 0-10 4.477-10 10 0 5.522 4.477 10 10 10s10-4.478 10-10c0-5.523-4.477-10-10-10zM58 67.447c0-5.523-4.477-10-10-10s-10 4.477-10 10 4.477 10 10 10 10-4.477 10-10z">
                            <animateTransform attributeName="transform" type="rotate" from="0 67 67" to="-360 67 67"
                                dur="2.5s" repeatCount="indefinite" />
                        </path>
                        <path
                            d="M28.19 40.31c6.627 0 12-5.374 12-12 0-6.628-5.373-12-12-12-6.628 0-12 5.372-12 12 0 6.626 5.372 12 12 12zm30.72-19.825c4.686 4.687 12.284 4.687 16.97 0 4.686-4.686 4.686-12.284 0-16.97-4.686-4.687-12.284-4.687-16.97 0-4.687 4.686-4.687 12.284 0 16.97zm35.74 7.705c0 6.627 5.37 12 12 12 6.626 0 12-5.373 12-12 0-6.628-5.374-12-12-12-6.63 0-12 5.372-12 12zm19.822 30.72c-4.686 4.686-4.686 12.284 0 16.97 4.687 4.686 12.285 4.686 16.97 0 4.687-4.686 4.687-12.284 0-16.97-4.685-4.687-12.283-4.687-16.97 0zm-7.704 35.74c-6.627 0-12 5.37-12 12 0 6.626 5.373 12 12 12s12-5.374 12-12c0-6.63-5.373-12-12-12zm-30.72 19.822c-4.686-4.686-12.284-4.686-16.97 0-4.686 4.687-4.686 12.285 0 16.97 4.686 4.687 12.284 4.687 16.97 0 4.687-4.685 4.687-12.283 0-16.97zm-35.74-7.704c0-6.627-5.372-12-12-12-6.626 0-12 5.373-12 12s5.374 12 12 12c6.628 0 12-5.373 12-12zm-19.823-30.72c4.687-4.686 4.687-12.284 0-16.97-4.686-4.686-12.284-4.686-16.97 0-4.687 4.686-4.687 12.284 0 16.97 4.686 4.687 12.284 4.687 16.97 0z">
                            <animateTransform attributeName="transform" type="rotate" from="0 67 67" to="360 67 67"
                                dur="8s" repeatCount="indefinite" />
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Navbar -->
        @include('admin.layouts.navbar')
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        @include('admin.layouts.sidebar')

        <!-- Content Wrapper. Contains page content -->
        <div class="main-content page-content">
            @yield('content')
        </div>
        <!-- /.content-wrapper -->

        @yield('modal')
        @include('admin.layouts.modal')
        @include('sweetalert::alert')
        @include('admin.layouts.footer')
    </div>
    <!-- ./wrapper -->
    <!-- jQuery -->
    <script src="{{ asset('template/admin/plugins/jquery/jquery.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    @yield('js')
    @include('admin.layouts.script')
    <!-- jQuery UI 1.11.4 -->
    <script src="{{ asset('template/admin/plugins/jquery-ui/jquery-ui.min.js') }}"></script>
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <!-- DataTables  & Plugins -->
    <script src="{{ asset('template/admin/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>

    <script src="{{ asset('template/admin/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>

    <script src="{{ asset('template/admin/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>

    <!-- jQuery Mapael -->
    <script src="{{ asset('template/admin/plugins/jquery-mousewheel/jquery.mousewheel.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/raphael/raphael.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/jquery-mapael/jquery.mapael.min.js') }}"></script>
    {{-- <script src="{{ asset('template/admin/plugin/jquery-mapael/maps/usa_states.min.js') }}"></script> --}}


    <!-- Select2 -->
    <script src="{{ asset('template/admin/plugins/select2/js/select2.full.min.js') }}"></script>
    <!-- Bootstrap4 Duallistbox -->
    <script src="{{ asset('template/admin/plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js') }}">
    </script>
    <!-- InputMask -->
    <script src="{{ asset('template/admin/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('template/admin/plugins/inputmask/jquery.inputmask.min.js') }}"></script>

    <!-- date-range-picker -->
    <script src="{{ asset('template/admin/plugins/daterangepicker/daterangepicker.js') }}"></script>

    <!-- bootstrap color picker -->
    <script src="{{ asset('template/admin/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('template/admin/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}">
    </script>
    <!-- BS-Stepper -->
    <script src="{{ asset('template/admin/plugins/bs-stepper/js/bs-stepper.min.js') }}"></script>

    <!-- dropzonejs -->
    <script src="{{ asset('template/admin/plugins/dropzone/min/dropzone.min.js') }}"></script>



    <!-- ChartJS -->
    <script src="{{ asset('template/admin/plugins/chart.js/Chart.min.js') }}"></script>
    <script>
        $.widget.bridge('uibutton', $.ui.button)
    </script>


    <!-- Bootstrap 4 -->
    <script src="{{ asset('template/admin/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- overlayScrollbars -->
    <script src="{{ asset('template/admin/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('template/admin/dist/js/adminlte.js') }}"></script>

    <!-- AdminLTE for demo purposes -->
    {{-- <script src="{{ asset('template/admin/dist/js/demo.js') }}"></script> --}}
    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <script src="{{ asset('template/admin/dist/js/pages/dashboard2.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/number-to-words@1.2.4/numberToWords.js"></script>



    <!-- Page specific script -->
    <script>
        $(function() {
            $("#example1").DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        });

        $(function() {
            // Initialize Select2 Elements
            $('.select2').select2();

            $('.select2bs4').select2({
                theme: 'bootstrap4'
            });

            // Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {
                'placeholder': 'dd/mm/yyyy'
            });
            // Datemask2 mm/dd/yyyy
            $('#datemask2').inputmask('mm/dd/yyyy', {
                'placeholder': 'mm/dd/yyyy'
            });
            // Money Euro
            $('[data-mask]').inputmask();

            // Date picker
            $('#reservationdate').datetimepicker({
                format: 'L'
            });

            // Date and time picker
            $('#reservationdatetime').datetimepicker({
                icons: {
                    time: 'far fa-clock'
                }
            });

            // Date range picker
            $('#reservation').daterangepicker();

            // Date range picker with time picker
            $('#reservationtime').daterangepicker({
                timePicker: true,
                timePickerIncrement: 30,
                locale: {
                    format: 'MM/DD/YYYY hh:mm A'
                }
            });

            // Date range as a button
            $('#daterange-btn').daterangepicker({
                    ranges: {
                        'Today': [moment(), moment()],
                        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')],
                        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                            'month').endOf('month')]
                    },
                    startDate: moment().subtract(29, 'days'),
                    endDate: moment()
                },
                function(start, end) {
                    $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format(
                        'MMMM D, YYYY'));
                }
            );

            // Timepicker
            $('#timepicker').datetimepicker({
                format: 'LT'
            });

            // Bootstrap Duallistbox
            $('.duallistbox').bootstrapDualListbox();

            // Colorpicker
            $('.my-colorpicker1').colorpicker();
            $('.my-colorpicker2').colorpicker();

            $('.my-colorpicker2').on('colorpickerChange', function(event) {
                $('.my-colorpicker2 .fa-square').css('color', event.color.toString());
            });

            $('#search_number').click(function() {
                var number = $("#number").val();

                $.ajax({
                    type: "post",
                    url: "{{ route('lead.search') }}",
                    data: {
                        number: number,
                        _token: "{{ csrf_token() }}"
                    },
                    dataType: "JSON",
                    success: function(response) {
                        $("#msg").html("");
                        var data = response.data;
                        let text = "";
                        if (!data) {
                            $('#msg')
                                .html("No record found")
                                .addClass('message-error')
                                .removeClass('message-success');
                            return;
                        }

                        var msg =
                            '<span class="message-alert"><b>Name:</b> ' + data.name +
                            '</span><br>' +
                            '<span class="message-alert"><b>Project:</b> ' + data.project +
                            '</span><br>' +
                            '<b>Created At:</b> ' + data.created_at + '<br>' +
                            '<b>Updated At:</b> ' + data.updated_at + '<br>';

                        const assigne = data.assignTo || [];
                        if (assigne.length > 0) {
                            msg += "<b>Assign To:</b><br>";
                            assigne.forEach(function(item) {
                                text += "- " + item.name + "<br>";
                            });
                        }

                        msg += text;

                        const currentUserId = {{ Auth::id() }};

                        const isSuperAdmin ={{ Auth::user()->getRoleNames()->first() == 'superadmin' ? 'true' : 'false' }};
                        const assignedIds = data.assignTo ? data.assignTo.map(a => a.id) : [];
                        if (isSuperAdmin || assignedIds.includes(currentUserId)) {
                            const editUrl =
                                "{{ url(config('adminPrefix') . 'admin/crm/lead') }}";
                            msg +=
                                '<br><button class="btn btn-primary" id="editLeadBtn" data-url="' +
                                editUrl + '">Edit Lead</button>';
                        } else {
                            msg +=
                                '<br><button class="btn btn-warning" id="requestEditBtn">Request Edit</button>';
                        }

                        $('#msg')
                            .html(msg)
                            .addClass('message-success')
                            .removeClass('message-error');

                        $(document).off('click', '#editLeadBtn').on('click', '#editLeadBtn',
                            function() {
                                window.location.href = $(this).data('url');
                            });

                        $(document).off('click', '#requestEditBtn').on('click',
                            '#requestEditBtn',
                            function(e) {
                                e.preventDefault(); // stop form submission or reload

                                $.ajax({
                                    url: "{{ route('request.edit.btn') }}",
                                    type: "POST",
                                    data: {
                                        table_name: "leads",
                                        record_id: data.id,
                                        _token: "{{ csrf_token() }}"
                                    },
                                    success: function(response) {
                                        let alertBox = $('#soft-alert');

                                        // Reset alert classes
                                        alertBox.removeClass(
                                            'alert-success alert-warning alert-danger'
                                            );

                                        if (response.status === "exists") {
                                            alertBox.addClass('alert-warning')
                                                .text(response.message);
                                        } else if (response.status ===
                                            "success") {
                                            alertBox.addClass('alert-success')
                                                .text(response.message);
                                        } else {
                                            alertBox.addClass('alert-danger')
                                                .text("Unexpected response.");
                                        }

                                        // Show and auto-hide
                                        alertBox.fadeIn(300).delay(2500)
                                            .fadeOut(500);
                                    },
                                    error: function(xhr) {
                                        let alertBox = $('#soft-alert');
                                        alertBox.removeClass(
                                                'alert-success alert-warning')
                                            .addClass('alert-danger');
                                        alertBox.text(
                                                "Something went wrong. Please try again."
                                                )
                                            .fadeIn(300).delay(2500).fadeOut(
                                                500);
                                    }
                                });
                            });

                    },
                    error: function() {
                        $("#msg").html("");
                        $('#msg')
                            .html("No Record Found")
                            .addClass('message-error')
                            .removeClass('message-success');
                    }
                });
            });
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('.date', {
                enableTime: false, // Set to true if you want to include time
                dateFormat: "Y-m-d", // Set the desired date format
                altInput: true,
                altFormat: "F j, Y", // Set the format for the alternative input (displayed to the user)
                defaultDate: "{{ session('last_submit_date', now()) }}", // Set the default date to the last submitted date stored in the session, or use the current date as a fallback
                onChange: function(selectedDates, dateStr, instance) {
                    // Update the hidden input with the selected date in the desired format
                    document.getElementById('hiddenDate').value = dateStr;
                }
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap4' // Adjust this based on your Bootstrap version
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            // On change event of the dropdown
            $('#actionDropdown').change(function() {
                var selectedValue = $(this).val(); // Get the selected value
                var token = "{{ csrf_token() }}";
                $.ajax({
                    url: '{{ route('project.select_town') }}',
                    method: 'POST',
                    data: {
                        action: selectedValue,
                        _token: token // Include CSRF token
                    },
                    success: function(response) {
                        console.log(response); // Log the response if needed
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        console.error(error); // Log any errors
                    }
                });
            });
        });
    </script>



    @stack('script')
</body>

</html>
