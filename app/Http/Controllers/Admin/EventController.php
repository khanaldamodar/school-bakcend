<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Event;
use App\Services\TenantLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($domain)
    {
        $events = Event::with('eventType')->latest()->get();

        if ($events->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No events found!"
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => "Events fetched successfully",
            'events' => $events
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $domain)
    {
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i', // e.g., 14:30
            'event_type_id' => 'nullable|exists:event_types,id',
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

        TenantLogger::logCreate('events', "Event created: {$event->title}", [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $event->date
        ]);

        return response()->json([
            'status' => true,
            'message' => "Event Created SuccessFully",
            'event' => $event->load('eventType')
        ], 222);

    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $event = Event::with('eventType')->find($id);

        if (!$event) {
            return response()->json([
                'status' => false,
                'message' => 'No event found with this id'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Event found successfully',
            'data' => $event
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'event_type_id' => 'nullable|exists:event_types,id',
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

        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'status' => false,
                'message' => "Event not found with id {$id}"
            ], 404);
        }

        $event->update($validate->validated());

        TenantLogger::logUpdate('events', "Event updated: {$event->title}", [
            'id' => $event->id,
            'title' => $event->title
        ]);

        return response()->json([
            'status' => true,
            'message' => "Event updated successfully",
            'event' => $event->load('eventType')
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

        TenantLogger::logDelete('events', "Event deleted: {$event->title}", [
            'id' => $id,
            'title' => $event->title
        ]);

        return response()->json([
            'status' => true,
            'message' => "Event deleted successfully"
        ], 200);
    }

}
