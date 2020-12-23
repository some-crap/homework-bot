<?php
include 'config.php';
function file_download($url) {
    $temp = explode('.', $url);
    $temp = $temp[count($temp) - 1];
    $ch = curl_init();
    $filename = md5(password_hash(time() . (time() + 1), PASSWORD_DEFAULT)) . '.' . $temp;
    $filename;
    curl_setopt($ch, CURLOPT_URL, $url);
    $openFile = fopen($filename, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $openFile);
    curl_exec($ch);
    curl_close($ch);
    fclose($openFile);
    return $filename;
}
function vk_check_files($json) {
    $array = json_decode($json);
    $c = 0;
    $photos = 0;
    while ($array[$c]) {
        $width = 0;
        $local_c = 0;
        if ($array[$c]->type == "photo") {
            $sizes = $array[$c]->photo->sizes;
            while ($sizes[$local_c]) {
                if ($sizes[$local_c]->width > $width) {
                    $width = $sizes[$local_c]->width;
                    $url = $sizes[$local_c]->url;
                }
                $local_c++;
            }
            $return[$photos] = array('file_name' => file_download($url));
            $photos++;
        }
        $c++;
    }
    //echo "\n\n\n".json_encode($return)."\n\n\n";
    if ($photos > 0) {
        return $return;
    } else {
        return false;
    }
}
define('VK_API_VERSION', '5.122'); // по хорошему это всё говно нужно вынести в functions.php, но мне было лень
define('VK_API_ENDPOINT', "https://api.vk.com/method/");
function _vkApi_call($method, $params = array()) {
    $params['access_token'] = TOKEN;
    $params['v'] = VK_API_VERSION;
    $url = VK_API_ENDPOINT . $method . '?' . http_build_query($params);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($json, true);
    return $response['response'];
}
function vkApi_messagesSend($peer_id, $message, $attachments) {
    return _vkApi_call('messages.send', array('peer_id' => $peer_id, 'message' => $message, 'attachment' => $attachments, 'random_id' => time().$peer_id));
}
function vkApi_photosGetMessagesUploadServer($peer_id) {
    return _vkApi_call('photos.getMessagesUploadServer', array('peer_id' => $peer_id,));
}
function vkApi_photosSaveMessagesPhoto($photo, $server, $hash) {
    return _vkApi_call('photos.saveMessagesPhoto', array('photo' => $photo, 'server' => $server, 'hash' => $hash,));
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
$link = new mysqli(HOST, USERNAME, PASS, DBNAME);
$mass_data['group_id'] = $argv[1];
$mass_data['text'] = urldecode($argv[2]);
$mass_data['files'] = urldecode($argv[3]);
$users = mysqli_query($link, "SELECT * FROM `users` WHERE `group_id` = '" . $mass_data['group_id'] . "'");
if ($mass_data['files'] != '' and $mass_data['files'] != null) {
    $files = vk_check_files($mass_data['files']);
}
while ($mass_ids = mysqli_fetch_assoc($users)) {
    if ($files == false) {
        vk_msg_send($mass_ids['vk_id'], $mass_data['text']);
    } else {
        $c = 0;
        $photo_array = null;
        $array_len = count($files) - 1;
        while ($c <= $array_len) {
            $temp_photo = uploadPhoto($mass_ids['vk_id'], $files[$c]['file_name']);
            if ($photo_array == null) {
                $photo_array = 'photo' . $temp_photo['owner_id'] . '_' . $temp_photo['id'];
            } else {
                $photo_array = $photo_array . ',' . 'photo' . $temp_photo['owner_id'] . '_' . $temp_photo['id'];
            }
            echo "\n\n" . $photo_array . "\n\n";
                $c++;
        }
        vkApi_messagesSend($mass_ids['vk_id'], $mass_data['text'], $photo_array);
    }
}
if ($files != false) {
    $c = 0;
    while ($files[$c]) {
        unlink($files[$c]['file_name']);
        $c++;
    }
    $files = null;
}
exit();
?>