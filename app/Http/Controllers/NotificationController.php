<?php
namespace App\Http\Controllers;

class NotificationController extends Controller
{
    public function index()
    {
        $items = auth()->user()->notifications()->latest()->paginate(20);
        auth()->user()->unreadNotifications->markAsRead();
        return view('notifications.index', compact('items'));
    }
}
