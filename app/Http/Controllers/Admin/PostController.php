<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    /**
     * Display a listing of approved posts. (Public API)
     */
    public function index()
    {
        $posts = Post::approved()->with('user:id,name')->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Posts retrieved successfully',
            'data' => $posts
        ]);
    }

    /**
     * Display all posts for admin management. (Admin API)
     */
    public function adminIndex()
    {
        $posts = Post::with('user:id,name')->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'All posts retrieved successfully',
            'data' => $posts
        ]);
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $user = Auth::user();
        $isAdmin = in_array($user->role, ['admin', 'super-admin']);

        $data = [
            'user_id' => $user->id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'status' => $isAdmin ? 'approved' : 'pending',
            'is_admin_post' => $isAdmin,
        ];

        if ($request->hasFile('image')) {
            $upload = ImageUploadHelper::uploadToCloud($request->file('image'), 'school/posts');
            if ($upload) {
                $data['image'] = $upload['url'];
            }
        }

        $post = Post::create($data);

        return response()->json([
            'status' => true,
            'message' => $isAdmin ? 'Post created and approved successfully' : 'Post created successfully, waiting for approval',
            'data' => $post
        ], 201);
    }

    /**
     * Display the specified post.
     */
    public function show($domain, $id)
    {
        $post = Post::with('user:id,name')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Post retrieved successfully',
            'data' => $post
        ]);
    }

    /**
     * Update the specified post.
     */
    public function update(Request $request, $domain, $id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();

        // Only author or admin can update
        if ($post->user_id !== $user->id && !in_array($user->role, ['admin', 'super-admin'])) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'image' => 'sometimes|nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $upload = ImageUploadHelper::uploadToCloud($request->file('image'), 'school/posts');
            if ($upload) {
                $validated['image'] = $upload['url'];
            }
        }

        $post->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Post updated successfully',
            'data' => $post
        ]);
    }

    /**
     * Approve or reject a post (Admin only).
     */
    public function updateStatus(Request $request, $domain, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending'
        ]);

        $post = Post::findOrFail($id);
        $post->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => 'Post status updated to ' . $request->status,
            'data' => $post
        ]);
    }

    /**
     * Remove the specified post.
     */
    public function destroy($domain, $id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();

        // Only author or admin can delete
        if ($post->user_id !== $user->id && !in_array($user->role, ['admin', 'super-admin'])) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        $post->delete();

        return response()->json([
            'status' => true,
            'message' => 'Post deleted successfully'
        ]);
    }
}
