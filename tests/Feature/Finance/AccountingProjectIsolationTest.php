<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\AccountingController;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountingProjectIsolationTest extends TestCase
{
    private function selectedProjectRequest(string $method, array $input): Request
    {
        $request = Request::create('/', $method, $input, ['selected_action' => 5]);
        $this->app->instance('request', $request);
        return $request;
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('A request for another project was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_accounting_entry_cannot_use_another_project_id(): void
    {
        $request = $this->selectedProjectRequest('POST', [
            'price' => '100.00', 'detail' => 'Test entry', 'accounts_id' => 10,
            'subaccounts_id' => 20, 'projects_id' => 6,
        ]);

        $this->assertForbidden(fn () => (new AccountingController())->store($request));
    }

    public function test_accountant_ledger_cannot_switch_project_through_query_string(): void
    {
        $request = $this->selectedProjectRequest('GET', ['project_id' => 6]);
        $user = new User();
        $user->id = 9;

        $this->assertForbidden(fn () => (new AccountingController())->AccountantLedgers($user, $request));
    }

    public function test_accounting_details_cannot_switch_project_through_filter(): void
    {
        $request = $this->selectedProjectRequest('GET', ['projects_id' => 6]);

        $this->assertForbidden(fn () => (new AccountingController())->details_index($request));
    }
}
