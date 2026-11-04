<?php

namespace App\Http\Controllers;

use App\Models\PlotDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PlotDefinitionController extends Controller
{
    private const KINDS = ['category', 'type', 'size', 'unit'];

    private function projectId(): int
    {
        $id = (int) getSelectedTown();
        abort_if($id < 1, 409, 'Select a project before managing plot definitions.');
        return $id;
    }

    private function requireSchema(): void
    {
        abort_unless(Schema::hasTable('plot_definitions')
            && Schema::hasTable('plot_definition_audits')
            && Schema::hasTable('plot_definition_rate_revisions'),
            409, 'Plot definition setup migration is required before this page can be used.');
    }

    public function index()
    {
        $this->requireSchema();
        $definitions = PlotDefinition::where('project_id', $this->projectId())
            ->orderBy('kind')->orderBy('name')->get()->groupBy('kind');
        $units = $definitions->get('unit', collect());
        $rates = DB::table('plot_definition_rate_revisions as r')
            ->join('plot_definitions as d', 'd.id', '=', 'r.plot_definition_id')
            ->where('r.project_id', $this->projectId())->where('d.project_id', $this->projectId())->where('d.kind', 'size')
            ->whereDate('r.effective_from', '<=', now()->toDateString())
            ->orderByDesc('r.effective_from')->get()->groupBy('plot_definition_id');

        return view('admin.plots.definitions', compact('definitions', 'units', 'rates'))
            ->with('title', 'Plot Definitions');
    }

    public function store(Request $request)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'code' => ['required', 'string', 'max:40', Rule::unique('plot_definitions')->where(fn ($q) => $q->where('project_id', $projectId)->where('kind', $request->input('kind')))],
            'name' => ['required', 'string', 'max:120'],
            'legacy_value' => ['nullable', 'integer', 'min:1', 'max:3'],
            'unit_code' => ['nullable', 'string', 'max:20'],
            'size_value' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'unit_definition_id' => ['nullable', 'integer'],
            'rate_per_unit' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'effective_from' => ['nullable', 'date'],
        ]);

        if ($data['kind'] === 'type' && !in_array((int) ($data['legacy_value'] ?? 0), [1, 2], true)) {
            throw ValidationException::withMessages(['legacy_value' => 'Plot types must map to the existing Residential or Commercial type.']);
        }
        if ($data['kind'] === 'unit' && empty($data['unit_code'])) {
            throw ValidationException::withMessages(['unit_code' => 'Enter a short unit symbol/code.']);
        }
        if ($data['kind'] === 'size') {
            if (empty($data['size_value']) || empty($data['unit_definition_id']) || !isset($data['rate_per_unit'], $data['effective_from'])) {
                throw ValidationException::withMessages(['kind' => 'Size definitions need a size, unit, rate, and rate effective date.']);
            }
            $unit = PlotDefinition::where('project_id', $projectId)->where('kind', 'unit')
                ->where('is_active', true)->find($data['unit_definition_id']);
            abort_unless($unit, 422, 'Select an active unit from this project.');
        }
        if ($data['kind'] === 'type' && PlotDefinition::where('project_id', $projectId)->where('kind', 'type')
            ->where('legacy_value', $data['legacy_value'])->exists()) {
            throw ValidationException::withMessages(['legacy_value' => 'That legacy plot type is already defined for this project.']);
        }

        DB::transaction(function () use ($data, $projectId, $request): void {
            // Serialize definition creation per project and repeat checks under
            // the lock; request-time validation alone is vulnerable to races.
            abort_unless(DB::table('projects')->where('id', $projectId)->lockForUpdate()->first(), 404,
                'Selected project was not found.');
            abort_if(PlotDefinition::where('project_id', $projectId)->where('kind', $data['kind'])
                ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($data['code']))])->exists(),
                422, 'A definition with this code already exists in this project.');

            if ($data['kind'] === 'type') {
                abort_if(PlotDefinition::where('project_id', $projectId)->where('kind', 'type')
                    ->where('legacy_value', $data['legacy_value'])->exists(),
                    422, 'That legacy plot type is already defined for this project.');
            }
            if ($data['kind'] === 'size') {
                abort_unless(PlotDefinition::where('project_id', $projectId)->where('kind', 'unit')
                    ->where('is_active', true)->lockForUpdate()->whereKey($data['unit_definition_id'])->exists(),
                    422, 'Select an active unit from this project.');
            }

            $definition = PlotDefinition::create([
                'project_id' => $projectId, 'kind' => $data['kind'], 'code' => strtoupper($data['code']),
                'name' => $data['name'], 'legacy_value' => $data['legacy_value'] ?? null,
                'unit_code' => $data['kind'] === 'unit' ? $data['unit_code'] : null,
                'size_value' => $data['kind'] === 'size' ? $data['size_value'] : null,
                'unit_definition_id' => $data['kind'] === 'size' ? $data['unit_definition_id'] : null,
                'is_active' => true, 'created_by' => $request->user()->id,
            ]);
            if ($data['kind'] === 'size') {
                DB::table('plot_definition_rate_revisions')->insert([
                    'plot_definition_id' => $definition->id, 'project_id' => $projectId,
                    'rate_per_unit' => $data['rate_per_unit'],
                    'effective_from' => $data['effective_from'], 'changed_by' => $request->user()->id,
                    'created_at' => now(),
                ]);
            }
            $this->audit($definition, 'created', null, $definition->only(['kind', 'code', 'name', 'size_value', 'unit_definition_id', 'unit_code', 'legacy_value']));
        });

        return back()->with('success', 'Plot definition created.');
    }

    public function update(Request $request, int $definitionId)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($definitionId, $projectId, $data, $request): void {
            $definition = PlotDefinition::where('project_id', $projectId)->lockForUpdate()->findOrFail($definitionId);
            $old = $definition->only(['name']);
            $definition->name = $data['name'];
            $definition->updated_by = $request->user()->id;
            $definition->save();
            $this->audit($definition, 'renamed', $old, $definition->only(['name']), $data['reason']);
        });

        return back()->with('success', 'Plot definition updated.');
    }

    public function status(Request $request, int $definitionId)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($definitionId, $projectId, $data, $request): void {
            $definition = PlotDefinition::where('project_id', $projectId)->lockForUpdate()->findOrFail($definitionId);
            if ($definition->is_active === (bool) $data['is_active']) return;
            $old = ['is_active' => $definition->is_active];
            $definition->is_active = (bool) $data['is_active'];
            $definition->updated_by = $request->user()->id;
            $definition->save();
            $this->audit($definition, 'status_changed', $old, ['is_active' => $definition->is_active], $data['reason']);
        });

        return back()->with('success', 'Plot definition status updated.');
    }

    public function rate(Request $request, int $definitionId)
    {
        $this->requireSchema();
        $projectId = $this->projectId();
        $data = $request->validate([
            'rate_per_unit' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'effective_from' => ['required', 'date'], 'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($definitionId, $projectId, $data, $request): void {
            $definition = PlotDefinition::where('project_id', $projectId)->where('kind', 'size')
                ->lockForUpdate()->findOrFail($definitionId);
            $exists = DB::table('plot_definition_rate_revisions')->where('plot_definition_id', $definition->id)
                ->where('effective_from', $data['effective_from'])->exists();
            if ($exists) throw ValidationException::withMessages(['effective_from' => 'A rate already exists for this effective date.']);
            DB::table('plot_definition_rate_revisions')->insert([
                'plot_definition_id' => $definition->id, 'project_id' => $projectId,
                'rate_per_unit' => $data['rate_per_unit'],
                'effective_from' => $data['effective_from'], 'changed_by' => $request->user()->id,
                'created_at' => now(),
            ]);
            $this->audit($definition, 'rate_changed', null, [
                'rate_per_unit' => $data['rate_per_unit'], 'effective_from' => $data['effective_from'],
            ], $data['reason']);
        });

        return back()->with('success', 'Effective-dated plot size rate recorded.');
    }

    private function audit(PlotDefinition $definition, string $action, ?array $old, ?array $new, ?string $reason = null): void
    {
        DB::table('plot_definition_audits')->insert([
            'project_id' => $definition->project_id, 'plot_definition_id' => $definition->id,
            'action' => $action, 'old_values' => $old ? json_encode($old, JSON_THROW_ON_ERROR) : null,
            'new_values' => $new ? json_encode($new, JSON_THROW_ON_ERROR) : null,
            'reason' => $reason, 'actor_id' => auth()->id(), 'created_at' => now(),
        ]);
    }
}
