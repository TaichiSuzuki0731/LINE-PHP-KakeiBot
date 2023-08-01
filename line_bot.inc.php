<?php
require('function.inc.php'); //共通関数群
require(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報

define("KAKEIBO_WEBPAGE_URL", 'https://st0731-dev-srv.moo.jp/redirect.php');
define("SESSION_EXPIRY", 300); //5分

$file = get_included_files();
if (array_shift($file) === __FILE__) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

/**
 * ユーザの情報を登録
 * @param char $db_link DB接続情報
 * @param char $user_id LineのユーザID
 * @param cher $user_name Lineのユーザ名
 */
function insert_user($db_link, $user_id, $user_name) {
    $sql = sprintf("INSERT INTO line_user (id, name) VALUES ('%s', '%s')",
        mysqli_real_escape_string($db_link, $user_id),
        mysqli_real_escape_string($db_link, $user_name)
    );

    mysqli_query($db_link, $sql);
}

/**
 * グループの情報を登録
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 * @param char $group_name Lineのグループ名
 * @param number $member Lineのグループ人数
 */
function insert_group_info($db_link, $group_id, $group_name, $member) {
    $sql = sprintf("INSERT INTO group_info (group_id, group_name, member) VALUES ('%s', '%s', '%s')",
        mysqli_real_escape_string($db_link, $group_id),
        mysqli_real_escape_string($db_link, $group_name),
        mysqli_real_escape_string($db_link, $member)
    );

    mysqli_query($db_link, $sql);
}

/**
 * 出費をテーブルに登録
 * @param char $db_link DB接続情報
 * @param char $message_id 使い捨てのメッセージID
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @param cher $message_text メッセージ内容
 * @param cher $ch_type チャンネルタイプ
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function insert_kakeibo($db_link, $message_id, $user_id, $group_id, $message_text, $ch_type) {
    $sql = sprintf("INSERT INTO line_kakeibo (message_id, id, group_id, price, ch_type) VALUES ('%s', '%s', '%s', '%s', '%s')",
        mysqli_real_escape_string($db_link, $message_id),
        mysqli_real_escape_string($db_link, $user_id),
        mysqli_real_escape_string($db_link, $group_id),
        mysqli_real_escape_string($db_link, $message_text),
        mysqli_real_escape_string($db_link, $ch_type)
    );

    return mysqli_query($db_link, $sql);
}

/**
 * logをテーブルに登録
 * @param char $str 保存しておきたいテキスト
 */
function insert_log($str) {
    $db_link = db_connect();
    $sql = sprintf("INSERT INTO line_log (message) VALUES ('%s')",
        mysqli_real_escape_string($db_link, $str)
    );

    mysqli_query($db_link, $sql);
}

/**
 * グループのメンバー数に変化があった場合にテーブルを更新
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 * @param number $member Lineのグループ人数
 */
function update_group_member($db_link, $group_id, $member) {
    $sql = sprintf("UPDATE line_group_info SET member = '%s' WHERE group_id = '%s'",
        mysqli_real_escape_string($db_link, $member),
        mysqli_real_escape_string($db_link, $group_id)
    );

    mysqli_query($db_link, $sql);
}

/**
 * kakeiboテーブルの日付の一番新しいデータのclassify_idを更新
 * @param char $db_link DB接続情報
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @param cher $classify_id 出費の分類
 * @param cher $ch_type チャンネルタイプ
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function update_kakeibo_classify($db_link, $user_id, $group_id, $classify_id, $ch_type) {
    if ($ch_type == 'user') {
        $sql = sprintf("UPDATE line_kakeibo SET classify_id = '%s' WHERE id = '%s' AND group_id = '' ORDER BY created DESC Limit 1",
            mysqli_real_escape_string($db_link, $classify_id),
            mysqli_real_escape_string($db_link, $user_id)
        );
    } else {
        $sql = sprintf("UPDATE line_kakeibo SET classify_id = '%s' WHERE id = '%s' AND group_id = '%s' ORDER BY created DESC Limit 1",
            mysqli_real_escape_string($db_link, $classify_id),
            mysqli_real_escape_string($db_link, $user_id),
            mysqli_real_escape_string($db_link, $group_id)
        );
    }

    return mysqli_query($db_link, $sql);
}

/**
 * ユーザの名前をテーブルから削除(物理削除)
 * @param char $db_link DB接続情報
 * @param char $user_id LineのユーザID
 */
function del_user_info($db_link, $user_id) {
    $sql = sprintf("DELETE FROM line_user WHERE id = '%s'",
        mysqli_real_escape_string($db_link, $user_id)
    );

    mysqli_query($db_link, $sql);
}

/**
 * Kakeiboテーブルのデータを全削除(物理削除)
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 */
function del_kakeibo_all_data($db_link, $ch_type, $user_id, $group_id) {
    $sql = 'DELETE FROM line_kakeibo WHERE ';
    if ($ch_type == 'user') {
        $sql .= sprintf("id = '%s' and group_id = ''",
            mysqli_real_escape_string($db_link, $user_id)
        );
    } else {
        $sql .= sprintf("group_id = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );
    }

    mysqli_query($db_link, $sql);
}

/**
 * グループから退出した際に登録してあったメンバー数を削除(物理削除)
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 */
function delete_group_member($db_link, $group_id) {
    $sql = sprintf("DELETE FROM group_info WHERE group_id = '%s'",
        mysqli_real_escape_string($db_link, $group_id)
    );

    mysqli_query($db_link, $sql);
}

/**
 * kakeiboテーブルから毎日ごとの集計を取得
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function get_price_this_month($db_link, $ch_type, $user_id, $group_id) {
    $sql = "SELECT DATE_FORMAT(created, '%Y/%m/%d') AS date, sum(price) AS sam_price FROM line_kakeibo where ";
    if ($ch_type == 'user') {
        $user_id = mysqli_real_escape_string($db_link, $user_id);
        $sql .= "id = '" . $user_id . "' and group_id = ''";
    } else {
        $group_id = mysqli_real_escape_string($db_link, $group_id);
        $sql .= "group_id = '" . $group_id . "'";
    }
    $sql .= " and DATE_FORMAT(created, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m') GROUP BY DATE_FORMAT(created, '%Y%m%d')";

    return mysqli_query($db_link, $sql);
}

/**
 * kakeiboテーブルから過去12ヶ月間を遡って分類ごとの集計を取得
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function get_classify_price_one_year($db_link, $ch_type, $user_id, $group_id) {
    $sql = "SELECT classify_id, sum(price) AS sam_price FROM line_kakeibo WHERE ";
    if ($ch_type == 'user') {
        $sql .= sprintf("id = '%s' and ch_type = 'user'",
            mysqli_real_escape_string($db_link, $user_id)
        );
    } else {
        $sql .= sprintf("group_id = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );
    }
    $sql .= " AND DATE_FORMAT(created, '%Y/%m') > DATE_FORMAT((NOW() - INTERVAL 12 MONTH), '%Y/%m') GROUP BY classify_id ORDER BY classify_id";

    return mysqli_query($db_link, $sql);
}

/**
 * kakeiboテーブルから特定月の分類ごとの集計を取得
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @param number $month ターゲット月
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function get_classify_price_specific_month($db_link, $ch_type, $user_id, $group_id, $month) {
    $sql = "SELECT classify_id, sum(price) AS sam_price FROM line_kakeibo WHERE ";
    if ($ch_type == 'user') {
        $sql .= sprintf("id = '%s' and ch_type = 'user'",
            mysqli_real_escape_string($db_link, $user_id)
        );
    } else {
        $sql .= sprintf("group_id = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );
    }
    $sql .= " AND DATE_FORMAT(created, '%Y/%m') = DATE_FORMAT((NOW() - INTERVAL " . $month . " MONTH), '%Y/%m') GROUP BY classify_id ORDER BY classify_id";

    return mysqli_query($db_link, $sql);
}

/**
 * kakeiboテーブルから過去12ヶ月間を遡って毎月ごと集計
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @return array 成功時はmysqli_resultオブジェクト
 * @return bool  失敗時はFalse
 */
function get_price_one_year($db_link, $ch_type, $user_id, $group_id) {
    $sql = "SELECT DATE_FORMAT(created, '%Y/%m') AS monthly, sum(price) AS sam_price FROM line_kakeibo where ";
    if ($ch_type == 'user') {
        $sql .= sprintf("id = '%s' AND group_id = '' ",
            mysqli_real_escape_string($db_link, $user_id)
        );
    } else {
        $sql .= sprintf("group_id = '%s' ",
            mysqli_real_escape_string($db_link, $group_id)
        );
    }
    $sql .= "AND DATE_FORMAT(created, '%Y%m') > DATE_FORMAT((NOW() - INTERVAL 12 MONTH), '%Y%m') GROUP BY DATE_FORMAT(created, '%Y%m')";

    return mysqli_query($db_link, $sql);
}

/**
 * 支出合計を計算
 * @param char $db_link DB接続情報
 * @param cher $ch_type チャンネルタイプ
 * @param char $group_id LineのグループID
 * @param char $user_id LineのユーザID
 * @return number $sum_price 合算された値
 */
function sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id) {
    $sum_price = 0;
    $sql = 'SELECT price FROM line_kakeibo WHERE ';
    //グループ会計
    if ($ch_type == 'group' || $ch_type == 'room') {
        $sql .= sprintf("group_id = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );
    } else { //個人会計
        $sql .= sprintf("id = '%s' and ch_type = 'user'",
            mysqli_real_escape_string($db_link, $user_id)
        );
    }
    $sql .= " and DATE_FORMAT(created, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m')";
    $res = mysqli_query($db_link, $sql);

    if ($res != false) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sum_price = $sum_price + $row['price'];
        }
    }
    mysqli_free_result($res);

    return $sum_price;
}

