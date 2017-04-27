<?php

class UserController extends \BaseController {
	private $idUser;
	private $history;
	
	//Check if session was create
	public function UserController(){
		$this->history = new HistoryController;	
		$id = Session::get("user");
		
		if($id == null || $id == ""){
			$this->idUser = false;
		}
		else{
			$this->idUser = Crypt::decrypt($id);
		}
	}
	
	//Send a push notification if GCM is saved: used for test
	public function anySendGcm(){
    $gcm = new GCMController;
    $user = User::find(Input::get("user"));
    $registration_ids[] = $user->gcm;
    $gcm->sendNote($registration_ids,["message"=>"GCM id was saved"]);
    return $user->gcm;
  }

	public function getIndex()
	{
		return Response::make('Try the followig /login or /register or /edit');
	}
	
	//Try to login
	public function anyLogin(){
		$user = User::whereEmail(Input::get('email'))->first();
	
		if( $user && Hash::check(Input::get('password'), $user->password)){
			Session::put("user", Crypt::encrypt($user->id));
			$result = ["success"=>true, "registered"=>true, "user"=>$user];
			$this->history->save($user->id, $user->email . " entrou no sistema");
		} else{
			$result = ["success"=>false, "registered"=>false];
		}
		
		return Response::json($result);
	}
	
	//Try to register
	public function anyRegister(){
		$user = User::whereEmail(Input::get("email"))->first();
		$superior = User::whereEmail(Input::get("superior"))->first();
		
		if($user){
			$result = ["success"=>true, "registered"=>true];
		} else if ($superior){
			$user = new User;
			$user->email = Input::get("email");
			$user->password = Hash::make(Input::get("password"));
			$user->name = Input::get("name");
			$user->position = Input::get("position");
			$user->superiorId = $superior->id;
			$user->gcm = Input::get("gcm");
			$user->save();
			$result = ["success"=>true, "registerd"=>false, "user"=>$user];
			$this->history->save($user->id, $user->email . " se registrou no sistema");
			//TODO: Send an email to superior requesting registration
		} else {
			$result = ["success"=>false];
		}
		
		return Response::json($result);
	}
	
	public function anyPosition(){
		$positions = User::lists('position');
		$result = ["success"=>true, "positions"=>$positions];
				
		return $result;
	}
	
	//Get employee by position
	public function anyEmployee(){
		$employees = User::wherePosition(Input::get("position"))->get();
		
		if ($employees){
			$result = ["success"=>true, "employees"=>$employees];			
		} else {
			$result = ["success"=>false];			
		}
				
		return $result;
	}
	
}