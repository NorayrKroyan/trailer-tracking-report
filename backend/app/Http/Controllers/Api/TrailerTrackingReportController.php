<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrailerTrackingReportController extends Controller
{
    public function owners(): JsonResponse
    {
        $owners = DB::table('vehicle as v')
            ->select('v.owner')
            ->where('v.is_deleted', 0)
            ->where('v.id_vehicle_type', 1)
            ->whereNotNull('v.owner')
            ->whereRaw("TRIM(v.owner) <> ''")
            ->distinct()
            ->orderBy('v.owner')
            ->pluck('owner');

        return response()->json([
            'owners' => $owners,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $owner = trim((string) $request->query('owner', ''));

        if ($owner === '') {
            return response()->json([
                'rows' => [],
                'summary' => [
                    'total_trailers' => 0,
                    'assigned_count' => 0,
                    'unassigned_count' => 0,
                ],
            ]);
        }

        $rows = DB::table('vehicle as v')
            ->leftJoin('driver as d', function ($join) {
                $join->on('d.id_trailer', '=', 'v.id_vehicle')
                    ->where('d.is_deleted', 0)
                    ->where('d.is_driver', 1);
            })
            ->leftJoin('contact as c', function ($join) {
                $join->on('c.id_contact', '=', 'd.id_contact')
                    ->where('c.is_deleted', 0);
            })
            ->selectRaw("
                v.id_vehicle,
                COALESCE(
                    NULLIF(TRIM(v.vehicle_name), ''),
                    NULLIF(TRIM(v.vehicle_number), ''),
                    CONCAT('Trailer #', v.id_vehicle)
                ) AS trailer_display,
                v.vehicle_number,
                v.vehicle_name,
                v.license_plate,
                v.owner,
                COALESCE(
                    NULLIF(
                        GROUP_CONCAT(
                            DISTINCT TRIM(CONCAT(c.first_name, ' ', c.last_name))
                            ORDER BY c.first_name, c.last_name
                            SEPARATOR ', '
                        ),
                        ''
                    ),
                    'Unassigned'
                ) AS current_assigned_driver
            ")
            ->where('v.is_deleted', 0)
            ->where('v.id_vehicle_type', 1)
            ->where('v.owner', $owner)
            ->groupBy(
                'v.id_vehicle',
                'v.vehicle_number',
                'v.vehicle_name',
                'v.license_plate',
                'v.owner'
            )
            ->orderBy('trailer_display')
            ->get();

        $assignedCount = $rows->filter(
            fn ($row) => (string) $row->current_assigned_driver !== 'Unassigned'
        )->count();

        $unassignedCount = $rows->filter(
            fn ($row) => (string) $row->current_assigned_driver === 'Unassigned'
        )->count();

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'total_trailers' => $rows->count(),
                'assigned_count' => $assignedCount,
                'unassigned_count' => $unassignedCount,
            ],
        ]);
    }
}