/**
 * グループの場合は人数を取得
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 * @return number グループ人数
 */
function count_db_group_member($db_link, $group_id) {
    $sql = sprintf("SELECT member FROM line_group_info WHERE group_id = '%s'",
        mysqli_real_escape_string($db_link, $group_id)
    );
    $res = mysqli_query($db_link, $sql);

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    return $row['member'];
}

/**
 * ユーザネーム取得
 * @param char $db_link DB接続情報
 * @param char $user_id LineのユーザID
 * @return char ユーザ名
 */
function get_user_name($db_link, $user_id) {
    $sql = sprintf("SELECT name FROM line_user WHERE id = '%s'",
        mysqli_real_escape_string($db_link, $user_id)
    );
    $res = mysqli_query($db_link, $sql);

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    return $row['name'];
}

/**
 * kakeiboテーブルからgroupIDでgroupユーザを取得
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 * @return array グループに所属するユーザ
 */
function get_user_id_from_group($db_link, $group_id) {
    $return = [];
    $sql = sprintf("SELECT id FROM line_kakeibo WHERE group_id = '%s' GROUP BY id",
        mysqli_real_escape_string($db_link, $group_id)
    );
    if ($res = mysqli_query($db_link, $sql)) {
        while ($row = mysqli_fetch_assoc($res)) {
            array_push($return, $row['id']);
        }
    }
    mysqli_free_result($res);

    return $return;
}

