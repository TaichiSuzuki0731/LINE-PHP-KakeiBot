<?php
require('line_bot.inc.php');

//セッションに有効期限を設定
set_session_expiry();

session_start();

//DBに接続
$db_link = db_connect();

if ($_SESSION['user_id'] != '' && !$_GET['execution']) { //home.phpからの遷移時
    if ($_GET['delete'] == '') {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>ページ遷移に失敗しました。管理者にお問い合わせしてください(E-D000)</h3><br>';
        mysqli_close($db_link);
        exit();
    }
    $message_id = $_GET['delete'];

    $sql = sprintf("SELECT * FROM line_kakeibo WHERE message_id = '%s' AND id = '%s' ",
        mysqli_real_escape_string($db_link, $message_id),
        mysqli_real_escape_string($db_link, $_SESSION['user_id'])
    );
    $res = mysqli_query($db_link, $sql);

    $kakeibo_data = mysqli_fetch_object($res);

    mysqli_free_result($res);

    $date = substr($kakeibo_data->{'created'}, 0, 10);
    $time = substr($kakeibo_data->{'created'}, 11);
    $classify_array = classify_spending();
    $_SESSION['message_id'] = $message_id;
    $created = substr($kakeibo_data->{'created'}, 0, 7);
} elseif ($_SESSION['user_id'] != '' && $_GET['execution']) { //delete実行
    // セッションの有効期限を確認
    if (is_session_expiry()) {
        //最終アクセス時間を更新
        update_last_access_time();
    } else {
        echo '<h1>' . SESSION_EXPIRY/60 . '分間操作が行われなかった為ログアウトしました。<br>再度LINEからログインして下さい</h1>';
        reset_session();
        exit();
    }

    if ($_SESSION['message_id'] == '') {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>削除に失敗しました。管理者にお問い合わせしてください(E-M001)</h3><br>';
        mysqli_close($db_link);
        exit();
    }

    //論理削除
    $sql = sprintf("UPDATE line_kakeibo SET id = '', group_id = '' WHERE message_id = '%s' AND id = '%s' Limit 1",
        mysqli_real_escape_string($db_link, $_SESSION['message_id']),
        mysqli_real_escape_string($db_link, $_SESSION['user_id']),
    );
    $res = mysqli_query($db_link, $sql);

    $result = 'true';
    if (!$res) {
        $result = 'false';
    }

    mysqli_free_result($res);

    $redirect_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/home.php?delete_result=' . $result .'&yearmonth=' . date('Y-m', strtotime(h($_GET['created'])));

    header("Location: {$redirect_url}");
} else {
    header("HTTP/1.1 404 Not Found");
    mysqli_close($db_link);
    exit();
}

mysqli_close($db_link);
?>
<!DOCTYPE html>
<html>
<head>
<title>kakeiBot_web</title>
<meta charset="utf-8"/>
<meta http-equiv="content-language" content="ja">
<link rel="stylesheet" href="css/loading.css">
<link rel="stylesheet" href="css/kakeibo.css?<?php echo time(); ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,700,0,0" />
<script src="js/loading.js"></script>
</head>
<body>
<div id="loading">
    <div class="loader"></div>
</div>
<div class="page-header">
    <p class="display-header"><a href="delete_kakeibo_tb.php?delete=<?php echo h($_GET['delete']) ?>">入力データの削除</a></p>
    <span onclick='backbrowser()' class="material-symbols-outlined">
        home
    </span>
</div>
<div class="confirmation-form">
    <form method='GET' action=''>
        <p>金額:<?php echo h($kakeibo_data->{'price'})?></p>
        <p>分類:<?php echo h($classify_array[$kakeibo_data->{'classify_id'}])?></p>
        <p>日付:<?php echo h($date)?></p>
        <p>時間:<?php echo h($time)?></p>
        <p class="decision-button">
            <button type='button' onclick='deleteclick()' class="delete-button">この内容で決定</button>
        </p>
    </form>
</div>
</body>
<script>
function backbrowser() {
    var url = '<?php echo 'home.php?yearmonth=' . h($created)?>';
    window.location.href = url;
}
function deleteclick() {
    var res = confirm("本当に削除しますか？復元は不可能です!");
    if( res == true ) {
        // OKなら移動
        var url = '<?php echo 'delete_kakeibo_tb.php?execution=true&created=' . h($created)?>';
        window.location.href = url;
    } else {
        // キャンセルならアラートボックスを表示
        alert("キャンセルしました");
    }
}
</script>
</html>