<?php

class HistoryController extends \BaseController {
	const QUANTITY = 30; //Amount of messages to show

	/**
	 * Display a listing of the resource.
	 * GET /history
	 *
	 * @return Response
	 */
	public function getIndex()
	{
		return Response::make('Stop snooping around!');
	}
	
	//List first n log messages
	public function anyList(){
		$histories = History::orderBy("created_at", "DESC")->take(QUANTITY)->get();
		return Response::json(["histories"=>$histories]);
	}
	
	public function save($user, $message){
		$history = new History;
		$history->user = $user;
		$history->log = $message;
		$history->save();
	}

}