/**
 * kakeiboテーブルからidでメッセージ送信者のグループ内の特定月分の家計簿データの合計を取得(光熱費は省く)
 * @param char $db_link DB接続情報
 * @param char $user_id LineのユーザID
 * @param char $group_id LineのグループID
 * @param int $month 取得したい月
 * @return int $sum_price 合算された値
 */
function get_sum_user_price_from_group($db_link, $user_id, $group_id, $month) {
    $db_link = db_connect();
    $sql = sprintf("SELECT SUM(price) AS sum_price FROM line_kakeibo WHERE id = '%s' AND group_id = '%s'",
        mysqli_real_escape_string($db_link, $user_id),
        mysqli_real_escape_string($db_link, $group_id)
    );
    $sql .= " AND classify_id != 10 AND DATE_FORMAT(created, '%Y/%m') = DATE_FORMAT((NOW() - INTERVAL " . $month . " MONTH), '%Y/%m')";
    $res = mysqli_query($db_link, $sql);

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    return $row['sum_price'];
}

/**
 * kakeiboテーブルからidでメッセージ送信者のグループ内の特定月分の家計簿データの合計を取得(光熱費は省く)
 * @param char $db_link DB接続情報
 * @param char $group_id LineのグループID
 * @param number $month 取得したい月
 * @return number $row['sum_price'] 合算された値
 */
