<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        $notices = Notice::all();

        if ($notices->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No notices found.'

            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notices retrieved successfully',
            'data' => $notices
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'notice_date' => 'nullable|date',
            'image' => 'nullable|image|max:2048', // Max 2MB
        ]);

        $notice = Notice::create($validatedData);
        return response()->json([
            'status' => true,
            'message' => 'Notice created successfully',
            'data' => $notice
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notice retrieved successfully',
            'data' => $notice
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'notice_date' => 'sometimes|nullable|date',
            'image' => 'sometimes|nullable|image|max:2048', // Max 2MB
        ]);

        $notice->update($validatedData);
        return response()->json([
            'status' => true,
            'message' => 'Notice updated successfully',
            'data' => $notice
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain,  string $id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return response()->json([
                'status' => false,
                'message' => 'Notice not found'
            ], 404);
        }

        $notice->delete();
        return response()->json([
            'status' => true,
            'message' => 'Notice deleted successfully'
        ], 200);
    }
}
