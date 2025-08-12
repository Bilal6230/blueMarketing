<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="{{ route('dashboard') }}" class="brand-link">
        {{-- <img src="{{ asset(Setting::getValue('app_logo')) }}" alt="{{ Setting::getName('app_name') }}" class="brand-image img-circle elevation-3" style="opacity: .8"> --}}
        <img src="{{ asset('images/logo/blue-marketing-logo.png') }}" class="brand-image img-circle elevation-3"
            style="opacity: .8" alt="logo">
        <span class="brand-text font-weight-light">{{ Setting::getValue('app_short_name') }}</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                {{-- <img src="/storage/{{  Auth::user()->avatar }}" class="img-circle elevation-2" alt="User Image"> --}}
                <img src="{{ asset('images/logo/blue-marketing-logo.png') }}" alt="logo">
            </div>
            <div class="info">
                <a href="#" class="d-block">{{ Auth::user()->name }} <small></small></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu"
                data-accordion="false">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                        class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>@php $i = 1; @endphp
                @foreach ($modulemenus as $menus)
                    @if ($menus['menu_count'] == 1)
                        @foreach ($menus['menus'] as $menu)
                            @if ($i == 1)
                                @php
                                    $perm[] = $menu['permission'];
                                @endphp
                                @canany($perm)
                                    <li class="nav-header ml-2">MASTER DATA</li>
                                @endcanany
                            @endif
                            @can($menu['permission'])
                                <li class="nav-item">
                                    <a href="{{ route($menu['route']) }}"
                                        class="nav-link {{ request()->routeIs($menu['route']) == strtolower($menu['name']) ? 'active' : '' }}">
                                        <i class="nav-icon {{ $menu['icon'] }}"></i>
                                        <p>{{ $menu['name'] }}</p>
                                    </a>
                                </li>
                            @endcan
                            @php $i++; @endphp
                        @endforeach
                    @endif
                @endforeach
                @foreach ($modulemenus as $menus)
                    @if ($menus['menu_count'] > 1)
                        @foreach ($menus['menus'] as $menu)
                            @if (count($menus['menus']) > 1)
                                @if ($loop->iteration == 1)
                                    @php
                                        $perm[] = $menu['permission'];
                                    @endphp
                                    @canany($perm)
                                        <li class="nav-header ml-2">{{ strtoupper($menus['module']) }}</li>
                                    @endcanany
                                @endif
                                @can($menu['permission'])
                                    <li class="nav-item">
                                        <a href="{{ route($menu['route']) }}"
                                            class="nav-link {{ request()->routeIs($menu['route']) == strtolower($menu['name']) ? 'active' : '' }}">
                                            <i class="nav-icon {{ $menu['icon'] }}"></i>
                                            <p>{{ $menu['name'] }}</p>
                                        </a>
                                    </li>
                                @endcan
                            @endif
                        @endforeach
                    @endif
                @endforeach



                @canany(['read user', 'read role', 'read permission'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('accounting*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-plus"></i>
                            <p>Accounting<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('accounting*') ? 'block' : 'none' }};">
                            @can('read user')
                                <li class="nav-item">
                                    <a href="{{ route('accounting.head_index') }}"
                                        class="nav-link {{ request()->routeIs('accounting.head_index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Main Account</p>
                                    </a>
                                </li>
                            @endcan
                            @can('read user')
                                <li class="nav-item">
                                    <a href="{{ route('accounting.subhead_index') }}"
                                        class="nav-link {{ request()->routeIs('accounting.subhead_index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Child Account</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read user')
                                <li class="nav-item">
                                    <a href="{{ route('accounting.category_index') }}"
                                        class="nav-link {{ request()->routeIs('accounting.category_index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Joint Account</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                @canany(['read project', 'read sector', 'read area'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('project*', 'area*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Project Setup<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('project*', 'area*') ? 'block' : 'none' }};">
                            @can('read project')
                                <li class="nav-item">
                                    <a href="{{ route('project.index') }}"
                                        class="nav-link {{ request()->routeIs('project.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Project List</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read sector')
                                <li class="nav-item">
                                    <a href="{{ route('project.zone.index') }}"
                                        class="nav-link {{ request()->routeIs('project.zone.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Sectors</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read area')
                                <li class="nav-item">
                                    <a href="{{ route('area.index') }}"
                                        class="nav-link {{ request()->routeIs('area.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Area</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read plot')
                                <li class="nav-item">
                                    <a href="{{ route('project.plot.index') }}"
                                        class="nav-link {{ request()->routeIs('project.plot.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Plots</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                @canany(['read voucher', 'read slip', 'add slip', 'read jv'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('finance.voucher*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-ticket-alt"></i>
                            <p>Vouchers<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('finance.voucher*') ? 'block' : 'none' }};">


                            @can('read voucher')
                                <li class="nav-item">
                                    <a href="{{ route('finance.voucher.in') }}"
                                        class="nav-link {{ request()->routeIs('finance.voucher.in') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Cash In</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('finance.voucher.out') }}"
                                        class="nav-link {{ request()->routeIs('finance.voucher.out') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Cash out</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('finance.voucher.draft') }}"
                                        class="nav-link {{ request()->routeIs('finance.voucher.draft') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Draft Voucher</p>
                                    </a>
                                </li>




                                {{-- <li class="nav-item">
                                    <a href="{{ route('finance.voucher.index') }}" class="nav-link {{ request()->routeIs('finance.voucher.index') ? 'active':'' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Cash Voucher</p>
                                    </a>
                                </li> --}}
                            @endcan
                            @can('read jv')
                                <li class="nav-item">
                                    <a href="{{ route('journal.voucher.index') }}"
                                        class="nav-link {{ request()->routeIs('journal.voucher.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Journal Voucher</p>
                                    </a>
                                </li>
                            @endcan
                            @canany(['read slip', 'add slip'])
                                <li class="nav-item">
                                    <a href="{{ route('payment_schedule.cash') }}"
                                        class="nav-link {{ request()->routeIs('payment_schedule.cash') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Receive Payment</p>
                                    </a>
                                </li>
                            @endcan


                        </ul>
                    </li>
                @endcanany

                @canany(['report party_report', 'report cashbook', 'party_ledger'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('finance.reports*') ? 'active' : '' }}">
                            <i class="fas fa-print nav-icon"></i>
                            <p>Finance Reports<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('finance.reports*') ? 'block' : 'none' }};">

                            @can('report party_ledger')
                                <li class="nav-item">
                                    <a href="{{ route('finance.reports.details_index') }}"
                                        class="nav-link {{ request()->routeIs('finance.reports.details_index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Party Ledger</p>
                                    </a>
                                </li>
                            @endcan
                            @can('report cashbook')
                                <li class="nav-item">
                                    <a href="{{ route('finance.reports.show_ledger') }}"
                                        class="nav-link {{ request()->routeIs('finance.reports.show_ledger') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Cash Book</p>
                                    </a>
                                </li>
                            @endcan

                            @can('report party_report')
                                <li class="nav-item">
                                    <a href="{{ route('finance.reports.show_ledger_party') }}"
                                        class="nav-link {{ request()->routeIs('finance.reports.show_ledger_party') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Party Wise Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('finance.reports.show_ledger_head') }}"
                                        class="nav-link {{ request()->routeIs('finance.reports.show_ledger_head') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Head Wise Report</p>
                                    </a>
                                </li>
                            @endcan

                            @can('cheque report')
                                <li class="nav-item">
                                    <a href="{{ route('report.check') }}"
                                        class="nav-link {{ request()->routeIs('report.check') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Cheque Report</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                @canany(['read lead'])
                    <li class="nav-item">
                        <a href="#" class="nav-link  {{ request()->routeIs('crm*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-hands-helping"></i>
                            <p>CRM<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('crm*') ? 'block' : 'none' }};">
                            @can('read lead')
                                <li class="nav-item">
                                    <a href="{{ route('crm.lead.index') }}"
                                        class="nav-link {{ request()->routeIs('crm.lead.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Lead</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read lead')
                                <li class="nav-item">
                                    <a href="{{ route('lead.work') }}"
                                        class="nav-link {{ request()->routeIs('lead.work') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Start Work</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                @canany(['lead report', 'read lead', 'cheque report'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('report*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-paste"></i>
                            <p>CRM Reports<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('report*') ? 'block' : 'none' }};">
                            {{-- @can('read lead')
                                <li class="nav-item">
                                    <a href="{{ route('report.users') }}" class="nav-link">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Agent Report</p>
                                    </a>
                                </li>
                            @endcan --}}

                            @can('lead report')
                                <li class="nav-item">
                                    <a href="{{ route('report.lead.index') }}" class="nav-link">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Leads</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany




                @canany(['read project', 'read sector', 'read area', 'read plot', 'view inventory'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('booking*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Sales Reports<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('booking*') ? 'block' : 'none' }};">
                            @can('read plot')
                                <li class="nav-item">
                                    <a href="{{ route('booking.plot.index') }}"
                                        class="nav-link {{ request()->routeIs('booking.plot.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Plot Sale</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read plot')
                                <li class="nav-item">
                                    <a href="{{ route('booking.customer.report.form') }}"
                                        class="nav-link {{ request()->routeIs('booking.customer.report.form') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Customer Report</p>
                                    </a>
                                </li>
                            @endcan
                            @can('read plot')
                                <li class="nav-item">
                                    <a href="{{ route('booking.plot.recovery_list') }}"
                                        class="nav-link {{ request()->routeIs('booking.plot.recovery_list') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Project Report</p>
                                    </a>
                                </li>
                            @endcan
                            @can('view inventory')
                                <li class="nav-item">
                                    <a href="{{ route('booking.plot.inventory') }}"
                                        class="nav-link {{ request()->routeIs('booking.plot.inventory') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Inventory Report</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                @canany(['read user', 'read role', 'read permission'])
                    <li class="nav-item">
                        <a href="#" class="nav-link {{ request()->routeIs('user*') ? 'active' : '' }}">
                            <i class="fas fa-lock nav-icon"></i>
                            <p>Access<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('user*') ? 'block' : 'none' }};">
                            @can('read user')
                                <li class="nav-item">
                                    <a href="{{ route('user.index') }}"
                                        class="nav-link {{ request()->routeIs('user.index') ? 'active' : '' }}">
                                        <i class="fas fa-user nav-icon"></i>
                                        <p>User</p>
                                    </a>
                                </li>
                            @endcan
                            @can('read role')
                                <li class="nav-item">
                                    <a href="{{ route('role.index') }}"
                                        class="nav-link {{ request()->routeIs('role.index') ? 'active' : '' }}">
                                        <i class="fas fa-user-cog nav-icon"></i>
                                        <p>Role</p>
                                    </a>
                                </li>
                            @endcan
                            @can('read permission')
                                <li class="nav-item">
                                    <a href="{{ route('permission.index') }}"
                                        class="nav-link {{ request()->routeIs('permission.index') ? 'active' : '' }}">
                                        <i class="fas fa-unlock nav-icon"></i>
                                        <p>Permission</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany
                @canany(['read user', 'read role', 'read permission'])
                    <li class="nav-item">
                        <a href="{{ route('labours.index') }}"
                            class="nav-link {{ request()->routeIs('labours') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user"></i> {{-- Labour icon --}}
                            <p>Labour</p>
                        </a>
                    </li>
                @endcanany

                @canany(['read user', 'read role', 'read permission'])
                    <li class="nav-item">
                        <a href="{{ route('stocks.index') }}"
                            class="nav-link {{ request()->routeIs('stocks') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-boxes"></i> {{-- Stock icon --}}
                            <p>Stock</p>
                        </a>
                    </li>
                @endcanany

                {{-- @canany(['read expence'])
                    <li class="nav-item">
                        <a href="#" class="nav-link  {{ request()->routeIs('expence*') ? 'active':'' }}">
                            <i class="nav-icon fas fa-plus"></i>
                            <p>Expense<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview" style="display: {{ request()->routeIs('expence*') ? 'block':'none' }};">
                            @can('read expence')
                                <li class="nav-item">
                                    <a href="#" class="nav-link ">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Expense Categories </p>
                                    </a>
                                </li>
                            @endcan

                            @can('read expence')
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Add Expense</p>
                                    </a>
                                </li>
                            @endcan

                            @can('read expence')
                            <li class="nav-item">
                                <a href="{{ route('crm.lead.assign') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Today Expense Report</p>
                                </a>
                            </li>
                        @endcan


                        </ul>
                    </li>
                @endcanany --}}


                @canany(['read setting', 'filemanager'])
                    <li class="nav-item">
                        <a href="#" class="nav-link  {{ request()->routeIs('setting*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>Setting<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview"
                            style="display: {{ request()->routeIs('setting*') ? 'block' : 'none' }};">
                            @can('read setting')
                                <li class="nav-item">
                                    <a href="{{ route('setting.index') }}"
                                        class="nav-link {{ request()->routeIs('setting.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Setting</p>
                                    </a>
                                </li>
                            @endcan

                            @can('filemanager')
                                <li class="nav-item">
                                    <a href="{{ route('filemanager') }}"
                                        class="nav-link {{ request()->routeIs('filemanager') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>File Manager</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany






                <li class="nav-header"></li>
                <li class="nav-item">
                    <a href="#" class="nav-link bg-danger" data-toggle="modal" data-target="#modal-logout"
                        data-backdrop="static" data-keyboard="false">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <p>Sign Out</p>
                    </a>
                </li>
                <li class="nav-header"></li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
