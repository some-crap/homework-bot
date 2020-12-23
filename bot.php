<?php
ini_set("log_errors", 1);
ini_set("error_log", "logsbot.txt");
include 'config.php';
$keyboards = array("main" => '{ 
    	"one_time": false, 
		"buttons": [ 
		[{ 
		"action": { 
		"type": "text",
		"label": "Узнать",
		"payload": "{\"button\": \"get\"}"		
	    }, 
	    "color": "primary" 
		},
		{ 
		"action": { 
		"type": "text",
		"label": "Добавить",
		"payload": "{\"button\": \"add\"}"	
		}, 
		"color": "primary" 
		}
		],
		[{ 
    	"action": { 
		"type": "text",
		"label": "Настройки",
		"payload": "{\"button\": \"settings\"}"
		}, 
		"color": "primary" 
		},
		{ 
		"action": { 
		"type": "text",
		"label": "Скачать файл",
		"payload": "{\"button\": \"get_file\"}"	
		}, 
		"color": "primary" 
		},
		{ 
		"action": { 
		"type": "text",
		"label": "Удалить",
		"payload": "{\"button\": \"delete\"}"
		}, 
		"color": "primary" 
		}] 
		] 
		}',
	'zoom' => 
        '{ 
		"one_time": false, 
		"buttons": [ 
		[{ 
		"action": { 
    	"type": "text",
		"label": "Отмена",
		"payload": "{\"button\": \"cancel\"}"			
		}, 
		"color": "negative" 
		}] 
		] 
    	}');
