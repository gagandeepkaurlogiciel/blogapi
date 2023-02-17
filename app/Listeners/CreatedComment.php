<?php

namespace App\Listeners;

use App\Events\CreateComment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CreatedComment implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries =2;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\CreateComment  $event
     * @return void
     */
    public function handle(CreateComment $event)
    {
        $data = $event->create_comment[0];

        $facebook_image_id = Post::where('id', $data['postid'])
        ->pluck('facebook_post_id')[0];
        $facebook_msg_id = Post::where('id', $data['postid'])
        ->pluck('facebook_msg_id')[0];

        $access_token = $event->create_comment[1][0]['token'];
        $facebook_user_id = $event->create_comment[1][0]['facebook_id'];

        $profile_response = Http::get(env('FACEBOOK_GRAPH_API') . 'me/accounts?access_token=' . $access_token . '');
        $page_token = $profile_response['data'][0]['access_token'];
        $page_id = $profile_response['data'][0]['id'];

        $feed_response = Http::get(env('FACEBOOK_GRAPH_API') . $page_id . '/feed?&access_token=' . $page_token . '');

        if(!empty($facebook_image_id)){
            $comment_response = Http::post(env('FACEBOOK_GRAPH_API') . $facebook_image_id . '/comments/?message=' . $data['comment'] . '&access_token=' . $page_token . '');
        }else{
            $comment_response = Http::post(env('FACEBOOK_GRAPH_API') . $facebook_msg_id . '/comments/?message=' . $data['comment'] . '&access_token=' . $page_token . '');
        }

        $data = DB::table('comments')->where('userid', $data['userid'])
        ->where('id', $data['id'])
        ->update([
            'facebook_post_id' => $facebook_image_id,
            'comment_id' => $comment_response['id'],
            'pageid' => $page_id,
            'created_by' => $facebook_user_id,
        ]);
    }
}
