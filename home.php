<?php
require('line_bot.inc.php');

// JSだと$message_idの大きい値を四捨五入してしまい正しく遷移しないのでonClick用のURLを作る
function make_modify_kakeibo_tb_url($message_id) {
    if ($message_id != '') {
        return "location.href='modify_kakeibo_tb.php?modify={$message_id}'";
    }

    return '';
}

function make_delete_kakeibo_tb_url($message_id) {
    if ($message_id != '') {
        return "location.href='delete_kakeibo_tb.php?delete={$message_id}'";
    }

    return '';
}

//セッションに有効期限を設定
set_session_expiry();

//セッションスタート
session_start();

//DBに接続
$db_link = db_connect();

//初回ログイン時
if ($_SESSION['access_token'] != '') {
    $url = LINE_LOGIN_V2_PROFILE;
    $access_token = $_SESSION['access_token'];
    unset($_SESSION['access_token']);

    //最終アクセス時間を更新
    update_last_access_time();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token
    ));

    $res['result'] = curl_exec($ch);
    $res['getinfo'] = curl_getinfo($ch);
    curl_close($ch);

    //MessageAPIのレスポンスを記録
    @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

    //レスポンスからbodyを取り出す
    $response = substr($res['result'], $res['getinfo']['header_size']);

    $userdata = json_decode($response);

    $user_id = $userdata->{'userId'};
    $user_name = $userdata->{'displayName'};

    //ページ回遊に備えてサーバにパラメータ保持
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $user_name;

} elseif ($_SESSION['user_id'] != '') { //回遊時
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'];

} else {
    header("HTTP/1.1 404 Not Found");
    mysqli_close($db_link);
    exit();
}
//セッションの有効期限を確認
if (is_session_expiry()) {
    update_last_access_time();
} else {
    echo '<h1>' . SESSION_EXPIRY/60 . '分間操作が行われなかった為ログアウトしました。<br>再度LINEからログインして下さい</h1>';
    reset_session(); //セッション削除
    exit();
}

if ($user_id == '') {
    header("HTTP/1.1 404 Not Found");
    mysqli_close($db_link);
    exit();
}

$price_search_flag = false;
if ($_GET['price'] != '') {
    if (!is_regular_integer($_GET['price'])) {
        header("HTTP/1.1 404 Not Found");
        mysqli_close($db_link);
        exit();
    }
    $price_search_flag = true;
    $price_sql = "AND price = '" . mysqli_real_escape_string($db_link, $_GET['price']) . "' ";
}

//Kakeiboテーブルからユーザが記帳したレコードを取得
$sql = sprintf("SELECT * FROM line_kakeibo WHERE id = '%s' ",
    mysqli_real_escape_string($db_link, $user_id)
);

if ($price_search_flag) {
    $sql .= $price_sql;
} else {
    if ($_GET['yearmonth'] != '') {
        $sql .= "AND created LIKE '%" . mysqli_real_escape_string($db_link, $_GET['yearmonth']) ."%' ";
    } else {
        $sql .= "AND DATE_FORMAT(created, '%Y%m') > DATE_FORMAT((NOW() - INTERVAL 1 MONTH), '%Y%m') ";
    }
}

$sql .= 'ORDER BY created DESC';
$res = mysqli_query($db_link, $sql);

$user_kakeibo_array = [];
while ($row = mysqli_fetch_assoc($res)) {
    $user_kakeibo_array[] = $row;
}

mysqli_free_result($res);

// ユーザが記帳したグループを取得
$sql = sprintf("SELECT group_id FROM line_kakeibo WHERE id = '%s' GROUP BY group_id",
    mysqli_real_escape_string($db_link, $user_id)
);
$res = mysqli_query($db_link, $sql);

$group_id_array = [];
while ($row = mysqli_fetch_assoc($res)) {
    $group_id_array[] = $row['group_id'];
}

mysqli_free_result($res);

$in_pattern = '';
foreach ($group_id_array as $row) {
    if ($row != '') {
        $in_pattern .= '"' . $row . '", ';
    }
}

$in_pattern = rtrim($in_pattern, ', ');

// ユーザが過去に記帳した家計簿テーブルのグループidで再度家計簿テーブルのデータを取得
// 過去に１回でもグループ内で記帳が有れば自分が記帳していないグループデータも確認できる
$sql = sprintf("SELECT * FROM line_kakeibo WHERE id != '%s' AND group_id IN (" . $in_pattern . ") ",
    mysqli_real_escape_string($db_link, $user_id)
);

