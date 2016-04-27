<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


$users = array();
if(file_exists('user_data.json'))
	$users = json_decode(file_get_contents('user_data.json'),true);

print_r($_GET);
$users[$_GET['user']]['guardar'] = $_GET['guardar'];

file_put_contents('user_data.json',json_encode($users));
