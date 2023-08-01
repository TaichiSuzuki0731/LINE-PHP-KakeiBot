<?php
require('function.inc.php'); //共通関数群
require(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報

session_start();

$base_url = LINE_LOGIN_AUTHORIZE_URL;
$client_id = LINE_LOGIN_ID;
$redirect_uri = LINE_LOGIN_REDIRECT_URL;

$_SESSION['_line_state'] = sha1(time());

$query = "";
$query .= "response_type=" . urlencode("code") . "&";
$query .= "client_id=" . urlencode($client_id) . "&";
$query .= "redirect_uri=" . urlencode($redirect_uri) . "&";
$query .= "state=" . urlencode($_SESSION['_line_state']) . "&";
$query .= "scope=" . urlencode("profile") . "&";

$url = $base_url . '?' . $query;

header("Location: {$url}");
exit();
