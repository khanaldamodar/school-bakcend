<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsController extends Controller
{
    /**
     * Add SMS balance to a school.
     */
    public function addBalance(Request $request, $tenantId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        $tenant->sms_balance += $request->amount;
        $tenant->save();

        return response()->json([
            'status' => true,
            'message' => 'SMS balance added successfully',
            'data' => [
                'school_name' => $tenant->name,
                'new_balance' => $tenant->sms_balance
            ]
        ]);
    }

    /**
     * Get SMS balance of a school.
     */
    public function getBalance($tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'school_name' => $tenant->name,
                'sms_balance' => $tenant->sms_balance
            ]
        ]);
    }
}
