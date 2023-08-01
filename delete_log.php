<?php
require('send_admin_line.php'); //管理者にLineメッセージを送る

// 4世代まで保存
$date = date("Y-m-d", strtotime("-30 day"));

//DBに接続
$db_link = db_connect();

// where条件
$where = sprintf("WHERE created < '%s'",
    mysqli_real_escape_string($db_link, $date)
);

$sql = 'SELECT count(*) AS cnt FROM line_log ' . $where;
$res = mysqli_query($db_link, $sql);

$select_cnt = mysqli_fetch_assoc($res);

mysqli_free_result($res);

$message = '削除レコード数: ' . $select_cnt['cnt'] . "\n";

if ($select_cnt['cnt'] > 0) {
    $sql = 'DELETE FROM line_log ' . $where;
    $res = mysqli_query($db_link, $sql);
    $message .= "\nSuccess Delete Log";
    if (!$res) {
        $message .= "\nFailed Delete Log";
    }
    mysqli_free_result($res);
} else {
    $message .= "\nNo Delete Log";
}

mysqli_close($db_link);
post_messages($message);