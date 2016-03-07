<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Ws implements MessageComponentInterface {
	protected $clients;
	protected $next_conn_id = 0;
	protected $names = [];
	
	
	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}
	
	public function onOpen(ConnectionInterface $conn) {
		$conn_id = $this->next_conn_id++;
		
		$name = "user" . $conn_id;
		
		$info = [
			"id" => $conn_id,
			"conn" => $conn,
			"auth" => false,
			"name" => $name,
			"peers" => []
		];
		$this->clients->attach($conn, $info);
		
		$this->names[] = $name;
		
		$this->syncNames($name);
		
		echo "WS CONNECTED #$conn_id: $name ($color_value)\n";
	}
	
	public function onMessage(ConnectionInterface $from_conn, $payload) {
		try {
			$data = json_decode($payload,true);
			$info = $this->clients->offsetGet($from_conn);
			
			
			switch ($data['key']) {				
				case 37:
					$info['v'] = ['x'=>-9,'y'=>0];
					break;
				case 38:
					$info['v'] = ['x'=>0,'y'=>-9];
					break;				
				case 39:
					$info['v'] = ['x'=>9,'y'=>0];
					break;				
				case 40:
					$info['v'] = ['x'=>0,'y'=>9];
					break;
			}
			$p = $data['pos'];
			$this->clients->offsetSet($from_conn,$info);
			$this->sendUpdate($from_conn,$p);			
		} catch (\Exception $e) {
			$this->sendError($from_conn, $e->getMessage());
		}
	}

	public function onClose(ConnectionInterface $conn) {
		$info = $this->clients->offsetGet($conn);
		$this->clients->detach($conn);
		
		printf("WS DISCONNECTED: %s\n", $info['name']);
	}
	
	public function onError(ConnectionInterface $conn, \Exception $e) {
		$conn->close();
	}
	
	private function validateName($name) {
		if (strlen($name) < 3) {
			throw new \Exception("Display name ($name) must be at least 3 characters long");
		}
		
		if (strlen($name) > 20) {
			throw new \Exception("Display name ($name) cannot exceed 20 characters long");
		}
		if (!preg_match('/^[A-Za-z]([A-Za-z0-9]+[ ._-])*[A-Za-z0-9]+$/', $name)) {
			throw new \Exception("Display name ($name) contains illegal characters");
		}
	}
	
	private function validateColor($color) {
		if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
			throw new \Exception("Invalid color selected: $color");
		}
	}
	
	private function sendError($conn, $msg) {
		$conn->send(json_encode([
			"type" => "error",
			"error" => $msg,
		]));
	}
	
	private function syncNames($me){
		foreach($this->names as $name){	
			foreach ($this->clients as $notify){
				$notifyInfo = $this->clients->offsetGet($notify);
				if (is_array($notifyInfo['peers']) && in_array($name,$notifyInfo['peers'])) continue;
				if (!is_array($notifyInfo['peers'])) $notifyInfo['peers'] = [];
				$notifyInfo['peers'][] = $name;
				$player = [
					"type" => "newdude",
					"valid" => true,
					"name" => $name
				];
				if ($notifyInfo['name'] == $me) $player['current'] = TRUE;
				$notify->send(json_encode($player));
				$this->clients->offsetSet($notify,$notifyInfo);
			}
		}		
	}	
	
	
	private function sendUpdate($from_conn,$p){
		$players = [];
		foreach ($this->clients as $client){
			$clientInfo = $this->clients->offsetGet($client);
			$tmp = [];
			$tmp['name'] = $clientInfo['name'];
			$tmp['v'] = (is_array($clientInfo['v']))?$clientInfo['v']:['x'=>0,'y'=>0];
			if ($client == $from_conn) $tmp['p'] = $p;
			$players[] = $tmp;
		}
		$updateData = [
			'type'=>'update',
			'players'=>$players
			];
		
		foreach ($this->clients as $client)
		{
			$client->send(json_encode($updateData));	
		}			
	}
}

