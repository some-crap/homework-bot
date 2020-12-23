<?php
define ('PASS', '');
define ('DBNAME', '');
define ('USERNAME', '');
define ('HOST', '');//Данные для коннекта в бд
define ('TOKEN', '');//Токен от группы вк
function bgexec($cmd)
{
    exec($cmd . " > /dev/null &");
}
function vk_msg_send($peer_id, $text, $keyboard = null) {
    if (is_null($keyboard)) {
        $request_params = array('message' => $text, 'peer_id' => $peer_id, 'access_token' => TOKEN, 'v' => '5.122', 'random_id' => time().$peer_id);
    } else {
        $request_params = array('message' => $text, 'peer_id' => $peer_id, 'keyboard' => $keyboard, 'access_token' => TOKEN, 'v' => '5.122', 'random_id' => time().$peer_id);
    }
    $get_params = http_build_query($request_params);
    file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
}