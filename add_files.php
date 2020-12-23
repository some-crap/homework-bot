<?php
include 'config.php';
include 'cluster_config.php';
function tg_msg_send($chat_id, $text, $keyboard = null) {
    if (is_null($keyboard)) {
        $request_params = array('text' => $text, 'chat_id' => $chat_id);
    } else {
        $request_params = array('text' => $text, 'chat_id' => $chat_id, 'reply_markup' => $keyboard);
    }
    $get_params = http_build_query($request_params);
    file_get_contents('https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage?' . $get_params);
}
function file_download($url, $ex) {
    $ch = curl_init();
    $filename = md5(password_hash(time() . (time() + 1), PASSWORD_DEFAULT)) . '.' . $ex;
    $filename;
    $openFile = fopen('./user_files/' . $filename, 'w');
    fwrite($openFile, file_get_contents($url));
    fclose($openFile);
    return $filename;
}
function tgApi_upload($file_name, $bot_token) {
    $curl = curl_init('https://api.telegram.org/bot' . $bot_token . '/sendDocument');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, array('document' => new CURLfile($file_name), 'chat_id' => - 376331512));
    return curl_exec($curl);
}
function vk_check_files($json, $after_dot) {
    $array = json_decode($json);
    $c = 0;
    $number = 0;
    while ($array[$c]) {
        $width = 0;
        $local_c = 0;
        if ($array[$c]->type == "doc") {
            if ($array[$c]->doc->size <= 19922944) {
                $format = $array[$c]->doc->ext;
                if ($after_dot[$format]) {
                    $return[$number] = array('file_name' => file_download($array[$c]->doc->url, $format), 'title' => $array[$c]->doc->title, 'size' => $array[$c]->doc->size);
                    $number++;
                }
            }
        }
        if ($array[$c]->type == "photo") {
            $sizes = $array[$c]->photo->sizes;
            while ($sizes[$local_c]) {
                if ($sizes[$local_c]->width > $width) {
                    $width = $sizes[$local_c]->width;
                    $url = $sizes[$local_c]->url;
                }
                $local_c++;
            }
            $ex = explode('.', $url);
            $ex = $ex[count($ex) - 1];
            $fname = file_download($url, $ex);
            $return[$number] = array('file_name' => $fname, 'title' => $fname, 'size' => 0);
            $number++;
        }
        $c++;
    }
    if ($number > 0) {
        return $return;
    } else {
        return false;
    }
}
$link = new mysqli(HOST, USERNAME, PASS, DBNAME);
$files['files'] = urldecode($argv[3]);
$files['group_id'] = $argv[2];
$files['task_id'] = $argv[1];
$files_to_upload = vk_check_files($files['files'], $after_dot);
$file_number = 0;
if ($files_to_upload) {
    while ($files_to_upload[$file_number]) {
        $tg_bot_id = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `load_balancer` ORDER BY `load_balancer`.`bot_load` ASC LIMIT 1"));
        $tg_bot_id = $tg_bot_id['id'];
        mysqli_query($link, "UPDATE `load_balancer` SET `bot_load` = `bot_load` + 1 WHERE `id` = '" . $tg_bot_id . "'");
        $uploadfile = $files_to_upload[$file_number]['file_name'];
        $tg_file = json_decode(tgApi_upload('./user_files/' . $uploadfile, $bot_tokens[$tg_bot_id]));
        unlink('./user_files/' . $uploadfile);
        $file_id = $tg_file->result->document->file_id;
        $stmt = $link->prepare("INSERT INTO `user_files` SET `tg_file_id` = ?, `file_name` = ?, `file_size` = ?, `tg_bot_id` = ?, `group_id` = ?, `owner_id` = 0");
        $stmt->bind_param('ssiii', $file_id, $files_to_upload[$file_number]['title'], $files_to_upload[$file_number]['size'], $tg_bot_id, $files['group_id']);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $temp_hw = mysqli_query($link, "SELECT * FROM `homework` WHERE `db` = '" . $files['task_id'] . "'");
        if (mysqli_num_rows($temp_hw) == 1) {
            $temp_hw = mysqli_fetch_array($temp_hw);
            if ($temp_hw['files'] == '') {
                mysqli_query($link, "UPDATE `homework` SET `files` = '" . $newId . "' WHERE `db` = '" . $files['task_id'] . "'");
            } else {
                $newId = $temp_hw['files'] . ',' . $newId;
                mysqli_query($link, "UPDATE `homework` SET `files` = '" . $newId . "' WHERE `db` = '" . $files['task_id'] . "'");
            }
        }
        $file_number++;
    }
}
exit();
?>