<?php
include 'config.php';
include 'cluster_config.php';
function vk_msg_send($peer_id, $text, $keyboard = null) {
    if (is_null($keyboard)) {
        $request_params = array('message' => $text, 'peer_id' => $peer_id, 'access_token' => TOKEN, 'v' => '5.87');
    } else {
        $request_params = array('message' => $text, 'peer_id' => $peer_id, 'keyboard' => $keyboard, 'access_token' => TOKEN, 'v' => '5.87');
    }
    $get_params = http_build_query($request_params);
    file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
}
function file_download($url, $name, $dir) {
    $filename = $dir . '/' . $name;
    $openFile = fopen($filename, 'w');
    fwrite($openFile, file_get_contents($url));
    fclose($openFile);
    return $filename;
}
define('VK_API_VERSION', '5.103');
define('VK_API_ENDPOINT', "https://api.vk.com/method/");
function _vkApi_call($method, $params = array()) {
    $params['access_token'] = TOKEN;
    $params['v'] = VK_API_VERSION;
    $url = VK_API_ENDPOINT . $method . '?' . http_build_query($params);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    echo $json = curl_exec($curl);
    echo "\n\n";
    curl_close($curl);
    $response = json_decode($json, true);
    return $response['response'];
}
function vkApi_messagesSend($peer_id, $message, $attachments) {
    return _vkApi_call('messages.send', array('peer_id' => $peer_id, 'message' => $message, 'attachment' => $attachments, 'random_id' => rand(0, 300) . $peer_id));
}
function vkApi_docsGetMessagesUploadServer($peer_id) {
    return _vkApi_call('docs.getMessagesUploadServer', array('peer_id' => $peer_id, 'type' => 'doc'));
}
function vkApi_docsSave($file, $title) {
    return _vkApi_call('docs.save', array('file' => $file, 'title' => $title));
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
function uploadDoc($user_id, $file_name, $title) {
    $upload_server_response = vkApi_docsGetMessagesUploadServer($user_id);
    $upload_response = vkApi_upload($upload_server_response['upload_url'], $file_name);
    $save_response = vkApi_docsSave($upload_response['file'], $title);
    return array_pop($save_response);
}
print "Connecting to DB...\n";
$link = new mysqli(HOST, USERNAME, PASS, DBNAME);
if ($link == false) {
    print "No connection to DB.\n";
    exit();
} else {
    print "Connection with DB is established\n";
}
$task_id = $argv[1];
$tmp = mysqli_query($link, "SELECT * FROM `homework` WHERE `db` = '" . $task_id . "'");
if (mysqli_num_rows($tmp) > 0) {
    $tmp = mysqli_fetch_array($tmp);
    $id_checker = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `users` WHERE `vk_id` = '" . $argv[2] . "'"));
    if ($tmp['group_id'] == $id_checker['group_id']) {
        if ($tmp['files'] == '') {
            vk_msg_send($argv[2], 'К заданию не прикреплены файлы.');
        } else {
            $c = 0;
            if (is_numeric($tmp['files'])) {
                $file_info = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `user_files` WHERE `file_id` = '" . $tmp['files'] . "'"));
                $dir = './user_files/' . md5(password_hash(time() . (time() + 1), PASSWORD_DEFAULT));
                mkdir($dir);
                $file_link = json_decode(file_get_contents('https://api.telegram.org/bot' . $bot_tokens[$file_info['tg_bot_id']] . '/getFile?file_id=' . $file_info['tg_file_id']));
                $file_link = 'https://api.telegram.org/file/bot' . $bot_tokens[$file_info['tg_bot_id']] . '/' . $file_link->result->file_path;
                mysqli_query($link, "UPDATE `load_balancer` SET `bot_load` = `bot_load` + 1 WHERE `id` = '" . $file_info['tg_bot_id'] . "'");
                $path = file_download($file_link, $file_info['file_name'], $dir);
                $wtf = uploadDoc($argv[2], $path, $file_info['file_name']);
                echo json_encode($wtf) . "\n\n";
                $att = 'doc' . $wtf['owner_id'] . '_' . $wtf['id'];
                unlink($path);
                rmdir($dir);
            } else {
                $local_files_ids = explode(',', $tmp['files']);
                while ($c < count($local_files_ids)) {
                    $file_info = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `user_files` WHERE `file_id` = '" . $local_files_ids[$c] . "'"));
                    $dir = './user_files/' . md5(password_hash(time() . (time() + 1), PASSWORD_DEFAULT));
                    mkdir($dir);
                    $file_link = json_decode(file_get_contents('https://api.telegram.org/bot' . $bot_tokens[$file_info['tg_bot_id']] . '/getFile?file_id=' . $file_info['tg_file_id']));
                    $file_link = 'https://api.telegram.org/file/bot' . $bot_tokens[$file_info['tg_bot_id']] . '/' . $file_link->result->file_path;
                    mysqli_query($link, "UPDATE `load_balancer` SET `bot_load` = `bot_load` + 1 WHERE `id` = '" . $file_info['tg_bot_id'] . "'");
                    $path = file_download($file_link, $file_info['file_name'], $dir);
                    $wtf = uploadDoc($argv[2], $path, $file_info['file_name']);
                    if ($c == 0) {
                        $att = 'doc' . $wtf['owner_id'] . '_' . $wtf['id'];
                    } else {
                        $att = $att . ',doc' . $wtf['owner_id'] . '_' . $wtf['id'];
                    }
                    unlink($path);
                    rmdir($dir);
                    $c++;
                }
            }
            vkApi_messagesSend($argv[2], 'Файлы готовы.', $att);
        }
    } else {
        vk_msg_send($argv[2], 'Вы запрашивали файлы, к которым у Вас нет доступа.');
    }
} else {
    vk_msg_send($argv[2], 'Увы, задания, по которому Вы запрашивали файлы, не существует.');
}
exit();
?>
