<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyEvidenceReleaseGate;
use Illuminate\Console\Command;

final class AuditLegacyReleaseGate extends Command
{
    protected $signature='finance:audit-legacy-release-gate {manifest : JSON groups with coverage and conflict reports}';
    protected $description='Read-only consolidated review-scope gate; never authorizes financial mutation';
    public function handle(LegacyEvidenceReleaseGate $gate): int
    {
        $path=$this->argument('manifest');
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            $this->error('Existing readable JSON manifest required.'); return self::INVALID;
        }
        try { $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { $this->error('Invalid JSON.'); return self::INVALID; }
        if (!is_array($data) || !isset($data['groups']) || !is_array($data['groups'])) {
            $this->error('Manifest must contain groups array.'); return self::INVALID;
        }
        try { $result=$gate->assess($data['groups']); }
        catch (\InvalidArgumentException $e) { $this->error($e->getMessage()); return self::INVALID; }
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return $result['status']==='blocked' ? self::FAILURE : self::SUCCESS;
    }
}
