<?php

class DemandController extends \BaseController {
	private $history;
	private $gcm;
	
	//demand status as Accepted, Cancelled, Postponed or Reopen, Undefined is default.
	const ACCEPTED = 'A';
	const CANCELED = 'C'; // Demand once accepted by the admin, but canceled at some point
	const POSTPONED = 'P';
	const REOPEN = 'R';
	const UNDEFINED = 'U';
	const REJECTED = 'X'; // Demand rejected by the admin
	const RESENT = 'S';
	const DONE = 'D';
	const LATE = 'L';
	
	const NO = 'N';
	const YES = 'Y';
	
	// Type of menu
	const FULLMENU = 1;
	const NOMENU = 2;
	const REOPENMENU = 3;
	const CANCELMENU = 4;
	const RESENDMENU = 5;
	const DONEMENU = 6;
		
	// Type of FCM message.
	const ADDADMIN = 'add_demand_admin';
	const ADDRECEIVER = 'add_demand_receiver';
	const ADDSENT = 'add_demand_sent';
	const UPDATE = 'update_demand';
	const STATUS = 'update_status';
	const PRIOR = 'update_prior';
	const READ = 'update_read';
	
	// Type of page environment
	const RECEIVEDPAGE = 1;
	const SENTPAGE = 2;
	const ADMINPAGE = 3;
	const STATUSPAGE = 4;
	const CREATEPAGE = 5;
	
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
			$demand->prior = Input::get("prior");
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
			$text = "De:".$sender->name." Para:".$receiver->name;

			if($superior){
				// The following would not notify if the user is your own superior
				//	if ($superior->id != $sender->id) 
				$title = "(liberar Demanda)";
				$fcmToken = $superior->gcm;
				$menuType = self::FULLMENU;
				$storageType = self::ADDADMIN;
			} else {
				$title = "(nova)";
				$fcmToken = $receiver->gcm;
				$menuType = self::DONEMENU;
				$storageType = self::ADDRECEIVER;
			}
			
