<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class BankSourceLinkedVoucherPrintTest extends TestCase
{
    public function test_print_route_is_read_only_guarded_and_restricted_to_online_and_cheque(): void
    {
        $route=Route::getRoutes()->getByName('finance.bank_source.linked_print');
        $this->assertNotNull($route);
        $this->assertSame(['GET','HEAD'],$route->methods());
        $this->assertStringContainsString('online|cheque',$route->wheres['method']);
        $this->assertContains('permission:read voucher',$route->gatherMiddleware());
    }

    public function test_print_report_renders_active_and_released_allocation_evidence(): void
    {
        $html=Blade::render(file_get_contents(resource_path('views/admin/finance/voucher/bank_source_linked_history_print.blade.php')),[
            'method'=>'online','projectId'=>3,'sourceId'=>7,
            'sourceLabel'=>'TX-777','sourceStatus'=>'confirmed','sourceAmount'=>'300.00',
            'rows'=>[
                ['voucher_number'=>'BOR-9','voucher_type'=>'BOR','voucher_id'=>9,
                 'amount'=>'100.00','approval_status'=>'approved','allocated_at'=>'2026-11-13',
                 'released'=>false,'release_action'=>null,'released_at'=>null,'release_reason'=>null],
                ['voucher_number'=>null,'voucher_type'=>'CR','voucher_id'=>12,
                 'amount'=>'200.00','approval_status'=>null,'allocated_at'=>'2026-11-12',
                 'released'=>true,'release_action'=>'posted_void_release',
                 'released_at'=>'2026-11-13','release_reason'=>'Audited void'],
            ]
        ]);
        foreach (['TX-777','BOR-9','CR / #12','300.00','Not released','Released','Audited void','Includes historical released allocations'] as $text){
            $this->assertStringContainsString($text,$html);
        }
    }

    public function test_print_view_escapes_untrusted_release_reason(): void
    {
        $html=Blade::render(file_get_contents(resource_path('views/admin/finance/voucher/bank_source_linked_history_print.blade.php')),[
            'method'=>'cheque','projectId'=>3,'sourceId'=>4,'sourceLabel'=>'CH-4',
            'sourceStatus'=>'cleared','sourceAmount'=>'100.00',
            'rows'=>[['voucher_number'=>'BOP-4','voucher_type'=>'BOP','voucher_id'=>4,
                'amount'=>'100.00','approval_status'=>'approved','allocated_at'=>'2026-11-13',
                'released'=>true,'release_action'=>'void','released_at'=>'2026-11-13',
                'release_reason'=>'<script>alert(1)</script>']]
        ]);
        $this->assertStringNotContainsString('<script>alert(1)</script>',$html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$html);
    }

    public function test_both_management_modals_offer_project_scoped_print_link(): void
    {
        foreach(['online'=>'online','cheque'=>'cheque'] as $folder=>$method){
            $content=file_get_contents(resource_path('views/admin/finance/'.$folder.'/intake.blade.php'));
            $this->assertStringContainsString("route('finance.bank_source.linked_print'",$content);
            $this->assertStringContainsString('id="'.$method.'-linked-vouchers-print"',$content);
            $this->assertStringContainsString('linkedPrintUrlTemplate.replace',$content);
        }
    }

    public function test_controller_requires_project_bound_source_and_uses_read_only_history_service(): void
    {
        $content=file_get_contents(app_path('Http/Controllers/Finance/BankSourceLinkedVoucherPrintController.php'));
        $this->assertStringContainsString("->where('project_id', $projectId)->where('id', $source)",$content);
        $this->assertStringContainsString('$history->forSource($projectId, $method, $source)', $content);
        $this->assertStringContainsString("private, no-store, max-age=0",$content);
        $this->assertStringNotContainsString("->insert(",$content);
        $this->assertStringNotContainsString("->update(",$content);
        $this->assertStringNotContainsString("->delete(",$content);
    }
}
