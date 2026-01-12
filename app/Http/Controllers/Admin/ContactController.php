<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contacts = Contact::all();

        if ($contacts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No contacts found',
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'message' => 'Contacts fetched successfully',
                'contacts' => $contacts,
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:255',
        ]);

        $contact = Contact::create($validate);
        return response()->json([
            'status' => true,
            'message' => 'Contact created successfully',
            'contact' => $contact,
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show($domain, string $id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found',
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'message' => 'Contact fetched successfully',
                'contact' => $contact,
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $domain, string $id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        $validate = $request->validate([
            'name' => "nullable|string|max:255",
            'email' => "nullable|string|max:255",
            'phone' => "nullable|string|max:20",
            'message' => "nullable|string|max:255",
        ]);
        $contact->update($validate);

        return response()->json([
            'status' => true,
            'message' => 'Contact updated successfully',
            'contact' => $contact,
        ], 200);

    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $domain, string $id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Contact not found',
            ], 404);
        } else {
            $contact->delete();
            return response()->json([
                'status' => true,
                'message' => 'Contact deleted successfully',
            ]);
        }
    }
}
