<?php

namespace App\Http\Controllers;

use App\Models\Meetup;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }



    public function index()
    {
        $meetups = Meetup::all();
        return view('home', compact('meetups'));
    }
    public function saveMeetup(Request $request){
        $meetup = new Meetup();
        $meetup->name = $request->name;
        $meetup->save();
        return redirect()->back();
    }
    public function joinMeetup(Request $request){
        return view('meetup');
    }
}
