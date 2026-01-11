<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\QuickLink;
use Illuminate\Http\Request;

class QuickLinkContrtoller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $quickLinks = QuickLink::all();

        if ($quickLinks->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No quick links found.',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Quick links fetched successfully.',
            'data' => $quickLinks
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'url' => 'required|string'
        ]);
        $title = $validated['title'];
        $url = $validated['url'];

        $quickLink = QuickLink::create([
            'title' => $title,
            'url' => $url
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Quick link created successfully.',
            'data' => $quickLink
        ]);
    }



    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $quickLink = QuickLink::find($id);

        if (!$quickLink) {
            return response()->json([
                'status' => false,
                'message' => 'No quick link found with this id.',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => 'Quick link fetched successfully.',
            'data' => $quickLink
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $quickLink = QuickLink::find($id);
        if (!$quickLink) {
            return response()->json([
                'status' => false,
                'message' => 'No quick link found with this id.',
                'data' => []
            ]);
        }
        $validated = $request->validate([
            'title' => 'required|string',
            'url' => 'required|string'
        ]);
        $title = $validated['title'];
        $url = $validated['url'];

        $quickLink->title = $title;
        $quickLink->url = $url;
        $quickLink->save();
        return response()->json([
            'status' => true,
            'message' => 'Quick link updated successfully.',
            'data' => $quickLink
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($domain, string $id)
    {
        $quickLink = QuickLink::find($id);
        if (!$quickLink) {
            return response()->json([
                "status" => false,
                "message" => "No quick link found with this id.",
                "data" => []
            ]);
        } else {
            $quickLink->delete();
            return response()->json([
                "status" => true,
                "message" => "Quick link deleted successfully.",
            ]);
        }

    }
}
