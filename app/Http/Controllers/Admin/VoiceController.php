<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $voices = Voice::all();
        return response()->json($voices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $voice = Voice::create($request->all());
        return response()->json($voice);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $voice = Voice::findOrFail($id);
        return response()->json($voice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $voice = Voice::findOrFail($id);
        $voice->update($request->all());
        return response()->json($voice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $voice = Voice::findOrFail($id);
        $voice->delete();
        return response()->json($voice);
    }
}
