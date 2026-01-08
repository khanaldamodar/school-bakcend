<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\EventType;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $eventType = EventType::with('events')->get();

        if ($eventType->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Event Type Found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Event Type Fetched Successfuly',
            'types' => $eventType
        ], 404);


    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:event_types,name',
            'color_code' => 'required|string|max:20',
        ]);

        $eventType = EventType::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Event type created successfully',
            'data' => $eventType
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($domain, $id)
    {
        $eventType = EventType::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $eventType
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, $id)
    {
        $eventType = EventType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:event_types,name,' . $eventType->id,
            'color_code' => 'required|string|max:20',
        ]);

        $eventType->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Event type updated successfully',
            'data' => $eventType
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain, $id)
    {
        $eventType = EventType::findOrFail($id);
        $eventType->delete();

        return response()->json([
            'status' => true,
            'message' => 'Event type deleted successfully'
        ]);
    }
}
