<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OnlineLifecycleHistory
{
    public function record(CustomerLedger $source, string $event, ?int $fromStatus, ?int $toStatus, ?string $note = null): void
    {
        if ((int) $source->payment_type !== 2 || !Schema::hasTable('finance_online_lifecycle_events')) {
            return;
        }

        DB::table('finance_online_lifecycle_events')->insert([
            'project_id' => (int) $source->project_id,
            'customer_ledger_id' => (int) $source->getKey(),
            'event_type' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'transaction_number' => $source->t_number,
            'bank_id' => $source->bank_id,
            'passing_date' => $source->passing_date,
            'note' => $note,
            'actor_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function events(int $projectId, int $sourceId)
    {
        if (!Schema::hasTable('finance_online_lifecycle_events')) {
            return collect();
        }

        return DB::table('finance_online_lifecycle_events')
            ->where('project_id', $projectId)->where('customer_ledger_id', $sourceId)
            ->orderBy('id')->get();
    }
}
