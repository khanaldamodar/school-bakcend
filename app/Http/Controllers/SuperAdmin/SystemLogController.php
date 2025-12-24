<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SystemLog;

class SystemLogController extends Controller
{
    /**
     * Display a listing of the logs.
     */
    public function index(Request $request)
    {
        $query = SystemLog::query()->latest();

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        $logs = $query->paginate(50);

        return response()->json([
            'status' => true,
            'data' => $logs
        ]);
    }

    /**
     * Display the specified log.
     */
    public function show($id)
    {
        $log = SystemLog::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $log
        ]);
    }
}
