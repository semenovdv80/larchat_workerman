<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use App\Message;
use Illuminate\Support\Facades\Auth;

class ChatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show chats
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('chat');
    }

    /**
     * Fetch all messages
     *
     * @return Message
     */
    public function fetchMessages()
    {
        return Message::with('user')->get();
    }

    /**
     * Persist message to database
     *
     * @param Request $request
     * @return array
     */
    public function sendMessage(Request $request)
    {
        $user = Auth::user();

        $message = $user->messages()->create([
            'message' => $request->input('text')
        ]);

        return [
            'username' => $user->name,
            'created_at' => $message->created_at->toDateTimeString(),
            'status' => 'Message Sent!'
        ];
    }

    /**
     * Get users list
     *
     * @param Request $request
     * @return array
     */
    public function userList(Request $request)
    {
        $users = User::find($request->userIds);
        return [
            'users' => !empty($users) ? $users->pluck('name') : []
        ];
    }
}
