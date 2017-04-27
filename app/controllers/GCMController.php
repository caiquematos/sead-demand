<?php

class GCMController extends \BaseController {
 	const URL = 'https://fcm.googleapis.com/fcm/send';  
  const FCM_API_KEY = "Authorization:key=AIzaSyBuYg-k9W90cBlegIon9DR5Ke9us_byos0";
	
	public function getIndex(){
		return Response::make('Stop snooping around!');
	}

  public function sendNote($registatoin_ids, $message)
  {      
    $fields = [
      'registration_ids' => $registatoin_ids,
      'data' => $message
    ];
		
		$headers = [
      self::FCM_API_KEY,
      'Content-Type:application/json'
    ];
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, self::URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch); //Resquest a push notification
    
    if ($result === FALSE) {
      die('Curl failed: ' . curl_error($ch));
    }
		
    // Close connection
    curl_close($ch);
  }
  
	//Send notificatrion to everyone
  public function broadcast($message, $id)
  {
    $users = User::groupBy("gcm")->get();
    foreach ($users as $user) {
      $registatoin_ids[] = $user->gcm;
    }
    $message = ["message" => $message,"id"=>$id];
    $this->sendNote($registatoin_ids, $message);
  }
	
	 public function anyBroadcast(){
			$title = "Portugal vs. Denmark";
		 	$text = "5 to 1";
			$users = User::groupBy("gcm")->get();
			foreach ($users as $user) {
				$this->sendSingleNote($user->gcm, $title, $text);
			}
		 return $users;
	 }
	
	//Send Single Notification
	public function sendSingleNote($regId, $title, $text, $data){      
		$message = ['title'=>$title, 'text'=>$text];
		
    $fields = [
      'to' => $regId,
      'notification' => $message,
			'data' => $data
    ];
		
    $headers = [
      self::FCM_API_KEY,
      'Content-Type:application/json'
    ];
		
		//Open connection
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, self::URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch); //Resquest a push notification
    
    if ($result === FALSE) {
      die('Curl failed: ' . curl_error($ch));
    }
		
    //Close connection
    curl_close($ch);
  }
	
}