// グループ内の光熱費を取得
function get_sum_utility_costs_from_group($db_link, $group_id, $month) {
    $sql = sprintf("SELECT SUM(price) AS sum_price FROM line_kakeibo WHERE group_id = '%s'",
        mysqli_real_escape_string($db_link, $group_id)
    );
    $sql .= " AND classify_id = 10 AND DATE_FORMAT(created, '%Y/%m') = DATE_FORMAT((NOW() - INTERVAL " . $month . " MONTH), '%Y/%m')";
    $res = mysqli_query($db_link, $sql);

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    return $row['sum_price'];
}

/**
 * curl実行
 * @param char $ch curl実行情報
 * @return array 実行結果
 */
function exec_curl ($ch) {
    $res = [];

    $res['result'] = curl_exec($ch);
    $res['getinfo'] = curl_getinfo($ch);
    curl_close($ch);

    return $res;
}

/**
 * Lineリプライメッセージ
 * @param char $replyToken リプライ用トークン
 * @param char $message_type メッセージタイプ
 * @param char $return_message_text 送信するメッセージ
 */
function sending_messages($replyToken, $message_type, $return_message_text){
    //レスポンスフォーマット
    $response_format_text = [
        "type" => $message_type,
        "text" => $return_message_text
    ];

    //ポストデータ
    $post_data["replyToken"] = $replyToken;
    $post_data["messages"] = [
        $response_format_text
    ];

    //curl実行
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, LINE_REPLY_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charser=UTF-8',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ));

    $res = exec_curl($ch);

    @receipt_curl_response($res['result'], $res['getinfo'], 'POST');
    exit();
}

/**
 * 支出分類Flexメッセージ送信
 * @param json $send_json Flexメッセージ用JSONデータ
 * @param char $replyToken リプライ用トークン
 */
function send_flex_message($send_json, $replyToken){
    $send_array = json_decode($send_json);

    //ポストデータ
    $post_data["replyToken"] = $replyToken;
    $post_data["messages"] = [
        $send_array
    ];

    //curl実行
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, LINE_REPLY_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charser=UTF-8',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ));
    $res = exec_curl($ch);

    @receipt_curl_response($res['result'], $res['getinfo'], 'POST');
    exit();
}

/**
 * Lineグループ名を取得
 * @param char $group_id グループID
 * @return array グループ名
 */
function get_group_name($group_id) {
    $url = 'https://api.line.me/v2/bot/group/' . $group_id . '/summary';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN));

    $res = exec_curl($ch);

    //MessageAPIのレスポンスを記録
    @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

    //レスポンスからbodyを取り出す
    $response = substr($res['result'], $res['getinfo']['header_size']);

    return json_decode($response);
}

/**
 * Lineユーザ情報を取得
 * @param char $user_id ユーザID
 * @return array ユーザ名
 */
function get_line_user_profile($user_id) {
    $url = 'https://api.line.me/v2/bot/profile/' . $user_id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN));

    $res = exec_curl($ch);

    //MessageAPIのレスポンスを記録
    @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

    //レスポンスからbodyを取り出す
    $response = substr($res['result'], $res['getinfo']['header_size']);

    return json_decode($response);
}

/**
 * Lineグループメンバーをカウント
 * @param char $ch_type チャンネルタイプ
 * @param char $group_id グループID
 * @return number Lineのグループ人数
 */
function count_group_member($ch_type, $group_id) {
    $return = '';

    if ($ch_type == 'group') {
        $url = 'https://api.line.me/v2/bot/group/' . $group_id . '/members/count';
    } elseif ($ch_type == 'room') {
        $url = 'https://api.line.me/v2/bot/room/' . $group_id . '/members/count';
    } else {
        return $return;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN));

    $res = exec_curl($ch);

    //MessageAPIのレスポンスを記録
    @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

    //レスポンスからbodyを取り出す
    $response = substr($res['result'], $res['getinfo']['header_size']);

    $return = json_decode($response);

    return $return;
}

