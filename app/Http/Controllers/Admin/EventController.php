<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = Event::all();

        if ($events->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No events found!"
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => "Events Fetced Successfully",
            'events' => $events
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i', // e.g., 14:30
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => "Failde to validate the data",
                'error' => $validate->errors()
            ], 201);
        }

        $validatedEvent = $validate->validated();

        $event = Event::create($validatedEvent);

        return response()->json([
            'status' => true,
            'message' => "Event Created SuccessFully",
            'event' => $event
        ], 222);

    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        try {
            $event = Event::findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Event found successfully',
                'data' => $event
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'No data available with this id',
                'error' => $e->getMessage(),
            ], 404);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i', // e.g., 14:30
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => "Failed to validate the data",
                'error' => $validate->errors()
            ], 422);
        }

        $validatedEvent = $validate->validated();

        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'status' => false,
                'message' => "Event not found with id {$id}"
            ], 404);
        }

        $event->update($validatedEvent);

        return response()->json([
            'status' => true,
            'message' => "Event Updated Successfully",
            'event' => $event
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain, string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'status' => false,
                'message' => "Event not found with id {$id}"
            ], 404);
        }

        $event->delete();

        return response()->json([
            'status' => true,
            'message' => "Event deleted successfully"
        ], 200);
    }

}
