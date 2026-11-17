<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Providers\FinanceApiServiceProvider;
use App\Services\Finance\StockProjectApprovalWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Actual HTTP middleware -> controller -> typed Finance posting gateway integration.
 * SQLite :memory: only. No external endpoints, V1 mutations or live DB.
 *
 * Unified finance routes are registered with the normal application.
 * Each request requires authenticated project-scoped finance permissions.
 */
final class FinanceApiHttpSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        // The real Spatie PermissionRegistrar registers a Gate::before
        // callback and queries permissions before the finance Gate handler.
        // Use the application's actual permission migration in :memory:
        // instead of mocking away a security-critical authorization layer.
        // No non-test database is ever reachable (DatabaseIsolation guard).
        require_once base_path('database/migrations/2022_03_19_102456_create_permission_tables.php');
        (new \CreatePermissionTables())->up();

        Schema::create('projects', function (Blueprint $t): void {
            $t->id();
            $t->boolean('is_active')->default(true);
        });
        foreach (['users', 'commision_vouchers', 'stock_entries',
            'stock_bills', 'customer_ledger', 'booking_vouchers', 'labour_ledgers'] as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->id();
            });
        }
        Schema::table('commision_vouchers', function (Blueprint $t): void {
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_number');
            $t->date('date');
            $t->string('status');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->decimal('total_debit', 15, 2);
            $t->decimal('total_credit', 15, 2);
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('commision_voucher_details', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('commision_voucher_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit', 15, 2);
            $t->decimal('credit', 15, 2);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
        });
        Schema::create('journal_vouchers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('type');
            $t->unsignedBigInteger('voucher_number');
            $t->date('date');
            $t->decimal('total_debit', 15, 2);
            $t->decimal('total_credit', 15, 2);
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('journal_voucher_details', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('journal_voucher_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit', 15, 2);
            $t->decimal('credit', 15, 2);
            $t->text('description')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        (require base_path('database/finance_legacy_migrations/2026_10_30_000100_create_finance_finance_posting_core.php'))->up();
        (require base_path('database/finance_legacy_migrations/2026_10_31_000110_create_finance_commission_detail_links.php'))->up();
        DB::table('projects')->insert([
            ['id' => 1, 'is_active' => 1],
            ['id' => 2, 'is_active' => 1],
        ]);
        // The authenticated mock has User::id=1. Finance batches preserve
        // actor attribution via a REAL posted_by -> users.id foreign key;
        // create that same physical user in this isolated memory fixture.
        // Never bypass or disable the FK to make a posting test pass.
        DB::table('users')->insert(['id' => 1]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1],
            ['id' => 102, 'project_id' => 1],
            ['id' => 201, 'project_id' => 2],
        ]);
        DB::table('commision_vouchers')->insert([
            'id' => 41, 'project_id' => 1, 'voucher_number' => 'CV-41',
            'date' => '2026-10-30', 'status' => 'approved',
            'total_debit' => '35.00', 'total_credit' => '35.00',
        ]);
        DB::table('commision_voucher_details')->insert([
            ['id' => 411, 'commision_voucher_id' => 41, 'account_id' => 101,
                'debit' => '35.00', 'credit' => '0.00'],
            ['id' => 412, 'commision_voucher_id' => 41, 'account_id' => 102,
                'debit' => '0.00', 'credit' => '35.00'],
        ]);
        DB::table('journal_vouchers')->insert([
            'id' => 51, 'project_id' => 1, 'type' => 'JV', 'voucher_number' => 51,
            'date' => '2026-10-30', 'total_debit' => '125.50',
            'total_credit' => '125.50',
        ]);
        DB::table('journal_voucher_details')->insert([
            [
                'id' => 511, 'journal_voucher_id' => 51, 'account_id' => 101,
                'debit' => '125.50', 'credit' => '0.00',
                'description' => 'finance exact debit',
            ],
            [
                'id' => 512, 'journal_voucher_id' => 51, 'account_id' => 102,
                'debit' => '0.00', 'credit' => '125.50',
                'description' => 'finance exact credit',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function useFinanceApi(bool $allowWrite = false): void
    {
        (new FinanceApiServiceProvider($this->app))->boot();
    }

    private function authenticate(bool $superadmin = true, int $projectId = 1): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->project_id = $projectId;
        $user->shouldReceive('hasRole')->andReturn($superadmin);
        $this->actingAs($user, 'sanctum');
    }

    public function test_unified_finance_routes_require_authentication_without_feature_flags(): void
    {
        $this->getJson('/api/finance/projects/1/trial-balance')->assertUnauthorized();
        $this->postJson('/api/finance/projects/1/postings', [])->assertUnauthorized();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_enabled_routes_require_sanctum_authentication(): void
    {
        $this->useFinanceApi();
        $this->getJson('/api/finance/projects/1/trial-balance')
            ->assertUnauthorized();
    }

    public function test_local_superadmin_can_read_typed_reports_and_csv(): void
    {
        $this->useFinanceApi();
        $this->authenticate();
        $base = '/api/finance/projects/1';

        $this->getJson($base . '/trial-balance')
            ->assertOk()->assertJsonPath('scope', 'typed_finance_postings_only')
            ->assertJsonPath('total_period_debit', '0.00');
        $this->getJson($base . '/summary')
            ->assertOk()->assertJsonPath('posting_batch_count', 0);
        $this->getJson($base . '/voucher-register')
            ->assertOk()->assertJsonCount(0, 'documents');
        $this->getJson($base . '/accounts/101/statement')
            ->assertOk()->assertJsonPath('closing_balance', '0.00');
        $this->get($base . '/trial-balance/export')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get($base . '/accounts/101/statement/export')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_non_superadmin_cannot_read_other_project_or_bypass_gate(): void
    {
        $this->useFinanceApi();
        $this->authenticate(false, 1);
        $this->getJson('/api/finance/projects/2/trial-balance')
            ->assertForbidden();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_project_membership_still_applies_when_future_read_gate_allows_non_admin(): void
    {
        $this->useFinanceApi();
        // Simulate an explicitly approved future read role. Project membership
        // must be independently enforced AFTER the Gate allows access.
        Gate::define('finance-read', static fn ($user): bool => true);
        $this->authenticate(false, 1);
        $this->getJson('/api/finance/projects/1/trial-balance')
            ->assertOk();
        $this->getJson('/api/finance/projects/2/trial-balance')
            ->assertForbidden();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_invalid_posting_payload_does_not_mutate_accounting(): void
    {
        $this->useFinanceApi();
        $this->authenticate();
        $this->postJson('/api/finance/projects/1/postings', [])->assertUnprocessable();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_source_scoped_void_requires_an_existing_posting(): void
    {
        $this->useFinanceApi();
        $this->authenticate();
        $this->postJson('/api/finance/projects/1/vouchers/journal/51/void', [
            'request_key' => 'VOID-SOURCE-UNPOSTED-0001',
            'effective_date' => '2026-10-31', 'reason' => 'Correction',
        ])->assertStatus(409);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_source_scoped_void_refuses_unposted_journal_without_writes(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $this->postJson('/api/finance/projects/1/vouchers/journal/51/void', [
            'request_key' => 'VOID-SOURCE-UNPOSTED-0001',
            'effective_date' => '2026-10-31', 'reason' => 'Correction',
        ])->assertStatus(409)->assertJsonPath('error', 'FINANCE_VOUCHER_VOID_CONFLICT');
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_source_scoped_journal_void_posts_immutable_inverse_and_replays(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $base = '/api/finance/projects/1';
        $original = $this->postJson($base . '/postings', [
            'kind' => 'journal', 'source_id' => 51,
            'request_key' => 'HTTP-VOID-ORIGINAL-JV-0001',
        ])->assertCreated()->json('posting_batch_id');

        $void = [
            'request_key' => 'HTTP-VOID-JV-SOURCE-0001',
            'effective_date' => '2026-10-31',
            'reason' => 'Approved void correction',
        ];
        $endpoint = $base . '/vouchers/journal/51/void';
        $reversal = $this->postJson($endpoint, $void)
            ->assertCreated()->json('reversal_batch_id');
        $this->assertIsInt($reversal);
        $this->assertNotSame($original, $reversal);
        $this->assertSame($reversal, $this->postJson($endpoint, $void)
            ->assertCreated()->json('reversal_batch_id'));
        $this->postJson($endpoint, array_merge($void, [
            'request_key' => 'HTTP-VOID-JV-SECOND-0001',
        ]))->assertStatus(409);
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $reversal, 'reversal_of_batch_id' => $original,
        ]);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $this->getJson($base . '/trial-balance')
            ->assertOk()->assertJsonPath('balanced', true);
    }

    public function test_source_scoped_void_rejects_wrong_project_and_unposted_commission(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $void = [
            'request_key' => 'HTTP-VOID-PROJECT-GUARD-01',
            'effective_date' => '2026-10-31',
            'reason' => 'Approved correction',
        ];
        // Superadmins may inspect more than one project, but source 51 is
        // owned by project 1. Source isolation must fail with 409, never
        // accidentally reverse a project-1 posting through project 2.
        $this->postJson('/api/finance/projects/2/vouchers/journal/51/void', $void)
            ->assertStatus(409)
            ->assertJsonPath('error', 'FINANCE_VOUCHER_VOID_CONFLICT');
        $this->postJson('/api/finance/projects/1/vouchers/commission/41/void', $void)
            ->assertStatus(409);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_approved_commission_posts_and_voids_over_real_http_with_replay(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $base = '/api/finance/projects/1';
        $original = $this->postJson($base . '/postings', [
            'kind' => 'commission', 'source_id' => 41,
            'request_key' => 'HTTP-CV-SOURCE-POST-0001',
        ])->assertCreated()->json('posting_batch_id');

        $endpoint = $base . '/vouchers/commission/41/void';
        $payload = [
            'request_key' => 'HTTP-CV-SOURCE-VOID-0001',
            'effective_date' => '2026-10-31',
            'reason' => 'Approved CV correction',
        ];
        $reversal = $this->postJson($endpoint, $payload)
            ->assertCreated()->json('reversal_batch_id');
        $this->assertSame($reversal, $this->postJson($endpoint, $payload)
            ->assertCreated()->json('reversal_batch_id'));
        $this->postJson($endpoint, array_merge($payload, [
            'request_key' => 'HTTP-CV-SECOND-VOID-0001',
        ]))->assertStatus(409);
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $reversal, 'reversal_of_batch_id' => $original,
        ]);
        $this->assertDatabaseHas('commision_vouchers', [
            'id' => 41, 'status' => 'approved',
        ]);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
    }

    public function test_http_commission_draft_create_update_approve_post_and_immutability(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $base = '/api/finance/projects/1/commission-drafts';
        $legs = [
            ['account_id' => 101, 'debit' => '35.00', 'credit' => '0.00'],
            ['account_id' => 102, 'debit' => '0.00', 'credit' => '35.00'],
        ];
        $id = $this->postJson($base . '/create', [
            'voucher_number' => 'CV-HTTP-NEW-52',
            'date' => '2026-10-30', 'lines' => $legs,
        ])->assertCreated()->json('source_id');
        $this->assertIsInt($id);
        $this->postJson($base . '/update/' . $id, [
            'date' => '2026-10-31', 'lines' => $legs,
        ])->assertOk();
        $batch = $this->postJson($base . '/approve/' . $id, [
            'request_key' => 'HTTP-CV-DRAFT-APPROVE-001',
        ])->assertCreated()->json('posting_batch_id');
        $this->assertIsInt($batch);
        $this->postJson($base . '/update/' . $id, [
            'date' => '2026-10-31', 'lines' => $legs,
        ])->assertStatus(409);
        $this->postJson($base . '/cancel/' . $id)->assertStatus(409);
        $this->assertDatabaseHas('finance_posting_commission_sources', [
            'posting_batch_id' => $batch, 'commision_voucher_id' => $id,
        ]);
    }

    public function test_stock_project_approval_refuses_invalid_source(): void
    {
        $this->useFinanceApi();
        $this->authenticate();
        try {
            (new StockProjectApprovalWriter())->approve('stock_entry', 999, 1, 'OWNER-EVIDENCE-999');
            $this->fail('An invalid stock source was approved.');
        } catch (\DomainException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_explicit_write_flag_allows_journal_post_replay_and_reversal(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $base = '/api/finance/projects/1';
        $payload = [
            'kind' => 'journal',
            'source_id' => 51,
            'request_key' => 'HTTP-JOURNAL-ORIGINAL-0001',
        ];
        $original = $this->postJson($base . '/postings', $payload)
            ->assertCreated()->json('posting_batch_id');
        $this->assertIsInt($original);
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $original, 'project_id' => 1, 'posted_by' => 1,
        ]);
        $this->assertSame($original,
            $this->postJson($base . '/postings', $payload)
                ->assertCreated()->json('posting_batch_id'));

        $reversal = $this->postJson($base . '/reversals', [
            'original_batch_id' => $original,
            'request_key' => 'HTTP-JOURNAL-REVERSE-0001',
            'effective_date' => '2026-10-31',
            'reason' => 'Duplicate correction',
        ])->assertCreated()->json('reversal_batch_id');
        $this->assertIsInt($reversal);
        $this->assertNotSame($original, $reversal);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $this->getJson($base . '/trial-balance')
            ->assertOk()->assertJsonPath('balanced', true)
            ->assertJsonPath('total_period_debit', '251.00');
    }

    public function test_invalid_posting_payload_is_rejected_without_mutation(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $this->postJson('/api/finance/projects/1/postings', [
            'kind' => 'unknown', 'source_id' => 51,
            'request_key' => 'HTTP-INVALID-KIND-0001',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }
    public function test_sales_journal_sv_requires_explicit_kind_and_uses_physical_source_fk(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        DB::table('journal_vouchers')->where('id', 51)->update(['type' => 'SV']);

        $base = '/api/finance/projects/1';
        $invalid = [
            'kind' => 'journal', 'source_id' => 51,
            'request_key' => 'HTTP-SALES-AS-JV-BLOCK-01',
        ];
        $this->postJson($base . '/postings', $invalid)
            ->assertStatus(409)->assertJsonPath('error', 'FINANCE_POSTING_CONFLICT');
        $this->assertDatabaseCount('finance_posting_batches', 0);

        $payload = [
            'kind' => 'sales', 'source_id' => 51,
            'request_key' => 'HTTP-SALES-AS-SV-POST-01',
        ];
        $id = $this->postJson($base . '/postings', $payload)
            ->assertCreated()->json('posting_batch_id');
        $this->assertSame($id, $this->postJson($base . '/postings', $payload)
            ->assertCreated()->json('posting_batch_id'));
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $id, 'voucher_series' => 'SV', 'project_id' => 1,
        ]);
        $this->assertDatabaseHas('finance_posting_journal_sources', [
            'posting_batch_id' => $id, 'journal_voucher_id' => 51,
        ]);
        $this->getJson($base . '/voucher-register')
            ->assertOk()->assertJsonPath('documents.0.voucher_series', 'SV');
    }

    public function test_finance_readiness_scopes_exact_source_kind_to_project_and_no_writes(): void
    {
        $this->useFinanceApi(false);
        $this->authenticate();
        $base = '/api/finance/projects/1/sources/';
        $this->getJson($base . 'journal/51/readiness')
            ->assertOk()->assertJsonPath('eligible', true)
            ->assertJsonPath('source_kind', 'journal');
        $this->getJson($base . 'sales/51/readiness')
            ->assertOk()->assertJsonPath('eligible', false)
            ->assertJsonPath('reason_code', 'SOURCE_PROJECT_OR_TYPE_INVALID');
        $this->getJson($base . 'journal/999/readiness')
            ->assertOk()->assertJsonPath('eligible', false)
            ->assertJsonPath('reason_code', 'SOURCE_NOT_FOUND');
        $this->getJson($base . 'unknown/51/readiness')->assertNotFound();
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_finance_readiness_and_post_refuse_mismatched_sales_type(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        DB::table('journal_vouchers')->where('id', 51)->update(['type' => 'SV']);
        $base = '/api/finance/projects/1';
        $this->getJson($base . '/sources/sales/51/readiness')
            ->assertOk()->assertJsonPath('eligible', true);
        $this->getJson($base . '/sources/journal/51/readiness')
            ->assertOk()->assertJsonPath('eligible', false);

        $this->postJson($base . '/postings', [
            'kind' => 'journal', 'source_id' => 51,
            'request_key' => 'SV-READINESS-FAIL-0001',
        ])->assertStatus(409)->assertJsonPath('error', 'FINANCE_POSTING_CONFLICT');
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_source_readiness_does_not_expose_foreign_project_voucher_identity(): void
    {
        $this->useFinanceApi(true);
        $this->authenticate();
        $this->getJson('/api/finance/projects/2/sources/journal/51/readiness')
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('source_series', null)
            ->assertJsonPath('reason_code', 'SOURCE_NOT_FOUND');
        $this->postJson('/api/finance/projects/2/postings', [
            'kind' => 'journal', 'source_id' => 51,
            'request_key' => 'CROSS-PROJECT-VOUCHER-READINESS-1',
        ])->assertStatus(409);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

}