/**
 * curlレスポンスを収集
 * @param char $result result
 * @param char $res_curl get_info
 * @param $method GET | POST
 */
function receipt_curl_response($result, $res_curl, $method) {
    $strHead = substr($result, 0, $res_curl['header_size']);
    $_header = str_replace("\r", '', $strHead);
    $tmp_header = explode("\n", $_header);
    $ary_header = [];
    foreach ($tmp_header as $row_data) {
        $tmp = explode(': ', $row_data);
        $key = trim($tmp[0]);
        if ( $key == '' ) {
            continue;
        }
        $val = str_replace($key.': ', '', $row_data);
        $ary_header[$key] = trim($val);
    }
    $log = '[pro]x-line-request-id => ' . $ary_header['x-line-request-id'] . ' | Method => ' . $method . ' | EndPoint => ' . $res_curl['url'] . ' | StatusCode => ' . $res_curl['http_code'] . ' | date => ' . $ary_header['date'];
    insert_log($log);
}

/**
 * Webhook受信時のログ
 * @param char $response_code レスポンスコード
 * @param char $server_info サーバ情報
 */
function receipt_webhook_request($response_code, $server_info) {
    $protocol = empty($server_info["HTTPS"]) ? "http://" : "https://";
    $url = $protocol . $server_info["HTTP_HOST"] . $server_info["REQUEST_URI"];
    $access_log = '[pro]AccessLog => ' . $server_info["REMOTE_ADDR"] . ' | Method => ' . $server_info['REQUEST_METHOD'] . ' | RequestPath => ' . $url . ' | StatusCode => ' . $response_code . ' | time => ' . date("Y/m/d H:i:s");
    insert_log($access_log);
}

/**
 * 著名確認用の関数
 * @param char $response_code php://inputの情報
 */
function check_signature($str) {
    // ハッシュ作成
    $hash = hash_hmac('sha256', $str, LINE_CHANNEL_SECRET, true);

    // Signature作成
    $sig = base64_encode($hash);

    return $sig;
}

/**
 * 分類機能
 * @return array 分類
 */
function classify_spending() {
    $spending = [
        0 => '未設定',
        1 => '食料費',
        2 => '生活費',
        3 => '衣服費',
        4 => '美健費',
        5 => '交際費',
        6 => '交通費',
        7 => '娯楽費',
        8 => '医療費',
        9 => '通信費',
        10 => '光熱費',
        11 => '住居費',
        12 => '慶弔費',
        13 => 'その他',
        14 => '外食費',
        15 => 'ペット'
    ];

    return $spending;
}

/**
 * 正の整数かどうか？
 * @param str $str 調べる文字列
 * @return bool true or false
 */
function is_regular_integer($str) {
    if (preg_match('/^\d+$/', $str)) {
        return true;
    } else {
        return false;
    }
}

/**
 * sessionの有効時間の設定
 */
function set_session_expiry() {
    ini_set('session.gc_maxlifetime', SESSION_EXPIRY); //ガーベジコレクションの最大有効期限を設定
    session_set_cookie_params(SESSION_EXPIRY); //セッションIDを含むCookieの有効期限を設定
}

/**
 * 最終アクセス時間の更新
 */
function update_last_access_time() {
    $current_time = time();
    $_SESSION['last_access_time'] = $current_time;
}

/**
 * sessionの有効時間のチェック
 * @return bool true or false
 */
function is_session_expiry() {
    $return = true;
    $current_time = time();

    // 最終アクセス時間の取得
    $last_access_time = isset($_SESSION['last_access_time']) ? $_SESSION['last_access_time'] : null;

    if ($last_access_time) {
        if ($current_time - $last_access_time > SESSION_EXPIRY) {
            $return = false;
        }
    }

    return $return;
}

/**
 * セッション削除とリセット
 */
function reset_session() {
    setcookie(session_name(), '', time() - SESSION_EXPIRY); //セッションIDのCookieを削除
    session_unset(); //セッション変数破棄
    session_destroy(); //セッションデータ破棄
    $_SESSION = []; //セッション変数を空の配列でリセット
}