<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\StudentClub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentClubController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'club_id' => 'required|exists:clubs,id',
            'position' => 'nullable|string|max:255'
        ]);

        $validator->after(function ($validator) use ($request) {
            // Allow multiple members
            if ($request->position && strtolower($request->position) !== 'member') {

                $exists = StudentClub::where('club_id', $request->club_id)
                    ->where('position', $request->position)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'position',
                        "{$request->position} position already exists for this club."
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $studentClub = StudentClub::create($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Student added to club successfully',
            'data' => $studentClub
        ], 201);
    }



    public function update(Request $request, string $domain, string $id)
    {
        $studentClub = StudentClub::find($id);

        if (!$studentClub) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ], 404);
        }

        // Normalize position (optional but recommended)
        if ($request->has('position')) {
            $request->merge([
                'position' => ucfirst(strtolower($request->position))
            ]);
        }

        $validator = Validator::make($request->all(), [
            'position' => 'nullable|string|max:255'
        ]);

        // 🔴 Custom validation rule
        $validator->after(function ($validator) use ($request, $studentClub) {

            if (
                $request->position &&
                strtolower($request->position) !== 'member'
            ) {
                $exists = StudentClub::where('club_id', $studentClub->club_id)
                    ->where('position', $request->position)
                    ->where('id', '!=', $studentClub->id) // exclude self
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'position',
                        "{$request->position} position already exists for this club."
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $studentClub->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Student club position updated',
            'data' => $studentClub
        ]);
    }



    public function destroy(string $domain, string $id)
    {
        $studentClub = StudentClub::find($id);

        if (!$studentClub) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ], 404);
        }

        $studentClub->delete();

        return response()->json([
            'status' => true,
            'message' => 'Student removed from club'
        ]);
    }
}