if ($price_search_flag) {
    $sql .= $price_sql;
} else {
    if ($_GET['yearmonth'] != '') {
        $sql .= "AND created LIKE '%" . mysqli_real_escape_string($db_link, $_GET['yearmonth']) ."%' ";
    } else {
        $sql .= "AND DATE_FORMAT(created, '%Y%m') > DATE_FORMAT((NOW() - INTERVAL 1 MONTH), '%Y%m') ";
    }
}

$sql .= 'ORDER BY created DESC';
$res = mysqli_query($db_link, $sql);

$another_kakeibo_array = [];
while ($row = mysqli_fetch_assoc($res)) {
    $another_kakeibo_array[] = $row;
}

mysqli_free_result($res);

// 月別表示のためにYYYY-MMの形で記帳した日付を取得
$sql = "SELECT DATE_FORMAT(created, '%Y-%m') as yearmonth FROM line_kakeibo";
$sql .= sprintf(" WHERE id = '%s'",
    mysqli_real_escape_string($db_link, $user_id)
);
if ($in_pattern != '') {
    $sql .= " OR group_id IN (" . $in_pattern . ")";
}
$sql .= " GROUP BY yearmonth ORDER BY created DESC";
$res = mysqli_query($db_link, $sql);

$yearmonth = [];
while ($row = mysqli_fetch_assoc($res)) {
    $yearmonth[] = $row['yearmonth'];
}

mysqli_free_result($res);

//グループidからグループ名を取得
$group_name_array = [];
if ($in_pattern != '') {
    //$sql = 'SELECT group_id, group_name FROM group_name WHERE group_id IN (' . $in_pattern . ')';
    $sql = 'SELECT group_id, group_name FROM line_group_info WHERE group_id IN (' . $in_pattern . ')';
    $res = mysqli_query($db_link, $sql);

    while ($row = mysqli_fetch_assoc($res)) {
        $group_name_array[] = $row;
    }
    mysqli_free_result($res);
}

mysqli_close($db_link);

$classify_array = classify_spending();

// 配列を結合
$group_kakeibo_array = array_merge($user_kakeibo_array, $another_kakeibo_array);
// 記帳時間で降順に並べ替える.array_columnでcreatedの要素を取り出してarray_mapで全要素に対してarray_multisortで降順に並べ替える
array_multisort(array_map("strtotime", array_column($group_kakeibo_array, "created" )), SORT_DESC, $group_kakeibo_array);

// 個人家計簿を合計
$user_sum_price = 0;
foreach ($user_kakeibo_array as $row) {
    if ($row['group_id'] == '') {
        $user_sum_price += $row['price'];
    }
}
// グループ家計簿を合計
$group_sum_price = [];
$group_sum_groupId = [];
$total_group_sum_price = 0;
foreach ($group_kakeibo_array as $row) {
    if ($row['group_id'] != '') {
        if (in_array($row['group_id'], $group_sum_groupId, true)) { // すでに配列に入っている家計簿
            $temp = $group_sum_price[$row['group_id']] + $row['price'];
            $group_sum_price[$row['group_id']] = $temp;
            $total_group_sum_price = $temp;
        } else { // 初めて配列に入るグループ家計簿
            array_push($group_sum_groupId, $row['group_id']);
            $group_sum_price += [
                $row['group_id'] => $row['price']
            ];
            $total_group_sum_price += $row['price'];
        }
    }
}
$total_price = $user_sum_price + $total_group_sum_price;

if ($total_price == 0 && !$price_search_flag) {
    array_unshift($yearmonth, '-------------');
}

if ($price_search_flag) {
    array_unshift($yearmonth, '全期間');
}

// スマホでの表示を促す
$agent = $_SERVER['HTTP_USER_AGENT'];
$is_mobile = false;

// ユーザーエージェントからモバイル端末かどうかを判定
if (preg_match('/(iphone|ipod|ipad|android)/i', $agent)) {
    $is_mobile = true;
}

if (!$is_mobile) {
    echo '<p class="no-mobile">スマホでの閲覧に最適化されています</p>';
}

// 修正・削除の結果
$result_message = '';
if ($_GET['delete_result'] == 'true') {
    $result_message = '削除に成功しました';
}
if ($_GET['delete_result'] == 'false') {
    $result_message = '削除に失敗しました。管理者にお問い合わせしてください';
}
if ($_GET['modify_result'] == 'true') {
    $result_message = '修正に成功しました';
}
if ($_GET['modify_result'] == 'false') {
    $result_message = '修正に失敗しました。管理者にお問い合わせしてください';
}