			$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];	
			$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);				
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	public function anyResend(){
		$demand = Demand::find(Input::get('id'));

		if ($demand) {
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
			
			if ($sender && $receiver) {
				$demand->status = self::RESENT; //Resent
				$demand->seen = self::NO; //no
				$demand->save();
				$result = ["success"=>true, "sender"=>$sender, "receiver"=>$receiver, "demand"=>$demand];
				$this->history->save($sender->id, $sender->email . " reenviou a demanda " . $demand->id . " para " . $receiver->email);

				//If there is a superior, contact him.
				//If not, contact the receiver directly.
				$superior = User::find($receiver->superior);
				$text = "De:".$sender->name." Para:".$receiver->name;

				if($superior){
					// The following if would not notify if the user is your own superior
					//	if ($superior->id != $sender->id) 
					$title = "(liberar demanda)";
					$menuType = self::FULLMENU;
					$fcmToken = $superior->gcm;
				} else {
					$title = "(reenviada)";
					$text = "De:".$sender->name." Para:".$receiver->name;
					$menuType = self::DONEMENU;
					$fcmToken = $receiver->gcm;
				}
				
				$data = ["menu"=>$menuType, "page"=>self::FULLMENU, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>self::UPDATE];
				$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
			} else {
				$result = ["success"=>false];
			}
		} else {
			$result = ["success"=>false];
		} 
				
		return $result;
	}
	
	// Mark demand as read when clicked. TODO: Send data, but do not notify in background.
	public function anyMarkAsRead(){
		$demand = Demand::find(Input::get('demand'));
			
		if ($demand){
			$demand->seen = self::YES;
			$demand->save();
			
			// Notify sender's device.
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
			if ($sender && $receiver) {
				$fcmToken = $sender->gcm;
				$data = ['demand'=>$demand, 'sender'=>$sender, 'receiver'=>$receiver, "type"=>self::READ];
				$this->gcm->sendHiddenMessage($fcmToken, $data);
				
				$result = ["success"=>true, "demand"=>$demand, "sender"=>$sender, "receiver"=>$receiver];
			}
			
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	public function anySetPrior(){
		$demand = Demand::find(Input::get('demand'));
		
		if ($demand) {
			$demand->prior = Input::get('prior');
			$demand->save();
			
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
			
			if ($sender && $receiver){
								
				// Notification System
				$text = "De:".$sender->name." Para:".$receiver->name;
				$data = ["page"=>self::SENTPAGE, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>self::PRIOR, "menu"=>self::NOMENU];
				$title = "(prioridade:".$demand->prior.")";
				$fcmToken = $sender->gcm;
				$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
					
				$result = ["success"=>true, "demand"=>$demand, "sender"=>$sender, "receiver"=>$receiver];
				
			} else {
				$result = ["success"=>false];
			}
			
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}

	//Set demand status as Accepted, Cancelled, Postponed or Reopen.
	//Undefined is default.
	//TODO: In case of 'postponed' something should be done
	//{"A", "C", "P", "R", "U", "X", "D"}
	public function anySetStatus(){
		$demand = Demand::find(Input::get('demand'));
		
		if ($demand){			
			$demand->status = Input::get('status');
			if ($demand->status == self::REOPEN) $demand->seen = self::NO;
			$demand->save();
					
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
			
			if ($sender && $receiver) {
								
				// Notification System
				$text = "De:".$sender->name." Para:".$receiver->name;
				$storageType = self::STATUS;

				switch($demand->status){
						
						// Programmatically actions.
					case self::LATE:
						$menuType = self::NOMENU; // No need, as it is gonna be a hidden notification.
						// Notify sender's superior
						$senderSuperior = User::find($sender->superior);
						if ($senderSuperior) {
							$title = "(atrasada)";
							$fcmToken = $senderSuperior->gcm;
							$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
							$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						}
						// Notify sender
						$fcmToken = $sender->gcm;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendHiddenMessage($fcmToken, $data);
						// Notify receiver
						$fcmToken = $receiver->gcm;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendHiddenMessage($fcmToken, $data);
						break;
						
						// Normal user actions.
					case self::DONE:
						$title = "(concluída)";
						// Notify sender
						$menuType = self::NOMENU;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$fcmToken = $sender->gcm;
						$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						// Notify sender's superior
						$senderSuperior = User::find($sender->superior);
						if ($senderSuperior) {
							$fcmToken = $senderSuperior->gcm;
							$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						}
						break;
										
						// Admin user actions.
					case self::ACCEPTED:
						// Notify sender
						$title = "(deferida)";
						$fcmToken = $sender->gcm;
						$menuType = self::NOMENU;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						if ($sender->id != $receiver->id)
							$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						// Notify receiver
						$menuType = self::DONEMENU;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$fcmToken = $receiver->gcm;
						$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						break;	
					case self::CANCELED:
						$title = "(cancelada)";
						// Notify sender
						$menuType = self::RESENDMENU;
						$fcmToken = $sender->gcm;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						// Notify receiver
						$menuType = self::NOMENU;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$fcmToken = $receiver->gcm;
						$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						break;
					case self::REJECTED:
						$title = "(indeferida)";
						$fcmToken = $sender->gcm;
						$menuType = self::RESENDMENU;
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
						break;
						
						// Hidden Notification zone.
					case self::POSTPONED:
					case self::UNDEFINED:
					case self::REOPEN:
						// Notify sender
						$fcmToken = $sender->gcm;
						$menuType = self::NOMENU; // No need, as it is gonna be a hidden notification.
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendHiddenMessage($fcmToken, $data);
						// Notify receiver
						$fcmToken = $receiver->gcm;
						$menuType = self::NOMENU; // No need, as it is gonna be a hidden notification.
						$data = ["menu"=>$menuType, "page"=>$menuType, "demand" => $demand, "sender"=>$sender, "receiver"=>$receiver, "type"=>$storageType];
						$this->gcm->sendHiddenMessage($fcmToken, $data);
						break;
				}

				$result = ["success"=>true, "demand"=>$demand, "sender"=>$sender, "receiver"=>$receiver];

			} else {
				$result = ["success"=>false];
			}
			
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
				->orderBy('updated_at','desc')
				->get();
			if( $list ){
				foreach ( $list as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->senderEmail = $sender->email;
						$demand->receiverName = $receiver->name;
						$demand->receiverEmail = $receiver->email;
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
	
	/* (Not being used right now) list of the demand sent to me and admnistrated by me */
	public function anyListMergeReceived(){
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
				->orderBy('updated_at','desc')
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
	
	// List demandS at some moment approved by my admin
	public function anyListReceived(){
		$user = User::whereEmail(Input::get('email'))->first();
	
		if( $user ){
			 
			$list = Demand::whereReceiver($user->id)
				->where('status','<>',self::REJECTED)
				->Where('status','<>',self::POSTPONED)
				->Where('status','<>',self::UNDEFINED)
				->Where('status','<>',self::RESENT)
				->Where('status','<>',self::REOPEN)
				->orderBy('updated_at','desc')
				->get();
			
			// Catch the name of sender and receiver			
			if( $list ){
				foreach ( $list as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->senderEmail = $sender->email;
						$demand->receiverName = $receiver->name;
						$demand->receiverEmail = $receiver->email;
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
	
	// List demands under my aprovement
	public function anyListAdminReceived(){
		$user = User::whereEmail(Input::get('email'))->first();
	
		if( $user ){
			$subusers = User::whereSuperior($user->id)->get(); //workers under user supervision
			$demandsByUser = [];
			
			//return $subusers;
							
			//list all demands where I am the the superior
			foreach( $subusers as $subuser ){
				//if($sub->superior != $user->id) //Dont list if I am the superior
				$demandsByUser[] = Demand::whereReceiver($subuser->id)
					->where('status','<>', self::CANCELED)
					->where('status','<>', self::ACCEPTED)
					->where('status','<>', self::REJECTED)
					->orderBy('updated_at','desc')
					->get();
			}
			
			$sequenceOfDemands = [];
			
			if( $demandsByUser ){
				foreach ( $demandsByUser as $demands ){
					foreach ($demands as $demand){
						$sender = User::find($demand->sender);
						$receiver = User::find($demand->receiver);

						if ($sender && $receiver){
							$demand->senderName = $sender->name;
							$demand->senderEmail = $sender->email;
							$demand->receiverName = $receiver->name;
							$demand->receiverEmail = $receiver->email;
						}else {
							$result = ["success"=>false];
						}
						
						$sequenceOfDemands[] = $demand;
					}
				}
			}		
			
			$collection = Collection::make($sequenceOfDemands);
			$col = "updated_at";			
			$sorted = $collection->sortByDesc(function($col){
				return $col;
			})->values()->all();
			
			$result = ["success"=>true, "list"=>$sorted];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	// List demand not seen yet
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
	
	// List demand already seen
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
	
	// List demands user sent by status
	// Accepted
	// Canceled
	public function anyListDemandByStatus(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			$list = Demand::whereSender($user->id)->whereStatus(Input::get('status'))
						->orderBy('updated_at','desc')
						->get();
			if( $list ){
				foreach ( $list as $demand ){
					$sender = User::find($demand->sender);
					$receiver = User::find($demand->receiver);
					if ($sender && $receiver){
						$demand->senderName = $sender->name;
						$demand->senderEmail = $sender->email;
						$demand->receiverName = $receiver->name;
						$demand->receiverEmail = $receiver->email;
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
	
	// List demands user (as admin) supervised sent by status
	// Accepted
	// Canceled
	public function anyListAdminDemandByStatus(){
		$user = User::whereEmail(Input::get('email'))->first();
		
		if( $user ){
			
			$subusers = User::whereSuperior($user->id)->get(); //workers under user supervision
			$demandsByUser = [];
				
			//list all demands where I am the the superior
			foreach( $subusers as $subuser ){
				$demandsByUser[] = Demand::whereReceiver($subuser->id)
					->whereStatus(Input::get('status'))
					->orderBy('updated_at','desc')
					->get();
			}
			
			$sequenceOfDemands = [];
			
			if( $demandsByUser ){
				foreach ( $demandsByUser as $demands ){
					foreach ($demands as $demand){
						$sender = User::find($demand->sender);
						$receiver = User::find($demand->receiver);

						if ($sender && $receiver){
							$demand->senderName = $sender->name;
							$demand->senderEmail = $sender->email;
							$demand->receiverName = $receiver->name;
							$demand->receiverEmail = $receiver->email;
						}else {
							$result = ["success"=>false];
						}
						
						$sequenceOfDemands[] = $demand;
					}
				}
			}		
			
			$collection = Collection::make($sequenceOfDemands);
			$col = "updated_at";			
			$sorted = $collection->sortByDesc(function($col){
				return $col;
			})->values()->all();
			
			$result = ["success"=>true, "list"=>$sorted];
		} else {
			$result = ["success"=>false];
		}
		
		return $result;
	}
	
	// This method specifically change status to "reject".
	// When a demand is rejected, a reason why should be attached
	// to it. On the DB reason is also a table.
	public function anySetStatusToReject(){
		
		$demand = Demand::find(Input::get("demand"));
				
		if($demand) {
			$demand->status = self::REJECTED;
						
			$reason = new Reason;
			$reason->demand = $demand->id;
			$reason->status = $demand->status;
			$reason->reason = Input::get("reason");
			$reason->comment = Input::get("comment");
			$reason->save();
			
			$demand->reason = $reason->id;
			$demand->save();
			
			$sender = User::find($demand->sender);
			$receiver = User::find($demand->receiver);
						
			if ($sender && $receiver) {
				$title = "(não aceita)";
				$text = $demand->subject;
				$fcmToken = $sender->gcm;
				$menuType = self::RESENDMENU;
				$storageType = self::ADDSENT;
				$data = [
					"menu"=>$menuType, 
					"page"=>$menuType,
					"demand" => $demand,
					"reason" => $reason,
					"sender"=>$sender, 
					"receiver"=>$receiver,
					"type"=>$storageType
				];
				$this->gcm->sendSingleNote($fcmToken, $title, $text, $data);
				
				$result = ["success"=>true, "demand"=>$demand, "sender"=>$sender, "receiver"=>$receiver, "reason"=>$reason];
			} else $result = ["success"=>false];
			
		} else $result = ["success"=>false];
		
		return $result;
		
	}

}