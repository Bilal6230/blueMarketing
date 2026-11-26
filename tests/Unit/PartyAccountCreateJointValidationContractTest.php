<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PartyAccountCreateJointValidationContractTest extends TestCase
{
    public function test_party_create_and_edit_enforce_optional_exact_cnic_and_required_phone(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AccountingController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/accounting/child_accounts.blade.php'));
        $js = file_get_contents(public_path('js/party-account-inputs.js'));

        $this->assertGreaterThanOrEqual(2, substr_count($controller, "'cnic' => ['nullable', 'regex:/^\\d{13}$/']"));
        $this->assertGreaterThanOrEqual(2, substr_count($controller, "'phone' => ['required', 'string', 'min:1', 'max:30']"));
        $this->assertGreaterThanOrEqual(2, substr_count($view, 'maxlength="13" minlength="13" pattern="[0-9]{13}"'));
        $this->assertGreaterThanOrEqual(2, substr_count($view, 'name="phone" required maxlength="30"'));
        $this->assertStringContainsString('NIC must be exactly 13 digits.', $js);
        $this->assertStringContainsString("replace(/\\D/g,'').slice(0,13)", $js);
    }

    public function test_optional_head_select_creates_project_joint_record_atomically(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AccountingController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/accounting/child_accounts.blade.php'));
        $js = file_get_contents(public_path('js/party-account-inputs.js'));

        $this->assertStringContainsString("HeadAccounting::where('is_active', 1)->orderBy('name')", $controller);
        $this->assertStringContainsString("'head_accounting_id' => ['nullable', 'integer', 'exists:head_accountings,id']", $controller);
        $this->assertStringContainsString('DB::transaction(function () use ($data, $projectId)', $controller);
        $this->assertStringContainsString('Project::whereKey($projectId)->lockForUpdate()', $controller);
        $this->assertStringContainsString('ProjectHeadSubhead::create([', $controller);
        $this->assertStringContainsString("'project_id' => $projectId", $controller);
        $this->assertStringContainsString("'subhead_accounting_id' => $subhead->id", $controller);
        $this->assertStringContainsString("'plot_id' => null", $controller);
        $this->assertStringContainsString('id="party-create-head"', $view);
        $this->assertStringContainsString('name="head_accounting_id"', $view);
        $this->assertStringContainsString('new TomSelect(input', $js);
        $this->assertStringContainsString('dropdownParent:', $js);
    }
}