if ($result_message != '') {
    echo "<script>alert('" . h($result_message) . "');</script>";
}
?>
<!DOCTYPE html>
<html>
<head>
<title>kakeiBot_web</title>
<meta charset="utf-8"/>
<meta http-equiv="content-language" content="ja">
<meta property="og:title" content="kakeiBot_web">
<meta property="og:description" content="kakeiBot_webのHomePage">
<meta property="og:url" content="home.php">
<meta property="og:type" content="website">
<link rel="stylesheet" href="css/loading.css">
<link rel="stylesheet" href="css/kakeibo.css?<?php echo time(); ?>">
<link rel="stylesheet" href="css/scroll_top.css?<?php echo time(); ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,700,0,0" />

<script src="js/loading.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/scroll_top.js"></script>
</head>
<body>
<div id="loading">
    <div class="loader"></div>
</div>
<div id="page_top">
    <span onclick="pageTop()" class="material-symbols-outlined page_top_btn">
    arrow_upward
    </span>
</div>
<div class="page-header">
    <p class="display-header"><a href="home.php"><?php echo h(trim($user_name));?>さん専用KakeiBotページ</a></p>
    <img src="image/rabi001.JPG" alt="rabi" style="display: none;">
    <span onclick="toggleVisibility()" class="material-symbols-outlined">
        help
    </span>
    <span onclick="trend()" class="material-symbols-outlined">
        trending_up
    </span>
</div>
<div id="color_recode_font" onclick="toggleVisibility()" class="color-recode-font" style="display: none;">
    <div class="user-row">個人の記帳データ</div>
    <div class="group-row">所属しているグループの自分の記帳データ</div>
    <div class="another-row">所属しているグループの別の人の記帳データ(修正/削除不可)</div>
</div>
<div class="display-yearmonth">
    <form name="form1" id="form1" action="home.php" method="get" class="yearmonth-box">
        <select onchange="submitForm()" name="yearmonth" id="yearmonth" class="home-select">
            <?php
            foreach ($yearmonth as $row) {
                if ($_GET['yearmonth'] == $row) {
                    echo '<option value="' . h($row) . '" selected>' . h($row) . '</option>';
                } else {
                    echo '<option value="' . h($row) . '">' . h($row) . '</option>';
                }
            }
            ?>
        </select>
        <select onchange="targetShowRecord(this.value)" name="selectBox" id="selectBox" class="home-select">
            <option value="all">全表示</option>
            <?php
            foreach($classify_array as $key => $row) {
                echo '<option value="' . h($key) .'">' . h($row) . '</option>';
            }
            ?>
        </select>
    </form>
    <span onclick="showSearchPrice()" class="material-symbols-outlined">
        manage_search
    </span>
    <span onclick="showSumPrice()" class="material-symbols-outlined">
        paid
    </span>
</div>
<hr>
<div id="price-search" style="display: none;" class="price-search">
    <p class="p-price-number">全期間から検索を行います</p>
    <p class="p-price-number">金額で検索:
        <input type="number" name="price" id="price" class="price-number" placeholder="1000">
        <button type="submit" class="b-price-number">
            <span onclick="searchPrice()" class="material-symbols-outlined p-number-icon">
                search
            </span>
        </button>
    </p>
<hr>
</div>
<div id="sum_price_area" style="display: none;" onclick="showSumPrice()">
<a class="sum-price">ch毎の合計金額</a><br>
<?php
if ($user_sum_price > 0) {
    echo '<a class="sum-price">' . '個人: ' . h($user_sum_price) . '円</a><br>';
}
if (!empty($group_sum_price)) {
    foreach ($group_sum_price as $groupId => $price) {
        foreach ($group_name_array as $row) {
            if ($row['group_id'] == $groupId) {
                echo '<a class="sum-price">' . h($row['group_name']) . ': ' . h($price) . '円</a><br>';
            }
        }
    }
}
?>
<hr>
</div>
<table class="kakeibo-table">
<tr class="header">
    <th>ch</th>
    <th>値段</th>
    <th>分類</th>
    <th>時間</th>
    <th>修正</th>
    <th>削除</th>
