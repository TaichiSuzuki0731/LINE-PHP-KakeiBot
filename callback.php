<?php
require('function.inc.php'); //共通関数群
require(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報

session_start();

$code = $_GET['code'];
$state = $_GET['state'];

$session_state = $_SESSION['_line_state'];
unset($_SESSION['_line_state']);
if ($session_state !== $state) {
    exit();
}

// 各種値の設定
$url = LINE_LOGIN_V2_TOKEN;
$client_id = LINE_LOGIN_ID;
$client_secret = LINE_LOGIN_SECRET;
$redirect_uri = LINE_LOGIN_REDIRECT_URL;

// POSTパラメータの作成
$query = "";
$query .= "grant_type=" . urlencode("authorization_code") . "&";
$query .= "code=" . urlencode($code) . "&";
$query .= "redirect_uri=" . urlencode($redirect_uri) . "&";
$query .= "client_id=" . urlencode($client_id) . "&";
$query .= "client_secret=" . urlencode($client_secret) . "&";

// HTTPヘッダーの設定
$header = array(
    "Content-Type: application/x-www-form-urlencoded",
    "Content-Length: " . strlen($query),
);

// コンテキスト（各種情報）の設定
$context = array(
    "http" => array(
        "method"        => "POST",
        "header"        => implode("\r\n", $header),
        "content"       => $query,
        "ignore_errors" => true,
    ),
);

// id token を取得する
$res_json = file_get_contents($url, false, stream_context_create($context));

// 取得したjsonデータをオブジェクト化
$res = json_decode($res_json);

// エラーを取得
if (isset($res->error)) {
    $error_code = 'Error: ' . $res->error . 'Error_Description: ' . $res->error_description;
    exit();
}

$access_token = $res->{"access_token"};

$redirect_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/home.php';
$_SESSION['access_token'] = $access_token;

header("Location: {$redirect_url}");
exit();