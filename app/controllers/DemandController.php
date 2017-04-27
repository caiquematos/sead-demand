<?php

class DemandController extends \BaseController {
	private $history;
	private $gcm;
	
	//demand status as Accepted, Cancelled, Postponed or Reopen, Undefined is default.
	const ACCEPTED = 'A';
	const CANCELLED = 'C';
	const POSTPONED = 'P';
	const REOPEN = 'R';
	const UNDEFINED = 'U';
	
	const NO = 'N';
	const YES = 'Y';
	
	public function DemandController(){
		$this->history = new HistoryController;
		$this->gcm = new GCMController;
	}

	/**
	 * Display a listing of the resource.
	 * GET /demand
	 *
	 * @return Response
	 */
	public function anyIndex()
	{
		return "Send, edit, remove demand!";
	}

	public function anySend(){
		$sender = User::whereEmail(Input::get('sender'))->first();
		$receiver = User::whereEmail(Input::get('receiver'))->first();
				
		if ($sender && $receiver) {
			$demand = new Demand;
			$demand->sender = $sender->id;
			$demand->receiver = $receiver->id;
			$demand->importance = Input::get("importance");
			$demand->status = self::UNDEFINED; //undefined
			$demand->seen = self::NO; //no
			$demand->subject = Input::get("subject");
			$demand->description = Input::get("description");
			$demand->save();
			$result = ["success"=>true, "sender"=>$sender, "receiver"=>$receiver, "demand"=>$demand];
			$this->history->save($sender->id, $sender->email . " enviou a demanda " . $demand->id . " para " . $receiver->email);
			
			//If there is a superior, contact him.
			//If not, contact the receiver directly.
			$superior = User::find($receiver->superior);
			if($superior){
				if ($superior->id != $sender->id){
					$title = "Liberar Demanda";
					$text = "De:".$sender->name." Para:".$receiver->name;
					$data = ["noteId"=> $superior->id ."". $sender->id ."". $demand->id, "demand" => $demand];
					$fcmToken = $superior->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
				}
			} else {
				$title = $sender->name." (new)";
				$text = $demand->subject;
				$data = ["noteId"=> $sender->id ."". $demand->id];
				$fcmToken = $receiver->gcm;
				$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);				
			}
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	public function anyMarkAsRead(){
		$demand = Demand::find(Input::get('demand'));
		
		if ($demand){
			$demand->seen = self::YES;
			$demand->save();
			$result = ["success"=>true];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	
	//Set demand status as Accepted, Cancelled, Postponed or Reopen.
	//Undefined is default.
	//TODO: In case of 'postponed' something should be done
	//{"A", "C", "P", "R", "U"}
	public function anySetStatus(){
		$demand = Demand::find(Input::get('demand'));
		
		if ($demand){
			$demand->status = Input::get('status');
			$demand->save();
			
			//Notification System
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
			$text = $demand->subject;
			$data = ["noteId" => $sender->id ."". $demand->id, "demand" => $demand];
			
			switch($demand->status){
				case self::CANCELLED:
					$title = "Demanda (cancelada)";
					$fcmToken = $sender->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					break;
				case self::POSTPONED:
					$title = "Demanda (adiada)";
					$fcmToken = $sender->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					break;
				case self::REOPEN:
					//Inform sender
					$title = "Demanda (reaberta)";
					$fcmToken = $sender->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					//Inform receiver
					$fcmToken = $receiver->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					break;
				case self::ACCEPTED:
					//Inform sender
					$title = "Demanda (aceita)";
					$fcmToken = $sender->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					//Inform receiver
					$fcmToken = $receiver->gcm;
					$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					break;
			}
			
			$result = ["success"=>true, "demand"=>$demand];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	//list of the demand sent
	public function anyListSent(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			$list = Demand::whereSender($user->id)
				->orderBy('id','desc')
				->get();
			if( $list ){
				foreach ( $list as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->receiverName = $receiver->name;
					}else {
						$result = ["success"=>false];
					}
				}
			}
			$result = ["success"=>true, "list"=>$list];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	/* list of the demand received */
	public function anyListReceived(){
		$user = User::whereEmail(Input::get('email'))->first();
	
		//TODO: Exclude users type "PONTA"
		if( $user ){
			$subs = User::whereSuperior($user->id)->get(); //workers under user supervision
			$sublist = [];
			
			//list all demands where I am the the superior
			foreach( $subs as $sub ){
				//if($sub->superior != $user->id) //Dont list if I am the superior
				$sublist = Demand::whereReceiver($sub->id)
					->orderBy('id','desc')
					->get();
			}
			 
			//list all demands where I am the receiver
			$list = Demand::whereReceiver($user->id)
				->whereStatus(self::ACCEPTED,self::REOPEN)
				->orderBy('id','desc')
				->get();
			
			//merge both previous lists
			$array_merge = $list->merge($sublist);
			
			if( $array_merge ){
				foreach ( $array_merge as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->receiverName = $receiver->name;
					}else {
						$result = ["success"=>false];
					}
					
				}
			}
			
			$result = ["success"=>true, "list"=>$array_merge];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	public function anyListUnread(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			$list = Demand::whereReceiver($user->id)->whereSeen(self::NO)->get();
			$result = ["success"=>true, "list"=>$list];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	public function anyListRead(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			$list = Demand::whereReceiver($user->id)->whereSeen('Y')->get();
			$result = ["success"=>true, "list"=>$list];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	/* return a list of demands that user sent by status */
	public function anyListByStatus(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			$list = Demand::whereSender($user->id)->whereStatus(Input::get('status'))
						->orderBy('id','desc')
						->get();
			if( $list ){
				foreach ( $list as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->receiverName = $receiver->name;
					}else {
						$result = ["success"=>false];
					}
				}
			}
			$result = ["success"=>true, "list"=>$list];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}

}