</tr>
<?php
if ($total_price == 0) {
    echo '<p class="no-record">記録はありませんでしたニャ🐱</p>';
}
// 個人家計簿
foreach ($user_kakeibo_array as $row1) {
    if ($row1['group_id'] == '') {
        echo '<tr id="classifyId-' . h($row1['classify_id']) . '"class="user-row" style="display: table-row;">';
        echo '<td>個人</td>';
        echo '<td>' . h(number_format($row1['price'])) . '</td>';
        echo '<td>' . h($classify_array[$row1['classify_id']]) . '</td>';
        echo '<td>' . h($row1['created']) . '</td>';
        echo '<td><button type="button" name="modify" onclick="' . make_modify_kakeibo_tb_url(h($row1['message_id'])) . '" class="user-row-b"><span class="material-symbols-outlined">auto_fix</span></button></td>';
        echo '<td><button type="button" name="delete" onclick="' . make_delete_kakeibo_tb_url(h($row1['message_id'])) . '" class="user-row-b"><span class="material-symbols-outlined">delete</span></button></td>';
        echo '</tr>';
    }
}
// グループ家計簿
foreach ($group_kakeibo_array as $row1) {
    if ($row1['group_id'] != '') {
        if ($row1['id'] == $user_id) { // 自分の記帳分
            echo '<tr id="classifyId-' . h($row1['classify_id']) . '"class="group-row" style="display: table-row;">';
            foreach ($group_name_array as $row) {
                if ($row1['group_id'] == $row['group_id']) {
                    echo '<td>' . h($row['group_name']) . '</td>';
                }
            }
            echo '<td>' . h(number_format($row1['price'])) . '</td>';
            echo '<td>' . h($classify_array[$row1['classify_id']]) . '</td>';
            echo '<td>' . h($row1['created']) . '</td>';
            echo '<td><button type="button" name="modify" onclick="' . make_modify_kakeibo_tb_url(h($row1['message_id'])) . '" class="group-row-b"><span class="material-symbols-outlined">auto_fix</span></button></td>';
            echo '<td><button type="button" name="delete" onclick="' . make_delete_kakeibo_tb_url(h($row1['message_id'])) . '" class="group-row-b"><span class="material-symbols-outlined">delete</span></button></td>';
            echo '</tr>';
        } else { // 他人の記帳分
            echo '<tr id="classifyId-' . h($row1['classify_id']) . '"class="another-row" style="display: table-row;">';
            foreach ($group_name_array as $row) {
                if ($row1['group_id'] == $row['group_id']) {
                    echo '<td>' . h($row['group_name']) . '</td>';
                }
            }
            echo '<td>' . h(number_format($row1['price'])) . '</td>';
            echo '<td>' . h($classify_array[$row1['classify_id']]) . '</td>';
            echo '<td>' . h($row1['created']) . '</td>';
            echo '<td><button type="button" name="modify" class="another-row" disabled><span class="material-symbols-outlined">auto_fix</span></button></td>';
            echo '<td><button type="button" name="delete" class="another-row" disabled><span class="material-symbols-outlined">delete</span></button></td>';
            echo '</tr>';
        }
    }
}

?>
</table>
</body>
<script>
    //インフォメーション表示/非表示
    function toggleVisibility() {
        var element = document.getElementById('color_recode_font');
        if (element.style.display === 'none') {
            element.style.display = 'block';
        } else {
            element.style.display = 'none';
        }
    }
    //金額検索表示/非表示
    function showSearchPrice() {
        var element = document.getElementById('price-search');
        if (element.style.display === 'none') {
            element.style.display = 'block';
        } else {
            element.style.display = 'none';
        }
    }
    //金額検索(バリデーション込)
    function searchPrice() {
        var priceInput = document.getElementById('price');
        var priceValue = priceInput.value.trim();

        // 正規表現を使用して入力値のバリデーションを行う
        var validInput = /^[0-9]+$/.test(priceValue);

        if (priceValue === '' || !validInput) {
            var errorMessage = document.getElementById('error-message');
            alert('半角数字以外は入れられません');
        } else {
            var price = parseInt(priceValue);
            var url = 'home.php?price=' + price;
            window.location.href = url;
        }
    }
    function submitForm() {
        document.getElementById('form1').submit();
    }
    //合計金額表示/非表示
    function showSumPrice() {
        var element = document.getElementById('sum_price_area');
        if (element.style.display === 'none') {
            element.style.display = 'block';
        } else {
            element.style.display = 'none';
        }
    }
    function trend() {
        window.location.href = 'trends.php';
    }
    //分類毎に表示
    function targetShowRecord(target) {
    // 全ての要素を取得
    var rows = document.querySelectorAll('[id^="classifyId-"]');

    // 各要素をループして一致しないものを非表示にする
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var id = row.getAttribute('id');

        // id属性がclassifyId-で始まり、指定されたターゲットと一致しない場合は非表示にする
        if (id.startsWith('classifyId-')) {
            if (target === 'all' || id === 'classifyId-' + target) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        }
    }
}
    function pageTop() {
        window.location.href = '#page_top';
    }
</script>
</html>