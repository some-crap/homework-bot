<?php
ini_set("log_errors", 1);
ini_set("error_log", "logsbot.txt");
include 'config.php';
$access_token = TOKEN;
$group_id = 171524656; // Да-да, это тоже нужно было вынести в конфиг по хорошему
function GetLongPollServer($group_id, $access_token){
$request_params = array(
	'group_id' => $group_id,
	'access_token' => $access_token,
	'v' => '5.102' 
);
$get_params = http_build_query($request_params); 
$LongPollServer = json_decode(file_get_contents('https://api.vk.com/method/groups.getLongPollServer?'. $get_params));
print "LongPoll Server is found\n";
return $LongPollServer;
}
function tg_msg_send($chat_id,$text,$keyboard=null){
	if(is_null($keyboard)){
    	$request_params = array(
        	'text' => $text, 
    		'chat_id' => $chat_id
		);
	}
	else{
		$request_params = array(
    		'text' => $text, 
            'chat_id' => $chat_id, 
        	'reply_markup' => $keyboard
		);
	}
	$get_params = http_build_query($request_params); 
	file_get_contents('https://api.telegram.org/bot'.TG_TOKEN.'/sendMessage?'. $get_params);
}
include "qrlib.php";
define('VK_API_VERSION', '5.122');
define('VK_API_ENDPOINT', "https://api.vk.com/method/"); 
function _vkApi_call($method, $params = array()) { 
  $params['access_token'] = TOKEN; 
  $params['v'] = VK_API_VERSION; 
  $url = VK_API_ENDPOINT.$method.'?'.http_build_query($params); 
  $curl = curl_init($url); 
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
  $json = curl_exec($curl); 
  curl_close($curl); 
  $response = json_decode($json, true); 
  return $response['response']; 
} 
function vkApi_messagesSend($peer_id, $message, $attachments) { 
  return _vkApi_call('messages.send', array( 
    'peer_id' => $peer_id, 
    'message' => $message, 
    'attachment' => $attachments,
    'random_id' => $peer_id.time().rand(0,500)
  )); 
} 
function vkApi_photosGetMessagesUploadServer($peer_id) { 
  return _vkApi_call('photos.getMessagesUploadServer', array( 
    'peer_id' => $peer_id, 
  )); 
}
function vkApi_photosSaveMessagesPhoto($photo, $server, $hash) { 
   return _vkApi_call('photos.saveMessagesPhoto', array( 
    'photo' => $photo, 
    'server' => $server, 
    'hash' => $hash, 
  )); 
} 
function vkApi_upload($url, $file_name) { 
  $curl = curl_init($url); 
  curl_setopt($curl, CURLOPT_POST, true); 
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
  curl_setopt($curl, CURLOPT_POSTFIELDS, array('file' => new CURLfile($file_name))); 
  $json = curl_exec($curl); 
  curl_close($curl); 
  return json_decode($json, true); 
} 
function uploadPhoto($user_id, $file_name) { 
  $upload_server_response = vkApi_photosGetMessagesUploadServer($user_id); 
  $upload_response = vkApi_upload($upload_server_response['upload_url'], $file_name);
  $save_response = vkApi_photosSaveMessagesPhoto($upload_response['photo'], $upload_response['server'], $upload_response['hash']); 
  return array_pop($save_response); 
}
$server = GetLongPollServer($group_id, $access_token);
$ts = $server -> response -> ts;
$server_key = $server -> response -> key;
$server = $server -> response -> server;
$link = new mysqli(HOST, USERNAME, PASS, DBNAME);
print "Connected to DB\n";
print "Polling started\n";
while(true){
    if($link == false){
        print "DB connection is lost.\nReconnectiong...\n";
        while($link == false){
            $link = new mysqli(HOST, USERNAME, PASS, DBNAME);
        }
        print "Connected\n";
    }
    $is_active = time() + 600;
    mysqli_query($link, "UPDATE `system_status` SET `active_time`= ".$is_active." WHERE `sys_id` = 2");
    $data = json_decode(file_get_contents($server."?act=a_check&key=".$server_key."&ts=".$ts."&wait=25"));
    if(!isset($data -> updates)){
        print "Error #".$data -> failed."\n";
        print "The connection is lost.\n Connecting...\n";
        $server = GetLongPollServer($group_id, $access_token);
        $ts = $server -> response -> ts;
        $server_key = $server -> response -> key;
        $server = $server -> response -> server;
    }
    else{
        $ts = $data -> ts;
        $updates = $data -> updates;
        $c = 0;
        while($updates[$c]){
            $peer_id = null;
    	    $from_id = null;
    	    $msg = null;
    	    $payload = null;
    	    $db = null;
    	    $group_token = null;
			if($updates[$c]->type == 'message_reply'){
				$peer_id = $updates[$c]->object->peer_id;  
				$from_id = $updates[$c]->object->from_id;
				$msg = $updates[$c]->object->text;
				$db = mysqli_fetch_assoc($query);
				if((mb_strtolower(mb_substr($msg, 0, 11)) == "ключ группы" or mb_strtolower(mb_substr($msg, 0, 17)) == "новый ключ группы") and $peer_id < "2000000000"){
				    $group_token = explode('///', $msg);
				    $group_token = $group_token[1];
		    		$file_name = md5(time().$from_id.$ts.$c).'.png';
                    QRcode::png($group_token, $file_name, 'L', 4, 2);
                    $photo = uploadPhoto($peer_id, $file_name);
                    vkApi_messagesSend($peer_id, 'Можешь дать и QR код', 'photo'.$photo['owner_id'].'_'.$photo['id']); 
                    unlink($file_name);
				}    
		    }
            $c++;
	    }
    }
}
?>