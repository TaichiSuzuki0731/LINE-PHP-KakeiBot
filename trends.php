<?php
require('line_bot.inc.php');

//セッションに有効期限を設定
set_session_expiry();

session_start();

//DBに接続
$db_link = db_connect();

if ($_SESSION['user_id'] == '') {
    header("HTTP/1.1 404 Not Found");
    mysqli_close($db_link);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ユーザが所属するグループを検索

$sql = sprintf("SELECT group_id FROM line_kakeibo WHERE id = '%s' AND group_id != '' GROUP BY group_id",
    mysqli_real_escape_string($db_link, $user_id)
);
$res = mysqli_query($db_link, $sql);

$user_groups = [];
while ($row = mysqli_fetch_assoc($res)) {
    $user_groups[] = $row['group_id'];
}

mysqli_free_result($res);

// 配列のグループIDをin検索できるように文字列に変換
$user_groups_string = implode("', '", $user_groups);

// グループ名を取得
//$sql = "SELECT group_name, group_id FROM group_name WHERE group_id IN ('" . $user_groups_string . "')";
$sql = "SELECT group_name, group_id FROM line_group_info WHERE group_id IN ('" . $user_groups_string . "')";
mysqli_real_escape_string($db_link, $sql); // 'が/にエスケープされるのでクエリ自体をインジェクション処理する
$res = mysqli_query($db_link, $sql);

$user_group_name = [];
while ($row = mysqli_fetch_assoc($res)) {
    $user_group_name[] = [
        'name' => $row['group_name'],
        'id' => $row['group_id']
    ];
}

mysqli_free_result($res);

$user_group_name[] = [
    'name' => '個人',
    'id' => 'user'
];

// ユーザが記帳した年を取得
$sql = "SELECT DATE_FORMAT(created, '%Y') AS year FROM line_kakeibo WHERE id = '" . mysqli_real_escape_string($db_link, $user_id) . "' GROUP BY year ASC ORDER BY year DESC";
$res = mysqli_query($db_link, $sql);

$user_recorded_year = [];
while ($row = mysqli_fetch_assoc($res)) {
    $user_recorded_year[] = $row['year'];
}

mysqli_free_result($res);

// グループ名も年も入っていた場合に検索
if ($_POST['group_id'] != '' && $_POST['year'] != '') {
    // セッションの有効期限を確認
    if (is_session_expiry()) {
        //最終アクセス時間を更新
        update_last_access_time();
    } else {
        echo '<h1>' . SESSION_EXPIRY/60 . '分間操作が行われなかった為ログアウトしました。<br>再度LINEからログインして下さい</h1>';
        reset_session();
        exit();
    }

    $sql = "SELECT DATE_FORMAT(created, '%Y/%m') AS yearMonth, classify_id, SUM(price) as sumPrice FROM line_kakeibo WHERE ";
    if ($_POST['group_id'] == 'user') {
        $sql .= "id = '" . mysqli_real_escape_string($db_link, $user_id) . "' AND group_id = ''";
    } else {
        $sql .= "group_id = '" . mysqli_real_escape_string($db_link, $_POST['group_id']) . "' ";
    }
    $sql .= "AND DATE_FORMAT(created, '%Y') = '" . mysqli_real_escape_string($db_link, $_POST['year']) . "' GROUP BY yearMonth, classify_id ORDER BY yearMonth ASC, classify_id ASC";
    $res = mysqli_query($db_link, $sql);

    $array_trend = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $array_trend[] = $row;
    }
    /* $array_trend
      [0]=>
      array(3) {
        ["yearMonth"]=>
        string(7) "2023/01"
        ["classify_id"]=>
        string(1) "1"
        ["sumPrice"]=>
        string(5) "42650"
      }
        [1]=>
      array(3) {
        ["yearMonth"]=>
        string(7) "2023/01"
        ["classify_id"]=>
        string(1) "2"
        ["sumPrice"]=>
        string(4) "6087"
      }
    */

    mysqli_free_result($res);

    $array_color = [
        'RGB(128, 128, 128)', //グレー
        'RGB(255, 0, 0)', //レッド
        'RGB(0, 255, 0)', //グリーン
        'RGB(0, 0, 255)', //ブルー
        'RGB(255, 255, 0)', //イエロー
        'RGB(255, 165, 0)', //オレンジ
        'RGB(255, 192, 203)', //ピンク
        'RGB(128, 0, 128)', //パープル
        'RGB(173, 216, 230)', //ライトブルー
        'RGB(144, 238, 144)', //ライトグリーン
        'RGB(165, 42, 42)', //ブラウン
        'RGB(0, 255, 255)', //シアン
        'RGB(0, 0, 128)', //ネイビーブルー
        'RGB(50, 205, 50)', //ライムグリーン
        'RGB(255, 215, 0)', //ゴールド
        'RGB(192, 192, 192)', //シルバー
    ];

    // chart.js
    // 年月毎のラベル
    $unique_yearmonth = array_unique(array_column($array_trend, 'yearMonth'));
    $yearmonth_label = "'" . implode($unique_yearmonth, "', '") . "'";
    // 支出分類
    $classify_array = classify_spending();

    $trend_data = [];
    foreach ($array_trend as $row) {
        foreach ($unique_yearmonth as $row2) {
            if ($row['yearMonth'] == $row2) {
                foreach ($classify_array as $key => $row3) {
                    if ($row['classify_id'] == $key) {
                        $trend_data[$row['yearMonth']][$row['classify_id']] = $row['sumPrice'];
                    }
                }
            }
        }
    }
    /* $trend_data
      ["2023/01"]=>
      array(10) {
        [1]=>
        string(5) "42650"
        [2]=>
        string(4) "6087"
        [4]=>
        string(4) "1985"
        [7]=>
        string(4) "1780"
        [8]=>
        string(3) "981"
      }
      ...
      ...
      ...
    */
    $limit = count($classify_array);
    $trend_data2 = [];
    foreach ($unique_yearmonth as $row) {
        foreach ($classify_array as $key => $row2) {
            $trend_data2[$key]['label'] = $row2;
            $trend_data2[$key]['data'] .= $trend_data[$row][$key] . ', ';
            $trend_data2[$key]['backgroundColor'] = $array_color[$key];
        }
    }
    /*
    [0]=>
    array(3) {
      ["label"]=>
      string(9) "未設定"
      ["data"]=>
      string(10) ", , , , , "
      ["backgroundColor"]=>
      string(18) "RGB(128, 128, 128)"
    }
    [1]=>
    array(3) {
      ["label"]=>
      string(9) "食料費"
      ["data"]=>
      string(35) "42650, 50180, 46417, 43244, 20823, "
      ["backgroundColor"]=>
      string(14) "RGB(255, 0, 0)"
    }
    */
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
<script src="js/chart.js"></script>
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.min.js"></script> -->
</head>
<body>
<div id="loading">
    <div class="loader"></div>
</div>
<div class="page-header">
    <p class="display-header"><a href="trends.php"><?php echo h($user_name) ?>さんの支出の傾向</a></p>
    <span onclick='backbrowser()' class="material-symbols-outlined">
        home
    </span>
</div>
<form name="form1" action="" method="post">
    <div class="targettrend">
        <h2>【必須】確認するグループ↓になります</h2>
        <select onchange="submit(this.form)" name="group_id" id="group_id" class="trend-select">
<?php
foreach($user_group_name as $row) {
    if ($_POST['group_id'] == $row['id']) {
        echo '<option value="' . h($row['id']) .'"selected>' . h($row['name']) . '</option>';
    } else {
        echo '<option value="' . h($row['id']) .'">' . h($row['name']) . '</option>';
    }
}
?>
        </select>
        <h2>【必須】確認する年は↓になります</h2>
        <select onchange="submit(this.form)" name="year" id="year" class="trend-select">
            <option hidden>選択してください</option>
<?php
foreach($user_recorded_year as $row) {
    if ($_POST['year'] == $row) {
        echo '<option value="' . h($row) .'"selected>' . h($row) . '</option>';
    } else {
        echo '<option value="' . h($row) .'">' . h($row) . '</option>';
    }
}
?>
        </select>
    </div>
</form>
<canvas id="myBarChart"></canvas>
<script>
    var ctx = document.getElementById("myBarChart");
    var myBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php echo h($yearmonth_label); ?>],
            datasets: [
            <?php
            foreach ($trend_data2 as $row) {
                echo "{ label: '" . h($row['label']) . "', data: [" . h($row['data']) . "], backgroundColor: '" . h($row['backgroundColor']) . "'},";
            }
            ?>
            ]
        },
        options: {
            title: {
                display: true,
                text: '年間の分類別支出の傾向'
            },
            scales: {
                xAxes: [{
                    stacked: true, //積み上げ棒グラフにする設定
                    categoryPercentage:0.4 //棒グラフの太さ
                }],
                yAxes: [{
                    stacked: true,
                }]
            },
            responsive: true
        }
    });
</script>
<script>
    function backbrowser() {
        var url = '<?php echo 'home.php?yearmonth=' . h($created)?>';
        window.location.href = url;
    }
</script>
</body>
</html>