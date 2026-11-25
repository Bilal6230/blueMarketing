<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\RolePermissionCatalog;
use PHPUnit\Framework\TestCase;

final class Milestone02BookingLabourPermissionsContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value=file_get_contents(dirname(__DIR__,3).'/'.$path);
        self::assertIsString($value);
        return $value;
    }

    public function test_booking_has_four_real_actions_and_independent_price_right(): void
    {
        $catalog=$this->source('app/Support/Finance/RolePermissionCatalog.php');
        $migration=$this->source('database/migrations/2026_11_25_110000_register_booking_permissions.php');
        foreach (['read booking','create booking','update booking','delete booking','update booking price','view booking report'] as $name) {
            self::assertSame('booking',RolePermissionCatalog::generalModule($name),$name);
        }
        self::assertSame('Bookings / Plot Sales',RolePermissionCatalog::generalModuleLabel('booking'));
        foreach (['read booking','create booking','update booking','delete booking'] as $name) {
            self::assertStringContainsString($name,$migration);
        }
        self::assertStringContainsString("contains('read plot')",$migration);
        self::assertStringContainsString("contains('create plot')",$migration);
        self::assertStringContainsString("contains('update plot')",$migration);
        self::assertStringNotContainsString('Schema::create(',$migration);
        self::assertStringContainsString("assign lead", $catalog);
    }

    public function test_booking_route_guards_and_ui_do_not_inherit_plot_crud_rights(): void
    {
        $routes=$this->source('routes/web.php');
        $view=$this->source('resources/views/admin/booking/index.blade.php');
        $edit=$this->source('resources/views/admin/booking/edit.blade.php');
        $controller=$this->source('app/Http/Controllers/BookingController.php');
        foreach ([
            'booking.plot.index'=>'permission:read booking',
            'booking.plot.sale'=>'permission:create booking',
            'bookings.store'=>'permission:create booking',
            'booking.cancel'=>'permission:update booking',
            'bookings.transfer'=>'permission:update booking',
            'booking.schedule.form'=>'permission:read booking',
            'booking.destroy'=>'permission:delete booking',
            'booking.plot.recovery_list'=>'permission:read booking',
        ] as $name=>$permission) {
            self::assertStringContainsString(
                "middleware(['".$permission."'])->name('".$name."')",$routes,$name
            );
        }
        self::assertStringContainsString(
            "permission:update booking|update booking price",$routes
        );
        self::assertStringContainsString("@can('create booking')",$view);
        self::assertStringContainsString("@can('update booking')",$view);
        self::assertStringContainsString("@can('update booking price')",$view);
        self::assertStringContainsString("@can('delete booking')",$view);
        self::assertStringContainsString("@can('view booking report')",$view);
        self::assertStringContainsString("can('update booking')",$edit);
        self::assertStringContainsString("Auth::user()->can('update booking')",$controller);
    }

    public function test_sidebar_has_separate_booking_plot_inventory_and_report_permissions(): void
    {
        $view=$this->source('resources/views/admin/layouts/sidebar-menu.blade.php');
        self::assertStringContainsString("@can('read booking')",$view);
        self::assertStringContainsString("route('booking.plot.index')",$view);
        self::assertStringContainsString("@can('view booking report')",$view);
        self::assertStringContainsString("route('booking.customer.report.form')",$view);
        self::assertStringContainsString("@can('read plot')",$view);
        self::assertStringContainsString("route('project.plot.index')",$view);
        self::assertStringContainsString("@can('view inventory')",$view);
        self::assertStringContainsString("route('booking.plot.inventory')",$view);
    }

    public function test_labour_and_attendance_are_distinct_revocable_modules(): void
    {
        $routes=$this->source('routes/web.php');
        $migration=$this->source('database/migrations/2026_11_25_111000_align_labour_attendance_role_grants.php');
        $page=$this->source('resources/views/admin/labours/index.blade.php');
        $board=$this->source('resources/views/admin/labours/attn-board.blade.php');
        $controller=$this->source('app/Http/Controllers/LabourController.php');
        foreach ([
            'labours.index'=>'permission:read labour',
            'labours.store'=>'permission:create labour',
            'labours.update'=>'permission:update labour',
            'labours.destroy'=>'permission:delete labour',
            'labours.attendance'=>'permission:create attendance',
            'attendance.week.load'=>'permission:read attendance',
            'labours.report'=>'permission:read attendance',
        ] as $name=>$permission) {
            self::assertStringContainsString(
                "middleware('".$permission."')->name('".$name."')",$routes,$name
            );
        }
        self::assertStringNotContainsString('permission:read attendance|read labour',$routes);
        self::assertStringNotContainsString('permission:create attendance|create labour',$routes);
        self::assertStringContainsString("contains('read labour')",$migration);
        self::assertStringContainsString("contains('create labour')",$migration);
        self::assertStringContainsString("@can('read attendance')",$page);
        self::assertStringContainsString("@can('create attendance')",$page);
        self::assertStringContainsString("@can('create labour')",$page);
        self::assertStringContainsString("@can('update labour')",$page);
        self::assertStringContainsString("can('read attendance')",$controller);
        self::assertStringContainsString("can('create attendance')",$board);
        foreach(['labours.payment','labours.payment.details','labours.payment.update','labours.payment.delete'] as $name) {
            self::assertStringContainsString("name('".$name."')",$routes);
        }
    }

    public function test_obsolete_module_toggles_are_hidden_but_assigned_grants_are_preserved(): void
    {
        $controller=$this->source('app/Http/Controllers/RoleController.php');
        self::assertSame('legacy',RolePermissionCatalog::category('stock module'));
        self::assertSame('legacy',RolePermissionCatalog::category('labour module'));
        self::assertSame('lead',RolePermissionCatalog::generalModule('assign lead'));
        self::assertSame('Leads / CRM',RolePermissionCatalog::generalModuleLabel('lead'));
        self::assertSame('Labour Master',RolePermissionCatalog::generalModuleLabel('labour'));
        self::assertSame('Attendance',RolePermissionCatalog::generalModuleLabel('attendance'));
        self::assertStringContainsString('RolePermissionCatalog::isUnusedModuleToggle($name)',$controller);
        self::assertStringContainsString("array_merge(\$available,\$hidden)",$controller);
    }
}