$access_token = TOKEN;
$group_id = 171524656; // это тоже нужно было бы в конфиг вынести
function GetLongPollServer($group_id, $access_token) {
    $request_params = array('group_id' => $group_id, 'access_token' => $access_token, 'v' => '5.122');
    $get_params = http_build_query($request_params);
    $LongPollServer = json_decode(file_get_contents('https://api.vk.com/method/groups.getLongPollServer?' . $get_params));
    print "LongPoll Server is found\n";
    return $LongPollServer;
}
function tg_msg_send($chat_id, $text, $keyboard = null) {
    if (is_null($keyboard)) {
        $request_params = array('text' => $text, 'chat_id' => $chat_id);
    } else {
        $request_params = array('text' => $text, 'chat_id' => $chat_id, 'reply_markup' => $keyboard);
    }
    $get_params = http_build_query($request_params);
    file_get_contents('https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage?' . $get_params);
}
$server = GetLongPollServer($group_id, $access_token);
$ts = $server->response->ts;
$server_key = $server->response->key;
$server = $server->response->server;
print "Connecting to DB...\n";
$link = new mysqli(HOST, USERNAME, PASS, DBNAME);
if ($link->connect_errno) {
    print "No connection to DB.\n";
    exit();
} else {
    print "Connection with DB is established\n";
}
print "Polling started\n";
$start_time = time();
while (true) {
    if ($start_time < (time() - 3600)) {
        bgexec("php bot.php");
        exit();
    }
    $is_active = time() + 300;
    mysqli_query($link, "UPDATE `system_status` SET `active_time`= " . $is_active . " WHERE `sys_id` = 1");
    $data = json_decode(file_get_contents($server . "?act=a_check&key=" . $server_key . "&ts=" . $ts . "&wait=25"));
    echo json_encode($data);
    if (!isset($data->updates)) {
        print "Error #" . $data->failed . "\n";
        print "The connection is lost.\n Connecting...\n";
        $server = GetLongPollServer($group_id, $access_token);
        $ts = $server->response->ts;
        $server_key = $server->response->key;
        $server = $server->response->server;
    } else {
        $ts = $data->ts;
        $updates = $data->updates;
        $c = 0;
        while ($updates[$c]) {
            $peer_id = null;
            $from_id = null;
            $msg = null;
            $payload = null;
            $db = null;
            $text = null;
            $query = null;
            $temp = null;
            $key = null;
            $group_token = null;
            $time = null;
            $ex_time = null;
            $gdpr_token = null;
            $sent = null;
            $check = null;
            $mass_id = null;
            $files = null;
            $task_id = null;
            if ($updates[$c]->type == 'message_new') {
                $peer_id = $updates[$c]->object->message->peer_id;
                $from_id = $updates[$c]->object->message->from_id;
                $msg = $updates[$c]->object->message->text;
                $from_id = mysqli_real_escape_string($link, $from_id);
                $payload = json_decode($updates[$c]->object->message->payload)->button;
                if (mb_strtolower($msg) == "пинг") {
                    vk_msg_send($peer_id, 'понг!');
                }
                if($from_id == "450829055"){ // а тута свой id подствить нужно.
                    if (mb_strtolower($msg) == "/restart") {
                        vk_msg_send($peer_id, 'Рестартим бота...');
                        bgexec("php bot.php");
                        exit();
                    }
                    if (mb_strtolower($msg) == "/memory"){
                        $memory = file_get_contents("/proc/meminfo");
                        vk_msg_send($peer_id, $memory);
                    }
                    if (mb_substr(mb_strtolower($msg), 0, 5) == "/exec"){
                        $exec = base64_encode(mb_substr($msg, 6));
                        vk_msg_send($peer_id, "Отправили на выпонение.");
                        bgexec("php exec.php $exec $peer_id");
                    }
                }
                if ($msg == "/debug") {
                    vk_msg_send($peer_id, "@id" . $from_id . ",<br>from_id: " . $from_id . "<br>peer_id: " . $peer_id . "<br>".getmypid(), '');
                }
                $query = mysqli_query($link, "SELECT * FROM `users` WHERE `vk_id` ='" . $from_id . "'");
                $db = mysqli_fetch_assoc($query);
                if ($peer_id != $from_id) {
                    if (mb_strtolower($msg) == 'узнать дз' or mb_strtolower($msg) == '/дз' or mb_strtolower($msg) == '[club171524656|@hwonline] узнать дз' or mb_strtolower($msg) == '[club171524656|бот домашкв] узнать дз') {
                        if (mysqli_num_rows($query) == 0) {
                            vk_msg_send($peer_id, "Ты не зареган. Напиши мне в лс. ;)");
                        } else {
                            if ($db['group_id'] == 0) {
                                vk_msg_send($peer_id, "Твой аккаунт не привязан к группе. Напиши мне в лс. ;)");
                            }
                        }
                        $temp = mysqli_query($link, "SELECT * FROM `homework` WHERE `group_id` = " . $db['group_id'] . " AND `to_time` > " . time() . " ORDER BY `homework`.`to_time` ASC");
                        $text = '';
                        while ($temp_msg = mysqli_fetch_assoc($temp)) {
                            if ($temp_msg['files'] == '') {
                                $text = $text . $temp_msg['lesson'] . '<br>' . $temp_msg['task'] . '<br> К ' . date('d.m.Y', $temp_msg['to_time']) . '<br> id задания: ' . $temp_msg['db'] . '<br><br>';
                            } else {
                                $files_amount = count(explode(',', $temp_msg['files']));
                                $text = $text . $temp_msg['lesson'] . '<br>' . $temp_msg['task'] . '<br>📎 Прикреплено файлов: ' . $files_amount . '<br> К ' . date('d.m.Y', $temp_msg['to_time']) . '<br> id задания: ' . $temp_msg['db'] . '<br><br>';
                            }
                            if (mb_strlen($text) >= 900) {
                                vk_msg_send($peer_id, $text);
                                $text = '';
                                $sent = '1';
                            }
                        }
                        if ($text == '' and $sent != '1') {
                            $text = 'Заданий нет.';
                        } else {
                            vk_msg_send($peer_id, $text);
                        }
                    }
                } else {
                    if (mysqli_num_rows($query) == 0) {
                        vk_msg_send($peer_id, "Привет! Зайди в существующую группу или создай свою!\n\nПродолжая использовать бота, вы соглашаетесь, что вся информация добавляется пользователями бота на добровольной основе, и разработчики сервиса не несут никакой отвественности за её содержание. Пользователь несёт ответственность сам за каждое своё действие.\nТакже бот собирает необходимую публичную информацию о пользователе. Всю информацию, которая необходима боту для работы, можно будет посмотреть в разделе настроек.", '{ 
							"one_time": false, 
							"buttons": [ 
							[
							{ 
							"action": { 
							"type": "text",
							"label": "Присоединиться",
							"payload": "{\"button\": \"join\"}"
							}, 
							"color": "primary" 
							},
							{ 
							"action": { 
							"type": "text",
							"label": "Создать",
							"payload": "{\"button\": \"create\"}"
							}, 
							"color": "primary" 
							}] 
							] 
						}');
                        mysqli_query($link, "INSERT INTO `users`(`user_condition`, `vk_id`, `group_id`, `lesson`, `task`, `files`, `mass`) VALUES ('ready', '" . $from_id . "', '0', '', '', '', '0')");
                    } else {
                        if ($db['group_id'] == 0) {
                            if ($db['user_condition'] == 'ready') {
                                if ($payload == "join") {
                                    vk_msg_send($peer_id, 'Перешли мне сообщение с ключом группы.');
                                    mysqli_query($link, "UPDATE `users` SET `user_condition`='need_token' WHERE `vk_id` = '" . $from_id . "'");
                                } elseif ($payload == "create") {
                                    $group_token = md5($from_id . time());
                                    vk_msg_send($peer_id, 'Ключ группы: ///' . $group_token . '///<br>Можете передавать его своим одноклассникам или однокурсникам!', $keyboards['main']);
                                    mysqli_query($link, "INSERT INTO `groups` SET `group_token`='" . $group_token . "', `group_name` = '', `group_description` = ''");
                                    $temp = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `groups` WHERE `group_token`='" . $group_token . "'"));
                                    mysqli_query($link, "UPDATE `users` SET `group_id`='" . $temp['group_id'] . "' WHERE `vk_id` = '" . $from_id . "'");
                                } else {
                                    vk_msg_send($peer_id, "Привет! Зайди в существующую группу или создай свою!", '{ 
										"one_time": false, 
										"buttons": [ 
										[
										{ 
										"action": { 
										"type": "text",
										"label": "Присоединиться",
										"payload": "{\"button\": \"join\"}"
										}, 
										"color": "primary" 
										},
										{ 
										"action": { 
										"type": "text",
										"label": "Создать",
										"payload": "{\"button\": \"create\"}"
										}, 
										"color": "primary" 
										}] 
										] 
									} ');
                                }
                            }
                            if ($db['user_condition'] == 'need_token') {
                                $msg = mysqli_real_escape_string($link, $msg);
                                $temp = json_encode($updates[$c]);
                                $split = explode('\/\/\/', $temp);
                                if (isset($split[1])) {
                                    $msg = mysqli_real_escape_string($link, $split[1]);
                                }
                                $temp = mysqli_query($link, "SELECT * FROM `groups` WHERE `group_token`='" . $msg . "'");
                                if (mysqli_num_rows($temp) == 0) {
                                    vk_msg_send($peer_id, 'Неверный ключ.');
                                    mysqli_query($link, "UPDATE `users` SET `user_condition`='ready' WHERE `vk_id` = '" . $from_id . "'");
                                } else {
                                    vk_msg_send($peer_id, 'Готово.', $keyboards['main']);
                                    $temp = mysqli_fetch_array($temp);
                                    mysqli_query($link, "UPDATE `users` SET `user_condition`='ready', `group_id` = '" . $temp['group_id'] . "' WHERE `vk_id` = '" . $from_id . "'");
                                }
                            }
                        } else {
                            if ($db['user_condition'] == 'ready') {
                                if ($payload == 'gdpr') {
                                    $time = time();
                                    $ex_time = time() + 600;
                                    $gdpr_token = md5(time() . $from_id . file_get_contents('php://input') . $ex_time);
                                    mysqli_query($link, "INSERT INTO `tokens` SET `vk_id` = '$from_id', `token` = '$gdpr_token', `time`= '$time', `expiration_time` = '$ex_time'");
                                    echo "\n".$time."\n";
                                    vk_msg_send($peer_id, "Получить все данные, которые мы знаем о Вас можно в течение 10 минут по ссылке: <br>https://api.matveev.app/hw/GDPR.php?token=" . $gdpr_token . " <br><br>После того, как ссылка устареет, Вы можете запросить новую.<br><br>Мы обязаны предоставлять Вам эти данные для соблюдения Генерального регламента о защите персональных данных (GDPR)");
                                }
                                if ($payload == 'zoom') {
                                    vk_msg_send($peer_id, "Пока не работает.");
                                    vk_msg_send($peer_id, "Здесь можно оставить ссылку или идентификатор и пакроль от конференции, написать, когда нужно напомнить об уроке", $keyboards['zoom']);
                                }
                                if ($payload == 'add') {
                                    vk_msg_send($peer_id, 'Введите название предмета.', '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Отмена",
									"payload": "{\"button\": \"cancel\"}"			
									}, 
									"color": "negative" 
									}] 
									] 
								}');
                                    mysqli_query($link, "UPDATE `users` SET `user_condition`='need_lesson' WHERE `vk_id` = '" . $from_id . "'");
                                }
                                if ($payload == 'get') {
                                    $temp = mysqli_query($link, "SELECT * FROM `homework` WHERE `group_id` = " . $db['group_id'] . " AND `to_time` > " . time() . " ORDER BY `homework`.`to_time` ASC");
                                    $text = '';
                                    while ($temp_msg = mysqli_fetch_assoc($temp)) {
                                        if ($temp_msg['files'] == '') {
                                            $text = $text . $temp_msg['lesson'] . '<br>' . $temp_msg['task'] . '<br> К ' . date('d.m.Y', $temp_msg['to_time']) . '<br> id задания: ' . $temp_msg['db'] . '<br><br>';
                                        } else {
                                            $files_amount = count(explode(',', $temp_msg['files']));
                                            $text = $text . $temp_msg['lesson'] . '<br>' . $temp_msg['task'] . '<br>📎 Прикреплено файлов: ' . $files_amount . '<br> К ' . date('d.m.Y', $temp_msg['to_time']) . '<br> id задания: ' . $temp_msg['db'] . '<br><br>';
                                        }
                                        if (mb_strlen($text) >= 900) {
                                            vk_msg_send($peer_id, $text);
                                            $text = '';
                                            $sent = '1';
                                        }
                                    }
                                    if ($text == '' and $sent != '1') {
                                        $text = 'Заданий нет.';
                                        vk_msg_send($peer_id, $text);
                                    } else {
                                        vk_msg_send($peer_id, $text);
                                    }
                                }
                                if ($payload == 'settings') {
                                    vk_msg_send($peer_id, 'Настройки.', '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Ключи",
									"payload": "{\"button\": \"keys\"}"
									}, 
									"color": "primary" 
									},
									{ 
									"action": { 
									"type": "text",
									"label": "GDPR",
									"payload": "{\"button\": \"gdpr\"}"			
									}, 
									"color": "primary" 
									}
									],
									[{ 
									"action": { 
									"type": "text",
									"label": "Рассылка",
									"payload": "{\"button\": \"mass\"}"	
									}, 
									"color": "primary" 
									},
									{ 
									"action": { 
									"type": "text",
									"label": "Назад",
									"payload": "{\"button\": \"cancel\"}"			
									}, 
									"color": "negative" 
									}]
									] 
								}');
                                }
                                if ($payload == 'keys') {
                                    vk_msg_send($peer_id, 'Настройки.', '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Посмотреть ключ",
									"payload": "{\"button\": \"watch_key\"}"					
									}, 
									"color": "primary" 
									},
									{ 
									"action": { 
									"type": "text",
									"label": "Сгенерировать",
									"payload": "{\"button\": \"gen_key\"}"			
									}, 
									"color": "primary" 
									}],
									[{ 
									"action": { 
									"type": "text",
									"label": "Выйти",
									"payload": "{\"button\": \"exit_group\"}"			
									}, 
									"color": "primary" 
									},
									{ 
									"action": { 
									"type": "text",
									"label": "Назад",
									"payload": "{\"button\": \"cancel\"}"				
									}, 
									"color": "negative" 
									}]
									] 
								}');
                                }
                                if ($payload == 'watch_key') {
                                    $temp = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `groups` WHERE `group_id` = '" . $db['group_id'] . "'"));
                                    vk_msg_send($peer_id, 'Ключ группы:<br>///' . $temp['group_token'] . '///');
                                }
                                if ($payload == 'mass') {
                                    mysqli_query($link, "UPDATE `users` SET `user_condition`='need_mass' WHERE `vk_id` = '" . $from_id . "'");
                                    vk_msg_send($peer_id, 'Введите текст рассылки.<br>Сообщение отправится всем участникам Вашей группы.<br>Также можно прикрепить и до десяти фотографий.', '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Отмена",
									"payload": "{\"button\": \"cancel\"}"
									}, 
									"color": "negative" 
									}] 
									] 
								}');
                                }
                                if ($payload == 'gen_key') {
                                    $group_token = md5($from_id . time());
                                    mysqli_query($link, "UPDATE `groups` SET `group_token`='" . $group_token . "' WHERE `group_id` = '" . $db['group_id'] . "'");
                                    vk_msg_send($peer_id, 'Новый ключ группы:<br>///' . $group_token . '///');
                                }
                                if ($payload == 'delete') {
                                    vk_msg_send($peer_id, 'Введите id задания', '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Отмена",
									"payload": "{\"button\": \"cancel\"}"
									}, 
									"color": "negative" 
									}] 
									] 
								}');
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'need_id' WHERE `vk_id` = '" . $from_id . "'");
                                }
                                if ($payload == 'exit_group') {
                                    mysqli_query($link, "UPDATE `users` SET `group_id` = '0' WHERE `vk_id` = '" . $from_id . "'");
                                    vk_msg_send($peer_id, 'Вы вышли из группы.', '{ 
									"one_time": false, 
									"buttons": [ 
									[
									{ 
									"action": { 
									"type": "text",
									"label": "Присоединиться",
									"payload": "{\"button\": \"join\"}"		
									}, 
									"color": "primary" 
									},
									{ 
									"action": { 
									"type": "text",
									"label": "Создать",
									"payload": "{\"button\": \"create\"}"
									}, 
									"color": "primary" 
									}] 
									] 
								}');
                                }
                                if ($payload == 'get_file') {
                                    vk_msg_send($peer_id, "Отправь мне ID задания.", '{ 
									"one_time": false, 
									"buttons": [ 
									[{ 
									"action": { 
									"type": "text",
									"label": "Отмена",
									"payload": "{\"button\": \"cancel\"}"
									}, 
									"color": "negative" 
									}] 
									] 
								}');
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'need_file_id' WHERE `vk_id` = '" . $from_id . "'");
                                }
                            } elseif ($db['user_condition'] == 'need_id' and $payload != 'cancel') {
                                $id = mysqli_real_escape_string($link, $msg);
                                $temp = mysqli_fetch_array(mysqli_query($link, "SELECT * FROM `homework` WHERE `db` = '" . $id . "'"));
                                if ($temp['group_id'] == $db['group_id']) {
                                    mysqli_query($link, "DELETE FROM `homework` WHERE `db` = '" . $id . "'");
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready' WHERE `vk_id` = '" . $from_id . "'");
                                    vk_msg_send($peer_id, 'Удалено', $keyboards['main']);
                                } else {
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready' WHERE `vk_id` = '" . $from_id . "'");
                                    vk_msg_send($peer_id, 'Задание относится не к вашей группе, либо было уже удалено', $keyboards['main']);
                                }
                            } elseif ($db['user_condition'] == 'need_lesson' and $payload != 'cancel') {
                                vk_msg_send($peer_id, "Введите задание. \nМожно прикрепить до 10 файлов. Они пройдут проверку, и прикрепятся только прошедшие проверку.\nРазмер каждого файла не должен превышать 19 мегабайт.\nПроверка файлов может длиться до 15 минут.");
                                $stmt = $link->prepare("UPDATE `users` SET `user_condition` = 'need_task', `lesson` = ? WHERE `vk_id` = ?");
                                $stmt->bind_param('si', $msg, $from_id);
                                $stmt->execute();
                            } elseif ($db['user_condition'] == 'need_file_id' and $payload != 'cancel') {
                                mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready' WHERE `vk_id` = '" . $from_id . "'");
                                vk_msg_send($peer_id, "Вы поставлены в очередь на получение файлов.", $keyboards['main']);
                                $task_id = mysqli_real_escape_string($link, $msg);
                                bgexec('php fork_send.php '.$task_id.' '.$from_id);
                            } elseif ($db['user_condition'] == 'need_task' and $payload != 'cancel') {
                                if (isset($updates[$c]->object->message->attachments)) {
                                    $files = json_encode($updates[$c]->object->message->attachments);
                                } else {
                                    $files = '';
                                }
                                vk_msg_send($peer_id, 'Введите дату<br>Формат: ДД.ММ.ГГГГ');
                                $stmt = $link->prepare("UPDATE `users` SET `user_condition` = 'need_date', `task` = ?, `files` = ?  WHERE `vk_id` = ?");
                                $stmt->bind_param('ssi', $msg, $files, $from_id);
                                $stmt->execute();
                            } elseif ($db['user_condition'] == 'need_date' and $payload != 'cancel') {
                                $date = mysqli_real_escape_string($link, $msg);
                                $check = explode('.', $date);
                                if (is_numeric($check[0]) and is_numeric($check[1]) and is_numeric($check[2]) and $check[1] > 0 and $check[1] < 13 and $check[2] > 2018 and $check[2] < 2038 and (($check[0] < 32 and ($check[1] == '01' or $check[1] == '03' or $check[1] == '05' or $check[1] == '07' or $check[1] == '08' or $check[1] == '12' or $check[1] == '10')) or ($check[0] < 31 and ($check[1] == '04' or $check[1] == '06' or $check[1] == '09' or $check[1] == '11' or $check[1] == '08' or $check[1] == '12')) or ($check[0] < 30 and $check[2] % 4 == 0) or ($check[0] < 29 and ($check[2] % 4 != 0)))) {
                                    $date = strtotime($date) + 64800;
                                    $llll = '';
                                    $stmt = $link->prepare("INSERT INTO `homework`(`lesson`, `task`, `to_time`, `from_time`, `group_id`, `files`) VALUES (?,?,?,?,?,?)");
                                    $stmt->bind_param('ssiiis', $db['lesson'], $db['task'], $date, time(), $db['group_id'],$llll);
                                    $stmt->execute();
                                    $task_id = $stmt->insert_id;
                                    if ($db['files'] != '') {
                                        bgexec('php add_files.php '.$task_id.' '.$db['group_id'].' '.urlencode($db['files']));
                                    }
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready', `task` = '', `lesson` = '', `files` = '' WHERE `vk_id` = '" . $from_id . "'");
                                    vk_msg_send($peer_id, 'Готово.', $keyboards['main']);
                                } else {
                                    vk_msg_send($peer_id, 'Введите дату ещё раз.<br>Дата должна быть в формате: <br> 01.01.2019');
                                }
                            } elseif ($db['user_condition'] == 'need_mass' and $payload != 'cancel') {
                                if ($db['mass'] > time()) {
                                    vk_msg_send($peer_id, 'Вы можете проводить рассылку только 1 раз в час.');
                                } else {
                                    mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready', `mass` = " . (time() + 3600) . " WHERE `vk_id` = '" . $from_id . "'");
                                    $msg = 'Рассылка от vk.com/id' . $from_id . ' : ' . $msg;
                                    $files = '';
                                    if (isset($updates[$c]->object->message->attachments)) {
                                        $files = json_encode($updates[$c]->object->message->attachments);
                                    }
                                    vk_msg_send($peer_id, "Рассылка успешна поставлена в очередь.\nСкоро её увидят все участники группы.", $keyboards['main']);
                                    bgexec('php mass_fork.php '.$db['group_id'].' '.urlencode($msg).' '.urlencode($files));
                                }
                            }
                            if ($payload == 'cancel') {
                                mysqli_query($link, "UPDATE `users` SET `user_condition` = 'ready', `task` = '', `lesson` = '' WHERE `vk_id` = '" . $from_id . "'");
                                vk_msg_send($peer_id, 'Готово.', $keyboards['main']);
                            }
                            if ( $msg == "меню" ){
                                vk_msg_send($peer_id, 'Готово.', $keyboards['main']);
                            }
                        }
                    }
                }
            }
            $c++;
        }
    }
}
?>