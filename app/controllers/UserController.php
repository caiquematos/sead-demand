<?php

class UserController extends \BaseController {
	private $idUser;
	private $history;
	
	const NO = 'N';
	const YES = 'Y';
	
	//const PATH = "http://192.168.42.86:8000";
	//const PATH = "http://192.168.100.12:8000";
	const PATH = "http://192.168.42.107:8000";
	//const PATH = "http://192.168.3.181:8000";
	//const PATH = "http://192.168.1.3:8000";
	//const PATH = "http://192.168.3.184:8000";
	
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

	public function getIndex(){
		return Response::make('Try the followig /login or /register or /edit');
	}
	
	//Try to login
	public function anyLogin(){
		$user = User::whereEmail(Input::get('email'))->first();
	
		if( $user && Hash::check(Input::get('password'), $user->password) && $user->status == self::YES){
			Session::put("user", Crypt::encrypt($user->id));
			$user->gcm = Input::get("fcm");
			$user->save();
			$result = ["success"=>true, "registered"=>true, "user"=>$user, "msg"=>""];
			$this->history->save($user->id, $user->email . " entrou no sistema");
		} else{
			$result = ["success"=>false, "registered"=>false, "user"=>null, "msg"=>"Usuário não liberado"];
		}
		
		return Response::json($result);
	}
	
	//Try to register
	public function anyRegister(){
		$user = User::whereEmail(Input::get("email"))->first();
		$superior = User::whereEmail(Input::get("superior"))->first();
		
		
		if($user){
			$result = ["success"=>true, "registered"=>true];
		} else {
			$user = new User;
			$user->email = Input::get("email");
			$user->password = Hash::make(Input::get("password"));
			$user->name = Input::get("name");
			$user->position = Input::get("position");
			$user->status = self::NO;
			$user->gcm = Input::get("gcm");
			$user->save(); // Save before in order to generate id
						
			// Test if this user is on top of position or not and attribute its superior regarding
			if ($superior) {
				$user->superior = $superior->id;
				$random_string = str_random(30);
				$user->code = Hash::make($random_string);				
				// Send email to superior
				$superiordata	 = ['superiorname'=>$superior->name,'username'=>$user->name,'useremail'=>$user->email,'userposition'=>$user->position, 'link'=>self::PATH.'/user/verify/?code='.$user->code];
				Mail::queue('confirm', $superiordata, function($message) use ($superior){
					$message->to($superior->email, $superior->name)
						->subject('Confirmar Autenticação');
				});
				
			} else if (Input::get("email") == Input::get("superior")) {
				$superior = $user;
				$user->superior = $superior->id;
				//$user->status = self::YES;
			}
			
			$user->save();
			
			$userdata = ['username'=>$user->name];
			Mail::queue('welcome', $userdata, function($message) use ($user){
  			$message->to($user->email, $user->name)
          ->subject('Sob Análise');
			});
			
			$result = ["success"=>true, "registerd"=>false, "user"=>$user];
			//$this->history->save($user->id, $user->email . " se registrou no sistema");
		}
		
		return Response::json($result);
	}
	
	// Verify new registered users
	public function anyVerify(){
		$hash_code = Input::get("code");
		$user = User::whereCode($hash_code)->first();
		
		if($user){
			$user->status = self::YES;
			$user->save();
			$message = 'Usuário liberado com sucesso!';
		} else{
			$message = 'Usuário não encontrado!';			
		}
		
		return $message;
	}
	
	public function anyPosition(){
		$positions = User::lists('position');
		$result = ["success"=>true, "positions"=>$positions];
				
		return $result;
	}
	
	// Get confirmed employees by position
	public function anyEmployee(){
		$employees = User::wherePosition(Input::get("position"))->whereStatus(self::YES)->get();
		
		if ($employees){
			$result = ["success"=>true, "employees"=>$employees];			
		} else {
			$result = ["success"=>false];			
		}
				
		return $result;
	}
	
	// Update FCM token of a specific user
	public function anyUpdateFcm(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if ($user) {
			$user->gcm = Input::get('fcm');
			$user->save();
			
			$result =  ["success"=>true, "fcm" => $user->fcm];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
}