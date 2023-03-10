<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Transformers\CommentTransformer;
use App\Transformers\PostListTransformer;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Events\CreateComment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use App\Events\UpdateComment;
use App\Events\DeleteComment;

class CommentController extends Controller
{
    /**
     * Insert comment
     */
    public function comment(Request $request, $id)
    {
        $userid = auth()->user()->id;
        $comment = $request->comment;
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
            'facebook_page' => 'string',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 401);
        }
        try {
            $data = Comment::create([
                'userid' => $userid,
                'postid' => $id,
                'comment' => $comment,
                'created_by' => $userid,
            ]);

            $user = User::where('id', $data['userid'])->first();

            if (!empty(auth()->user()->token)) {
                event(new CreateComment($data, $user));
            }

            return response()->json(['success' => 'Comment posted successfully']);
        } catch (QueryException $e) {
            Log::critical($e);
            return response('Something went wrong');
        }
    }

    /**
     * Update comment
     */
    public function update(Request $request, $id)
    {
        try {
            $user_id = auth()->user()->id;

            $validator = Validator::make($request->all(), [
                'comment' => 'required',
            ]);

            Comment::where('userid', $user_id)
                ->where('id', $id)
                ->update([
                    'comment' => $request->comment,
                ]);

            $data = Comment::where('userid', $user_id)
                ->where('id', $id)
                ->first();

            $user = User::where('id', $user_id)->first();

            if (!empty(auth()->user()->token)) {
                event(new UpdateComment($data, $user));
            }

            return response()->json(['success' => 'Comment updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['Something went wrong']);
        }
    }

    /**
     * List post-comments (hasMany)
     */
    public function show($id)
    {
        try {
            $userid = auth()->user()->id;
            $type = auth()->user()->type;
            if ($type == 1) {
                $data = Post::where('userid', $userid)
                    ->where('id', $id)
                    ->with('comments')
                    ->get();
            } else {
                $data = Post::where('id', $id)
                    ->with('comments')
                    ->get();
            }

            return collect($data)->transformWith(new PostListTransformer());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * List comments-post (Inverse of hasMany)
     */
    public function showlist($id)
    {
        try {
            $userid = auth()->user()->id;
            $type = auth()->user()->type;
            if ($type == 1) {
                $data = Comment::where('userid', $userid)
                    ->where('postid', $id)
                    ->with('post')
                    ->get();
            } else {
                $data = Comment::where('postid', $id)
                    ->with('post')
                    ->get();
            }

            return collect($data)->transformWith(new CommentTransformer());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        try {
            $user_id = auth()->user()->id;
            $data = Comment::where('id', $id)->first();
            $user = User::where('id', $user_id)->first();

            if (!empty(auth()->user()->token)) {
                event(new DeleteComment($data, $user));
            }

            $data = Comment::where('id', $id)->delete();

            return response()->json('Comment successfully deleted');
        } catch (\Exception $e) {
            Log::critical($e->getMessage());
            return response('Something went wrong');
        }
    }
}

