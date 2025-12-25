<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LocalBody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class LocalBodiesController extends Controller
{


    public function index()
    {
        $localBodies = LocalBody::all();
        
        $data = $localBodies->map(function ($lb) {
            $schoolCount = Tenant::where('local_unit', $lb->local_unit)
                ->where('district', $lb->district)
                ->count();
                
            return [
                'id' => $lb->id,
                'district' => $lb->district,
                'local_unit' => $lb->local_unit,
                'local_unit_np' => $lb->local_unit_np,
                'school_count' => $schoolCount
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getByDistrict($district)
    {
        $cacheKey = 'local_bodies_district_' . strtolower($district);

        $localBodies = Cache::rememberForever($cacheKey, function () use ($district) {
            return LocalBody::where('district', $district)->get(['id', 'local_unit', 'local_unit_np']);
        });

        $data = $localBodies->map(function ($lb) {
            $schoolCount = Tenant::where('local_unit', $lb->local_unit)
                ->where('district', $lb->district)
                ->count();
                
            return [
                'id' => $lb->id,
                'local_unit' => $lb->local_unit,
                'local_unit_np' => $lb->local_unit_np,
                'school_count' => $schoolCount
            ];
        });

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No local bodies found for the specified district',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Local bodies fetched successfully',
            'data' => $data
        ]);
    }


    public function getAllDistricts()
{
    $districts = Cache::rememberForever('local_bodies_districts', function () {
        return LocalBody::distinct()->get(['district']);
    });

    if ($districts->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'No districts found',
            'data' => []
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'Districts fetched successfully',
        'data' => $districts
    ]);
}

}
