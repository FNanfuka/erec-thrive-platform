<?php

namespace App\Services;

use App\Models\ClimateHealthOutcome;
use App\Models\ClimateLocation;
use App\Models\IngestionRun;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Dhis2HealthOutcomeService
{
    public function sync(?string $period = null): array
    {
        $started = now();
        $run = IngestionRun::create(['source' => 'dhis2-health-outcomes', 'status' => 'running', 'started_at' => $started]);
        if (! config('services.dhis2.enabled') || ! config('services.dhis2.base_url')) {
            return $this->finish($run, 'disabled', 0, 'DHIS2 connector is not configured.', ['configured' => false]);
        }

        $period = $period ?: now()->subMonth()->format('Y-m');
        $indicators = collect(config('services.dhis2.indicators', []))->keyBy('id');
        $orgUnits = config('services.dhis2.org_units', []);
        if ($indicators->isEmpty() || $orgUnits === []) return $this->finish($run, 'failed', 0, 'Configure DHIS2_INDICATORS and DHIS2_ORG_UNITS before syncing.');

        $query = ['dimension' => 'dx:'.implode(';', $indicators->keys()).';pe:'.$period.';ou:'.implode(';', $orgUnits), 'displayProperty' => 'NAME', 'outputIdScheme' => 'CODE'];
        try {
            $request = Http::connectTimeout(5)->timeout(20)->acceptJson();
            $token = config('services.dhis2.token');
            $request = $token ? $request->withToken($token) : $request->withBasicAuth(config('services.dhis2.username'), config('services.dhis2.password'));
            $payload = $request->get(rtrim(config('services.dhis2.base_url'), '/').'/api/analytics.json', $query)->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            return $this->finish($run, 'failed', 0, $exception->getMessage());
        }

        $headers = collect($payload['headers'] ?? [])->pluck('name')->values();
        $records = 0;
        foreach ($payload['rows'] ?? [] as $row) {
            $data = $headers->combine($row)->all();
            $indicator = $indicators->get($data['dx'] ?? null);
            $location = $this->locationFor($data['ou'] ?? null);
            if (! $indicator || ! $location || ! is_numeric($data['value'] ?? null)) continue;
            $start = Carbon::createFromFormat('Y-m', $data['pe'] ?? $period)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            ClimateHealthOutcome::updateOrCreate(
                ['climate_location_id' => $location->id, 'indicator' => $indicator['indicator'], 'age_group' => $indicator['age_group'] ?? 'under_5', 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'source' => 'dhis2'],
                ['value' => (float) $data['value'], 'unit' => $indicator['unit'] ?? 'reported_cases', 'quality_flag' => 'reported', 'metadata' => ['dhis2_data_element' => $data['dx'], 'dhis2_org_unit' => $data['ou'], 'period' => $data['pe'] ?? $period]]
            );
            $records++;
        }

        return $this->finish($run, 'complete', $records, null, ['period' => $period, 'rows_received' => count($payload['rows'] ?? [])]);
    }

    public function status(): array
    {
        $latest = IngestionRun::where('source', 'dhis2-health-outcomes')->latest('started_at')->first();

        return ['configured' => (bool) config('services.dhis2.enabled') && (bool) config('services.dhis2.base_url'), 'indicators_configured' => count(config('services.dhis2.indicators', [])), 'org_units_configured' => count(config('services.dhis2.org_units', [])), 'latest_sync' => $latest?->only(['status', 'records_ingested', 'started_at', 'finished_at', 'error_message'])];
    }

    private function locationFor(?string $orgUnit): ?ClimateLocation
    {
        if (! $orgUnit) return null;
        return ClimateLocation::where('is_active', true)->where(function ($query) use ($orgUnit) {
            $query->where('external_id', $orgUnit)->orWhereJsonContains('metadata->dhis2_org_unit_id', $orgUnit);
        })->first();
    }

    private function finish(IngestionRun $run, string $status, int $records, ?string $error = null, array $metadata = []): array
    {
        $run->update(['status' => $status, 'records_ingested' => $records, 'finished_at' => now(), 'error_message' => $error, 'metadata' => $metadata]);
        return ['status' => $status, 'records_ingested' => $records, 'error' => $error, 'metadata' => $metadata];
    }
}
