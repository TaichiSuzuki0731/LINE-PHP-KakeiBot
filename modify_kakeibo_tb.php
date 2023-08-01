<?php
require('line_bot.inc.php');

//セッションに有効期限を設定
set_session_expiry();

session_start();

//DBに接続
$db_link = db_connect();

if ($_SESSION['user_id'] != '' && !$_GET['execution']) {
    if ($_GET['modify'] == '') {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>ページ遷移に失敗しました。管理者にお問い合わせしてください(E-M000)</h3><br>';
        mysqli_close($db_link);
        exit();
    }
    $message_id = $_GET['modify'];

    $sql = sprintf("SELECT * FROM line_kakeibo WHERE message_id = '%s' AND id = '%s' ",
        mysqli_real_escape_string($db_link, $message_id),
        mysqli_real_escape_string($db_link, $_SESSION['user_id']),
    );
    $res = mysqli_query($db_link, $sql);

    $kakeibo_data = mysqli_fetch_object($res);

    mysqli_free_result($res);

    $date = substr($kakeibo_data->{'created'}, 0, 10);
    $time = substr($kakeibo_data->{'created'}, 11);
    $classify_array = classify_spending();
    $_SESSION['message_id'] = $message_id;
    $created = substr($kakeibo_data->{'created'}, 0, 7);
} elseif ($_SESSION['user_id'] != '' && $_GET['execution']) {
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
        echo '<h3>修正に失敗しました。管理者にお問い合わせしてください(E-M001)</h3><br>';
        mysqli_close($db_link);
        exit();
    }

    if (!is_regular_integer($_GET['input_price']) || !is_regular_integer($_GET['input_classify'])) {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>修正に失敗しました。管理者にお問い合わせしてください(E-M002)</h3><br>';
        mysqli_close($db_link);
        exit();
    }

    $date_pattern = '/^\d{4}-\d{2}-\d{2}$/'; //XXXX-YY-ZZ
    if (!preg_match($date_pattern, $_GET['input_date'])) {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>修正に失敗しました。管理者にお問い合わせしてください(E-M003)</h3><br>';
        mysqli_close($db_link);
        exit();    }

    $time_pattern = '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/'; //HH:MM:SS
    if (!preg_match($time_pattern, $_GET['input_time'])) {
        header("HTTP/1.1 404 Not Found");
        echo '<h3>修正に失敗しました。管理者にお問い合わせしてください(E-M004)</h3><br>';
        mysqli_close($db_link);
        exit();    }

    $insert_date_time = $_GET['input_date'] . ' ' . $_GET['input_time'];

    $sql = 'UPDATE line_kakeibo SET '; //base_sql
    $sql .= sprintf("price = '%s', classify_id = '%s', created = '%s', modifiy = now() WHERE message_id = '%s' AND id = '%s' Limit 1",
        mysqli_real_escape_string($db_link, $_GET['input_price']),
        mysqli_real_escape_string($db_link, $_GET['input_classify']),
        mysqli_real_escape_string($db_link, $insert_date_time),
        mysqli_real_escape_string($db_link, $_SESSION['message_id']),
        mysqli_real_escape_string($db_link, $_SESSION['user_id'])
    );
    $res = mysqli_query($db_link, $sql);

    $result = 'true';
    if (!$res) {
        $result = 'false';
    }

    mysqli_free_result($res);

    $redirect_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/home.php?modify_result=' . $result .'&yearmonth=' . date('Y-m', strtotime(h($_GET['input_date'])));

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
    <p class="display-header"><a href="modify_kakeibo_tb.php?modify=<?php echo h($_GET['modify']) ?>">入力データの修正</a></p>
    <span onclick='backbrowser()' class="material-symbols-outlined">
        home
    </span>
</div>
<div class="confirmation-form">
    <form method='GET' action='' onsubmit='return validateForm();'>
        <p>金額: <input type="number" name="input_price" id="input_price" min='1' value = <?php echo h($kakeibo_data->{'price'})?> class="modify-input"></p>
        <p>分類: 
            <select name="input_classify" id="classify-select" class="modify-input">
    <?php
    foreach ($classify_array as $key => $row) {
        if ($key == $kakeibo_data->{'classify_id'}) {
            echo '<option value="' . h($key) . '" selected>' . h($row) .'</option>';
        } else {
            echo '<option value="' . h($key) . '">' . h($row) . '</option>';
        }
    }
    ?>
            </select>
        </p>
        <p>日付: <input type="date" name="input_date" id="input_date" value = "<?php echo h($date)?>" class="modify-input"></p>
        <p>時間: <input type="time" name="input_time" id="input_time" step="1" value = "<?php echo h($time)?>" class="modify-input"></p>
        <p class="decision-button">
            <input type="submit" name="execution" value="この内容で決定" class="decision-input">
        </p>
    </form>
</div>
</body>
<script>
//バリデーションチェック
function validateForm() {
    var price = document.getElementById("input_price").value;
    var classify = document.getElementById("classify-select").value;
    var date = document.getElementById("input_date").value;
    var time = document.getElementById("input_time").value;

    // 金額のチェック
    if (price < 1 || !Number.isInteger(Number(price))) {
        alert("金額は1以上の整数で入力してください。");
        return false;
    }

    // 分類のチェック
    if (classify < 0 || !Number.isInteger(Number(classify))) {
        alert("分類はセレクタから選んでください");
        return false;
    }

    // 日付のチェック
    var datePattern = /^\d{4}-\d{2}-\d{2}$/;
    if (!date.match(datePattern)) {
        alert("日付はYYYY-MM-DDの形式で入力してください。");
        return false;
    }

    // 時間のチェック
    var timePattern = /^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/;
    if (!time.match(timePattern)) {
        alert("時間はHH:MM:SSの形式で入力してください。");
        return false;
    }

    // フォームを送信
    return true;
}
function backbrowser() {
    var url = '<?php echo 'home.php?yearmonth=' . h($created)?>';
    window.location.href = url;
}
</script>
</html>