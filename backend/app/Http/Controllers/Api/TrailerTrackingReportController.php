<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrailerTrackingReportController extends Controller
{
    protected array $columnCache = [];

    public function owners(): JsonResponse
    {
        $query = DB::table('vehicle as v')
            ->select('v.owner')
            ->whereNotNull('v.owner')
            ->whereRaw("TRIM(v.owner) <> ''")
            ->distinct()
            ->orderBy('v.owner');

        if ($this->hasColumn('vehicle', 'is_deleted')) {
            $query->where('v.is_deleted', 0);
        }

        if ($this->hasColumn('vehicle', 'id_vehicle_type')) {
            $query->where('v.id_vehicle_type', 1);
        }

        $owners = $query->pluck('owner');

        return response()->json([
            'owners' => $owners,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $owner = trim((string) $request->query('owner', ''));

        if ($owner === '') {
            return response()->json([
                'rows' => [],
                'summary' => [
                    'total_trailers' => 0,
                    'total_load_count' => 0,
                    'total_load_revenue' => 0,
                    'trailers_with_driver_history' => 0,
                ],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        }

        $rowsQuery = DB::table('vehicle as v')
            ->selectRaw("
                v.id_vehicle,
                COALESCE(
                    NULLIF(TRIM(v.vehicle_number), ''),
                    NULLIF(TRIM(v.vehicle_name), ''),
                    CONCAT('Trailer #', v.id_vehicle)
                ) AS trailer_display,
                v.vehicle_number,
                v.vehicle_name,
                v.owner
            ")
            ->where('v.owner', $owner)
            ->orderBy('trailer_display');

        if ($this->hasColumn('vehicle', 'is_deleted')) {
            $rowsQuery->where('v.is_deleted', 0);
        }

        if ($this->hasColumn('vehicle', 'id_vehicle_type')) {
            $rowsQuery->where('v.id_vehicle_type', 1);
        }

        $rows = $rowsQuery->get();

        $trailerIds = $rows->pluck('id_vehicle')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $historyByTrailer = $this->buildDriverHistoryByTrailer($trailerIds, $startDate, $endDate);
        $loadDataByTrailer = $this->buildLoadDataByTrailer($rows, $startDate, $endDate);

        $rows = $rows->map(function ($row) use ($historyByTrailer, $loadDataByTrailer) {
            $trailerId = (int) $row->id_vehicle;
            $entries = $historyByTrailer[$trailerId] ?? [];
            $loadData = $loadDataByTrailer[$trailerId] ?? [
                    'load_count' => 0,
                    'load_revenue' => 0.0,
                    'recent_job' => null,
                ];

            $row->load_count = (int) $loadData['load_count'];
            $row->load_revenue = (float) $loadData['load_revenue'];
            $row->recent_job = $loadData['recent_job'];

            $row->driver_history_entries = $entries;
            $row->driver_history_count = count($entries);
            $row->driver_history_plain = implode(' | ', array_map(
                fn (array $entry) => sprintf(
                    '%s - %s - %s',
                    $entry['driver_name'],
                    $entry['assigned_date_display'],
                    $entry['unassigned_date_display']
                ),
                $entries
            ));

            return $row;
        });

        return response()->json([
            'rows' => $rows->values(),
            'summary' => [
                'total_trailers' => $rows->count(),
                'total_load_count' => (int) $rows->sum(fn ($row) => (int) $row->load_count),
                'total_load_revenue' => round((float) $rows->sum(fn ($row) => (float) $row->load_revenue), 2),
                'trailers_with_driver_history' => (int) $rows->filter(
                    fn ($row) => (int) $row->driver_history_count > 0
                )->count(),
            ],
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function vehicleEditor(int $vehicleId): JsonResponse
    {
        $vehicle = $this->loadVehicleRecord($vehicleId);

        if (!$vehicle) {
            return response()->json([
                'message' => 'Vehicle not found.',
            ], 404);
        }

        return response()->json([
            'vehicle' => $vehicle,
        ]);
    }

    public function updateVehicle(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->loadVehicleRecord($vehicleId);

        if (!$vehicle) {
            return response()->json([
                'message' => 'Vehicle not found.',
            ], 404);
        }

        $input = (array) $request->input('vehicle', []);
        $updates = [];

        $this->setVehicleTypeUpdate($updates, $input['vehicle_type'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['vin'], $input['vin'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['year', 'vehicle_year'], $input['year'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['make', 'vehicle_make'], $input['make'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['model', 'vehicle_model'], $input['model'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['color', 'vehicle_color'], $input['color'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['vehicle_number'], $input['vehicle_number'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['vehicle_name'], $input['vehicle_name'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['state', 'registration_state'], $input['registration_state'] ?? null);
        $this->setFirstExistingColumn($updates, 'vehicle', ['owner'], $input['owner'] ?? null);
        $this->setInsuranceProviderUpdates($updates, $input['insurance_provider'] ?? null);
        $this->setFirstExistingColumn(
            $updates,
            'vehicle',
            ['process_rental_and_billing', 'is_rental_billing_enabled'],
            array_key_exists('process_rental_and_billing', $input)
                ? (int) ((bool) $input['process_rental_and_billing'])
                : null
        );
        $this->setFirstExistingColumn($updates, 'vehicle', ['license_plate', 'system_plate'], $input['license_plate'] ?? null);

        if (!empty($updates)) {
            DB::table('vehicle')
                ->where('id_vehicle', $vehicleId)
                ->update($updates);
        }

        return response()->json([
            'message' => 'Vehicle updated successfully.',
            'vehicle' => $this->loadVehicleRecord($vehicleId),
        ]);
    }

    public function deleteVehicle(int $vehicleId): JsonResponse
    {
        $vehicle = $this->loadVehicleRecord($vehicleId);

        if (!$vehicle) {
            return response()->json([
                'message' => 'Vehicle not found.',
            ], 404);
        }

        if ($this->hasColumn('vehicle', 'is_deleted')) {
            DB::table('vehicle')
                ->where('id_vehicle', $vehicleId)
                ->update(['is_deleted' => 1]);
        } else {
            DB::table('vehicle')
                ->where('id_vehicle', $vehicleId)
                ->delete();
        }

        return response()->json([
            'message' => 'Vehicle deleted successfully.',
        ]);
    }

    public function driverEditor(int $contactId): JsonResponse
    {
        $record = $this->loadDriverEditorRecord($contactId);

        if (!$record) {
            return response()->json([
                'message' => 'Driver / contact not found.',
            ], 404);
        }

        return response()->json([
            'contact' => $record['contact'],
            'driver' => $record['driver'],
            'lookups' => $this->driverEditorLookups(),
        ]);
    }

    public function updateDriver(Request $request, int $contactId): JsonResponse
    {
        $record = $this->loadDriverEditorRecord($contactId);

        if (!$record) {
            return response()->json([
                'message' => 'Driver / contact not found.',
            ], 404);
        }

        $contactInput = (array) $request->input('contact', []);
        $driverInput = (array) $request->input('driver', []);

        DB::transaction(function () use ($contactId, $record, $contactInput, $driverInput) {
            $contactUpdates = $this->filterTableColumns('contact', [
                'first_name' => $this->nullIfBlank($contactInput['first_name'] ?? null),
                'last_name' => $this->nullIfBlank($contactInput['last_name'] ?? null),
                'phone_number' => $this->nullIfBlank($contactInput['phone_number'] ?? null),
                'email' => $this->nullIfBlank($contactInput['email'] ?? null),
                'address' => $this->nullIfBlank($contactInput['address'] ?? null),
                'state' => $this->nullIfBlank($contactInput['state'] ?? null),
            ]);

            if (!empty($contactUpdates)) {
                DB::table('contact')
                    ->where('id_contact', $contactId)
                    ->update($contactUpdates);
            }

            $driverUpdates = $this->filterTableColumns('driver', [
                'is_driver' => $driverInput['is_driver'] ?? 1,
                'driver_shift' => $driverInput['driver_shift'] ?? null,
                'spanish_language' => $driverInput['spanish_language'] ?? null,
                'id_vehicle' => $driverInput['id_vehicle'] ?? null,
                'id_trailer' => $driverInput['id_trailer'] ?? null,
                'id_device' => $this->nullIfBlank($driverInput['id_device'] ?? null),
                'mobile_app_pin' => $this->nullIfBlank($driverInput['mobile_app_pin'] ?? null),
                'status' => $driverInput['status'] ?? null,
                'id_carrier' => $driverInput['id_carrier'] ?? null,
                'idprojects' => $driverInput['idprojects'] ?? null,
                'tcs_fuel_card_number' => $this->nullIfBlank($driverInput['tcs_fuel_card_number'] ?? null),
                'tcs_fuel_card_pin' => $this->nullIfBlank($driverInput['tcs_fuel_card_pin'] ?? null),
                'tcs_fuel_card_limit' => $this->nullIfBlank($driverInput['tcs_fuel_card_limit'] ?? null),
                'tcs_fuel_card_last_updated' => $this->nullIfBlank($driverInput['tcs_fuel_card_last_updated'] ?? null),
            ]);

            $existingDriverId = $record['driver']['id_driver'] ?? null;

            if ($existingDriverId) {
                if (!empty($driverUpdates)) {
                    DB::table('driver')
                        ->where('id_driver', $existingDriverId)
                        ->update($driverUpdates);
                }

                return;
            }

            if (!empty($driverUpdates)) {
                DB::table('driver')->insert(array_merge(
                    ['id_contact' => $contactId],
                    $driverUpdates
                ));
            }
        });

        $fresh = $this->loadDriverEditorRecord($contactId);

        return response()->json([
            'message' => 'Driver / contact updated successfully.',
            'contact' => $fresh['contact'],
            'driver' => $fresh['driver'],
            'lookups' => $this->driverEditorLookups(),
        ]);
    }

    protected function driverEditorLookups(): array
    {
        return [
            'states' => $this->stateOptions(),
            'carriers' => $this->loadCarrierOptions(),
            'projects' => $this->loadProjectOptions(),
            'trailers' => $this->loadVehicleOptions(1),
            'trucks' => $this->loadVehicleOptions(2),
        ];
    }

    protected function loadVehicleRecord(int $vehicleId): ?array
    {
        $cols = $this->tableColumns('vehicle');

        $selects = ['id_vehicle'];

        foreach ([
                     'id_vehicle_type',
                     'vehicle_type',
                     'vin',
                     'year',
                     'vehicle_year',
                     'make',
                     'vehicle_make',
                     'model',
                     'vehicle_model',
                     'color',
                     'vehicle_color',
                     'vehicle_number',
                     'vehicle_name',
                     'state',
                     'registration_state',
                     'owner',
                     'insurance_provided_by',
                     'insurance_provider',
                     'process_rental_and_billing',
                     'is_rental_billing_enabled',
                     'license_plate',
                     'system_plate',
                     'is_deleted',
                 ] as $column) {
            if (isset($cols[$column])) {
                $selects[] = $column;
            }
        }

        $query = DB::table('vehicle')
            ->select($selects)
            ->where('id_vehicle', $vehicleId);

        if (isset($cols['is_deleted'])) {
            $query->where('is_deleted', 0);
        }

        $row = $query->first();

        if (!$row) {
            return null;
        }

        $row = (array) $row;

        $vehicleTypeRaw = $this->getFirstExistingValue($row, ['vehicle_type', 'id_vehicle_type']);
        $vehicleType = 'Trailer';

        if ($vehicleTypeRaw !== null) {
            if (is_numeric($vehicleTypeRaw)) {
                $vehicleType = ((int) $vehicleTypeRaw === 2) ? 'Truck' : 'Trailer';
            } else {
                $normalized = trim((string) $vehicleTypeRaw);
                $vehicleType = $normalized !== '' ? ucfirst(strtolower($normalized)) : 'Trailer';
            }
        }

        return [
            'id_vehicle' => $row['id_vehicle'] ?? null,
            'vehicle_type' => $vehicleType,
            'vin' => $this->getFirstExistingValue($row, ['vin']) ?? '',
            'year' => $this->getFirstExistingValue($row, ['year', 'vehicle_year']) ?? '',
            'make' => $this->getFirstExistingValue($row, ['make', 'vehicle_make']) ?? '',
            'model' => $this->getFirstExistingValue($row, ['model', 'vehicle_model']) ?? '',
            'color' => $this->getFirstExistingValue($row, ['color', 'vehicle_color']) ?? '',
            'vehicle_number' => $this->getFirstExistingValue($row, ['vehicle_number']) ?? '',
            'vehicle_name' => $this->getFirstExistingValue($row, ['vehicle_name']) ?? '',
            'registration_state' => $this->getFirstExistingValue($row, ['state', 'registration_state']) ?? '',
            'owner' => $this->getFirstExistingValue($row, ['owner']) ?? '',
            'insurance_provider' => $this->readInsuranceProviderValue($row),
            'process_rental_and_billing' => (bool) ($this->getFirstExistingValue($row, ['process_rental_and_billing', 'is_rental_billing_enabled']) ?? false),
            'license_plate' => $this->getFirstExistingValue($row, ['license_plate', 'system_plate']) ?? '',
        ];
    }

    protected function setVehicleTypeUpdate(array &$updates, mixed $value): void
    {
        if ($this->hasColumn('vehicle', 'id_vehicle_type')) {
            $normalized = strtolower(trim((string) $value));

            if ($normalized === 'truck') {
                $updates['id_vehicle_type'] = 2;
                return;
            }

            if ($normalized === 'trailer') {
                $updates['id_vehicle_type'] = 1;
                return;
            }

            if ($value !== null && $value !== '' && is_numeric($value)) {
                $updates['id_vehicle_type'] = (int) $value;
            }

            return;
        }

        if ($this->hasColumn('vehicle', 'vehicle_type')) {
            $updates['vehicle_type'] = $this->nullIfBlank($value);
        }
    }

    protected function setInsuranceProviderUpdates(array &$updates, mixed $value): void
    {
        if ($this->hasColumn('vehicle', 'insurance_provider')) {
            $numericValue = $this->insuranceProviderToInteger($value);

            if ($numericValue !== null) {
                $updates['insurance_provider'] = $numericValue;
            }
        }

        if ($this->hasColumn('vehicle', 'insurance_provided_by')) {
            $labelValue = $this->insuranceProviderToLabel($value);

            if ($labelValue !== null || $value === null || $value === '') {
                $updates['insurance_provided_by'] = $labelValue;
            }
        }
    }

    protected function readInsuranceProviderValue(array $row): string
    {
        if (array_key_exists('insurance_provided_by', $row) && $row['insurance_provided_by'] !== null && $row['insurance_provided_by'] !== '') {
            return (string) $row['insurance_provided_by'];
        }

        if (array_key_exists('insurance_provider', $row)) {
            return $this->insuranceProviderToLabel($row['insurance_provider']) ?? '';
        }

        return '';
    }

    protected function insuranceProviderToInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'rental agency' => 1,
            'carrier' => 2,
            'owner' => 3,
            'company' => 4,
            default => null,
        };
    }

    protected function insuranceProviderToLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            $normalized = trim((string) $value);
            return $normalized === '' ? null : $normalized;
        }

        return match ((int) $value) {
            1 => 'Rental Agency',
            2 => 'Carrier',
            3 => 'Owner',
            4 => 'Company',
            default => null,
        };
    }

    protected function setFirstExistingColumn(array &$updates, string $table, array $candidates, mixed $value): void
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($table, $column)) {
                $updates[$column] = is_bool($value) || is_int($value) ? $value : $this->nullIfBlank($value);
                return;
            }
        }
    }

    protected function getFirstExistingValue(array $row, array $candidates): mixed
    {
        foreach ($candidates as $column) {
            if (array_key_exists($column, $row)) {
                return $row[$column];
            }
        }

        return null;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        $columns = $this->tableColumns($table);
        return isset($columns[$column]);
    }

    protected function loadDriverEditorRecord(int $contactId): ?array
    {
        $contactCols = $this->tableColumns('contact');
        $driverCols = $this->tableColumns('driver');

        $selects = ['c.id_contact as contact_id'];

        foreach (['first_name', 'last_name', 'phone_number', 'email', 'address', 'state'] as $col) {
            if (isset($contactCols[$col])) {
                $selects[] = "c.$col as contact_$col";
            }
        }

        foreach ([
                     'id_driver',
                     'is_driver',
                     'driver_shift',
                     'spanish_language',
                     'id_vehicle',
                     'id_trailer',
                     'id_device',
                     'mobile_app_pin',
                     'status',
                     'id_carrier',
                     'idprojects',
                     'tcs_fuel_card_number',
                     'tcs_fuel_card_pin',
                     'tcs_fuel_card_limit',
                     'tcs_fuel_card_last_updated',
                 ] as $col) {
            if (isset($driverCols[$col])) {
                $selects[] = "d.$col as driver_$col";
            }
        }

        $query = DB::table('contact as c')
            ->leftJoin('driver as d', 'd.id_contact', '=', 'c.id_contact')
            ->selectRaw(implode(', ', $selects))
            ->where('c.id_contact', $contactId);

        if (isset($contactCols['is_deleted'])) {
            $query->where('c.is_deleted', 0);
        }

        $row = $query->first();

        if (!$row) {
            return null;
        }

        $row = (array) $row;

        return [
            'contact' => [
                'id_contact' => $row['contact_id'],
                'first_name' => $row['contact_first_name'] ?? '',
                'last_name' => $row['contact_last_name'] ?? '',
                'phone_number' => $row['contact_phone_number'] ?? '',
                'email' => $row['contact_email'] ?? '',
                'address' => $row['contact_address'] ?? '',
                'state' => $row['contact_state'] ?? '',
            ],
            'driver' => [
                'id_driver' => $row['driver_id_driver'] ?? null,
                'is_driver' => (int) ($row['driver_is_driver'] ?? 1),
                'driver_shift' => $row['driver_driver_shift'] ?? '',
                'spanish_language' => (int) ($row['driver_spanish_language'] ?? 0),
                'id_vehicle' => $row['driver_id_vehicle'] ?? null,
                'id_trailer' => $row['driver_id_trailer'] ?? null,
                'id_device' => $row['driver_id_device'] ?? '',
                'mobile_app_pin' => $row['driver_mobile_app_pin'] ?? '',
                'status' => $row['driver_status'] ?? null,
                'id_carrier' => $row['driver_id_carrier'] ?? null,
                'idprojects' => $row['driver_idprojects'] ?? null,
                'tcs_fuel_card_number' => $row['driver_tcs_fuel_card_number'] ?? '',
                'tcs_fuel_card_pin' => $row['driver_tcs_fuel_card_pin'] ?? '',
                'tcs_fuel_card_limit' => $row['driver_tcs_fuel_card_limit'] ?? '',
                'tcs_fuel_card_last_updated' => $row['driver_tcs_fuel_card_last_updated'] ?? '',
            ],
        ];
    }

    protected function loadVehicleOptions(int $vehicleType): array
    {
        $query = DB::table('vehicle as v')
            ->selectRaw("
                v.id_vehicle,
                COALESCE(
                    NULLIF(TRIM(v.vehicle_number), ''),
                    NULLIF(TRIM(v.vehicle_name), ''),
                    CONCAT('Vehicle #', v.id_vehicle)
                ) AS label
            ");

        if ($this->hasColumn('vehicle', 'is_deleted')) {
            $query->where('v.is_deleted', 0);
        }

        if ($this->hasColumn('vehicle', 'id_vehicle_type')) {
            $query->where('v.id_vehicle_type', $vehicleType);
        }

        return $query
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id_vehicle,
                'label' => $row->label,
            ])
            ->all();
    }

    protected function loadCarrierOptions(): array
    {
        if (!Schema::hasTable('carrier')) {
            return [];
        }

        $cols = $this->tableColumns('carrier');

        if (!isset($cols['id_carrier']) || !isset($cols['carrier_name'])) {
            return [];
        }

        $query = DB::table('carrier')
            ->select('id_carrier', 'carrier_name')
            ->whereNotNull('carrier_name')
            ->whereRaw("TRIM(carrier_name) <> ''")
            ->orderBy('carrier_name');

        if (isset($cols['is_deleted'])) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->get()
            ->map(fn ($row) => [
                'id_carrier' => (int) $row->id_carrier,
                'carrier_name' => (string) $row->carrier_name,
            ])
            ->all();
    }

    protected function loadProjectOptions(): array
    {
        if (!Schema::hasTable('projects')) {
            return [];
        }

        $cols = $this->tableColumns('projects');

        if (!isset($cols['idprojects']) || !isset($cols['projectname'])) {
            return [];
        }

        $query = DB::table('projects')
            ->select('idprojects', 'projectname')
            ->whereNotNull('projectname')
            ->whereRaw("TRIM(projectname) <> ''");

        if (isset($cols['is_deleted'])) {
            $query->where('is_deleted', 0);
        }

        if (isset($cols['orderby'])) {
            $query->orderBy('orderby');
        }

        $query->orderBy('projectname');

        return $query
            ->get()
            ->map(fn ($row) => [
                'idprojects' => (int) $row->idprojects,
                'projectname' => (string) $row->projectname,
            ])
            ->all();
    }

    protected function stateOptions(): array
    {
        return [
            ['state_code' => '', 'state_name' => ''],
            ['state_code' => 'AL', 'state_name' => 'Alabama'],
            ['state_code' => 'AK', 'state_name' => 'Alaska'],
            ['state_code' => 'AZ', 'state_name' => 'Arizona'],
            ['state_code' => 'AR', 'state_name' => 'Arkansas'],
            ['state_code' => 'CA', 'state_name' => 'California'],
            ['state_code' => 'CO', 'state_name' => 'Colorado'],
            ['state_code' => 'CT', 'state_name' => 'Connecticut'],
            ['state_code' => 'DE', 'state_name' => 'Delaware'],
            ['state_code' => 'FL', 'state_name' => 'Florida'],
            ['state_code' => 'GA', 'state_name' => 'Georgia'],
            ['state_code' => 'HI', 'state_name' => 'Hawaii'],
            ['state_code' => 'ID', 'state_name' => 'Idaho'],
            ['state_code' => 'IL', 'state_name' => 'Illinois'],
            ['state_code' => 'IN', 'state_name' => 'Indiana'],
            ['state_code' => 'IA', 'state_name' => 'Iowa'],
            ['state_code' => 'KS', 'state_name' => 'Kansas'],
            ['state_code' => 'KY', 'state_name' => 'Kentucky'],
            ['state_code' => 'LA', 'state_name' => 'Louisiana'],
            ['state_code' => 'ME', 'state_name' => 'Maine'],
            ['state_code' => 'MD', 'state_name' => 'Maryland'],
            ['state_code' => 'MA', 'state_name' => 'Massachusetts'],
            ['state_code' => 'MI', 'state_name' => 'Michigan'],
            ['state_code' => 'MN', 'state_name' => 'Minnesota'],
            ['state_code' => 'MS', 'state_name' => 'Mississippi'],
            ['state_code' => 'MO', 'state_name' => 'Missouri'],
            ['state_code' => 'MT', 'state_name' => 'Montana'],
            ['state_code' => 'NE', 'state_name' => 'Nebraska'],
            ['state_code' => 'NV', 'state_name' => 'Nevada'],
            ['state_code' => 'NH', 'state_name' => 'New Hampshire'],
            ['state_code' => 'NJ', 'state_name' => 'New Jersey'],
            ['state_code' => 'NM', 'state_name' => 'New Mexico'],
            ['state_code' => 'NY', 'state_name' => 'New York'],
            ['state_code' => 'NC', 'state_name' => 'North Carolina'],
            ['state_code' => 'ND', 'state_name' => 'North Dakota'],
            ['state_code' => 'OH', 'state_name' => 'Ohio'],
            ['state_code' => 'OK', 'state_name' => 'Oklahoma'],
            ['state_code' => 'OR', 'state_name' => 'Oregon'],
            ['state_code' => 'PA', 'state_name' => 'Pennsylvania'],
            ['state_code' => 'RI', 'state_name' => 'Rhode Island'],
            ['state_code' => 'SC', 'state_name' => 'South Carolina'],
            ['state_code' => 'SD', 'state_name' => 'South Dakota'],
            ['state_code' => 'TN', 'state_name' => 'Tennessee'],
            ['state_code' => 'TX', 'state_name' => 'Texas'],
            ['state_code' => 'UT', 'state_name' => 'Utah'],
            ['state_code' => 'VT', 'state_name' => 'Vermont'],
            ['state_code' => 'VA', 'state_name' => 'Virginia'],
            ['state_code' => 'WA', 'state_name' => 'Washington'],
            ['state_code' => 'WV', 'state_name' => 'West Virginia'],
            ['state_code' => 'WI', 'state_name' => 'Wisconsin'],
            ['state_code' => 'WY', 'state_name' => 'Wyoming'],
        ];
    }

    protected function tableColumns(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            $this->columnCache[$table] = array_flip(Schema::getColumnListing($table));
        }

        return $this->columnCache[$table];
    }

    protected function filterTableColumns(string $table, array $data): array
    {
        $cols = $this->tableColumns($table);

        return collect($data)
            ->filter(fn ($value, $key) => array_key_exists($key, $cols))
            ->all();
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function resolveDateRange(Request $request): array
    {
        $end = $this->parseDateOrFallback(
            $request->query('end_date'),
            Carbon::now()
        );

        $start = $this->parseDateOrFallback(
            $request->query('start_date'),
            Carbon::now()->subDays(30)
        );

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    protected function parseDateOrFallback(mixed $value, Carbon $fallback): Carbon
    {
        try {
            if (!is_string($value) || trim($value) === '') {
                return $fallback->copy()->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return $fallback->copy()->startOfDay();
        }
    }

    /**
     * @param Collection<int, int> $trailerIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function buildDriverHistoryByTrailer(Collection $trailerIds, string $startDate, string $endDate): array
    {
        if ($trailerIds->isEmpty()) {
            return [];
        }

        $historyRows = DB::table('driver_vehicle_history as dvh')
            ->leftJoin('contact as c', function ($join) {
                $join->on('c.id_contact', '=', 'dvh.contact_id');

                if ($this->hasColumn('contact', 'is_deleted')) {
                    $join->where('c.is_deleted', 0);
                }
            })
            ->selectRaw("
                dvh.id,
                dvh.vehicle_id,
                dvh.driver_id,
                dvh.contact_id,
                dvh.vehicle_type,
                dvh.date_action,
                dvh.action,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                    CONCAT('Contact #', dvh.contact_id)
                ) AS driver_name
            ")
            ->whereIn('dvh.vehicle_id', $trailerIds->all())
            ->where('dvh.vehicle_type', 1)
            ->whereIn('dvh.action', ['Assigned', 'Unassigned'])
            ->whereDate('dvh.date_action', '<=', $endDate)
            ->orderBy('dvh.vehicle_id')
            ->orderByDesc('dvh.date_action')
            ->orderByDesc('dvh.id')
            ->get()
            ->groupBy('vehicle_id');

        $result = [];

        foreach ($historyRows as $vehicleId => $entries) {
            $pendingUnassignedByDriver = [];
            $sessions = [];

            foreach ($entries as $entry) {
                $driverKey = (string) $entry->driver_id;
                $action = strtolower(trim((string) $entry->action));
                $dateAction = $this->safeDateString($entry->date_action);

                if ($dateAction === null) {
                    continue;
                }

                if ($action === 'unassigned') {
                    $pendingUnassignedByDriver[$driverKey] = $entry;
                    continue;
                }

                if ($action !== 'assigned') {
                    continue;
                }

                $unassignedEntry = $pendingUnassignedByDriver[$driverKey] ?? null;
                unset($pendingUnassignedByDriver[$driverKey]);

                $assignedDate = $dateAction;
                $unassignedDate = $unassignedEntry
                    ? $this->safeDateString($unassignedEntry->date_action)
                    : null;

                if ($assignedDate > $endDate) {
                    continue;
                }

                if ($unassignedDate !== null && $unassignedDate < $startDate) {
                    continue;
                }

                $sortDate = $unassignedDate ?? $assignedDate;

                $sessions[] = [
                    'driver_id' => (int) $entry->driver_id,
                    'contact_id' => $entry->contact_id !== null ? (int) $entry->contact_id : null,
                    'driver_name' => (string) $entry->driver_name,
                    'assigned_date' => $assignedDate,
                    'assigned_date_display' => $this->displayDate($assignedDate),
                    'unassigned_date' => $unassignedDate,
                    'unassigned_date_display' => $unassignedDate !== null
                        ? $this->displayDate($unassignedDate)
                        : 'Current',
                    'sort_date' => $sortDate,
                ];
            }

            usort($sessions, function (array $a, array $b): int {
                return strcmp($b['sort_date'], $a['sort_date']);
            });

            $result[(int) $vehicleId] = array_map(function (array $session) {
                unset($session['sort_date']);
                return $session;
            }, $sessions);
        }

        return $result;
    }

    /**
     * @param Collection<int, object> $trailers
     * @return array<int, array{load_count:int, load_revenue:float, recent_job:?string}>
     */
    protected function buildLoadDataByTrailer(Collection $trailers, string $startDate, string $endDate): array
    {
        if ($trailers->isEmpty()) {
            return [];
        }

        $trailerIdSet = [];
        $trailerKeyToId = [];

        foreach ($trailers as $trailer) {
            $trailerId = (int) $trailer->id_vehicle;
            $trailerIdSet[$trailerId] = true;

            $key = $this->normalizeTrailerKey($trailer->vehicle_number);
            if ($key !== null && !isset($trailerKeyToId[$key])) {
                $trailerKeyToId[$key] = $trailerId;
            }
        }

        $rows = DB::table('load as l')
            ->join('load_detail as ld', 'ld.id_load', '=', 'l.id_load')
            ->leftJoin('join as j', 'j.id_join', '=', 'l.id_join')
            ->leftJoin('pull_point as pp', 'pp.id_pull_point', '=', 'j.id_pull_point')
            ->leftJoin('pad_location as pl', 'pl.id_pad_location', '=', 'j.id_pad_location')
            ->selectRaw("
                l.id_load,
                l.load_date,
                l.id_vehicle,
                UPPER(TRIM(CAST(ld.trailer_number AS CHAR))) AS trailer_key,
                COALESCE(ld.carrier_pay, 0) AS carrier_pay,
                COALESCE(
                    NULLIF(TRIM(pl.pl_job), ''),
                    NULLIF(TRIM(pp.pp_job), '')
                ) AS recent_job
            ")
            ->whereBetween('l.load_date', [$startDate, $endDate])
            ->orderByDesc('l.load_date')
            ->orderByDesc('l.id_load')
            ->get();

        $result = [];

        foreach ($trailerIdSet as $trailerId => $unused) {
            $result[(int) $trailerId] = [
                'load_count' => 0,
                'load_revenue' => 0.0,
                'recent_job' => null,
                '_load_seen' => [],
                '_recent_sort' => null,
            ];
        }

        foreach ($rows as $row) {
            $matchedTrailerId = null;
            $directTrailerId = (int) $row->id_vehicle;

            if ($directTrailerId > 0 && isset($trailerIdSet[$directTrailerId])) {
                $matchedTrailerId = $directTrailerId;
            } else {
                $detailKey = $this->normalizeTrailerKey($row->trailer_key);
                if ($detailKey !== null && isset($trailerKeyToId[$detailKey])) {
                    $matchedTrailerId = $trailerKeyToId[$detailKey];
                }
            }

            if ($matchedTrailerId === null || !isset($result[$matchedTrailerId])) {
                continue;
            }

            $loadId = (int) $row->id_load;
            if (!isset($result[$matchedTrailerId]['_load_seen'][$loadId])) {
                $result[$matchedTrailerId]['_load_seen'][$loadId] = true;
                $result[$matchedTrailerId]['load_count']++;
            }

            $result[$matchedTrailerId]['load_revenue'] += (float) $row->carrier_pay;

            $sortKey = sprintf('%s|%010d', (string) $row->load_date, $loadId);

            if (
                $result[$matchedTrailerId]['_recent_sort'] === null
                || strcmp($sortKey, (string) $result[$matchedTrailerId]['_recent_sort']) > 0
            ) {
                $result[$matchedTrailerId]['_recent_sort'] = $sortKey;
                $result[$matchedTrailerId]['recent_job'] = $row->recent_job !== null && trim((string) $row->recent_job) !== ''
                    ? (string) $row->recent_job
                    : null;
            }
        }

        foreach ($result as $trailerId => $data) {
            $result[$trailerId]['load_revenue'] = round((float) $data['load_revenue'], 2);
            unset($result[$trailerId]['_load_seen'], $result[$trailerId]['_recent_sort']);
        }

        return $result;
    }

    protected function normalizeTrailerKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function safeDateString(mixed $value): ?string
    {
        try {
            if ($value === null || $value === '') {
                return null;
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function displayDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        try {
            return Carbon::parse($date)->format('m-d-Y');
        } catch (\Throwable $e) {
            return $date;
        }
    }

    protected function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
