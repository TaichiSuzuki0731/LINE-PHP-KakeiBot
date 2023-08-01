<?php
require('line_bot.inc.php');

//処理開始
//Lineサーバに200を返す
$response_code = http_response_code(200);

//Webhook受信時のログ
receipt_webhook_request($response_code, $_SERVER);

//ユーザーからのメッセージ取得
$json_string = file_get_contents('php://input');

// HTTPヘッダーを取得
$headers = getallheaders();

// HTTPヘッダーから、署名検証用データを取得
$headerSignature = $headers["X-Line-Signature"];
//著名の確認
$sig = check_signature($json_string);
// 確認
if ($sig != $headerSignature) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

//jsonデコード
$json_object = json_decode($json_string);

//取得データを変数に格納
$event_type   = $json_object->{"events"}[0]->{"type"};                   //イベントタイプ
$replyToken   = $json_object->{"events"}[0]->{"replyToken"};             //返信用トークン
$message_id   = $json_object->{"events"}[0]->{"message"}->{"id"};        //メッセージID
$message_type = $json_object->{"events"}[0]->{"message"}->{"type"};      //メッセージタイプ
$message_text = $json_object->{"events"}[0]->{"message"}->{"text"};      //メッセージ内容
$ch_type      = $json_object->{"events"}[0]->{"source"}->{"type"};       //チャンネルのタイプ
$user_id      = $json_object->{"events"}[0]->{"source"}->{"userId"};     //user_id
$group_id     = $json_object->{"events"}[0]->{"source"}->{"groupId"};    //group_id

// DBに接続
$db_link = db_connect();

//ユーザ登録
if ($event_type == 'follow') {
    $user_name = get_line_user_profile($user_id); //Lineの名前を取得
    insert_user($db_link, $user_id, $user_name->{"displayName"});
}

//ユーザ情報削除
if ($event_type == 'unfollow') {
    del_user_info($db_link, $user_id);
    del_kakeibo_all_data($db_link, $ch_type, $user_id, $group_id);
}

//グループ or トークルームに参加した際はjoin,メンバーが参加した際はmemberJoined 検知した時にメンバー数をカウントする
if ($event_type == 'join' || $event_type == 'memberJoined' || $event_type == 'leave' || $event_type == 'memberLeft') {
    $get_number_people = count_group_member($ch_type, $group_id);
    $cnt = $get_number_people->{"count"};
    if ($event_type == 'join') { //グループにbotが参加
        //insert_group_member($db_link, $group_id, $cnt);
        $group_name = get_group_name($group_id);
        //insert_group_name($db_link, $group_id, $group_name->{"groupName"});
        insert_group_info($db_link, $group_id, $group_name->{"groupName"}, $cnt);
    } elseif ($event_type == 'memberJoined' || $event_type == 'memberLeft') { //グループでメンバーが参加 or 退出
        update_group_member($db_link, $group_id, $cnt);
    } else { //グループからbot退出
        delete_group_member($db_link, $group_id);
        del_kakeibo_all_data($db_link, $ch_type, $user_id, $group_id);
        //delete_group_name($db_link, $group_id);
    }
}

//メッセージタイプが「text」以外のときは何も返さず終了
if ($message_type != "text") {
    mysqli_close($db_link);
    exit();
}

//改行削除
$message_text = str_replace(array("\r\n", "\r", "\n"), '', $message_text);
//全角数字->半角数字
$message_text = mb_convert_kana($message_text, 'n');
//先頭語尾空白があった際に削除
$message_text = trim($message_text);
//[,]を削除
$message_text = str_replace(',', '', $message_text);

//ユーザネーム取得
$name = get_user_name($db_link, $user_id);
if (count($name) == 0) { //フォローされてない
    $follow_flag = false;
    $line_name = "ゲストさん\n";
} else { //フォローされている
    $follow_flag = true;
    $line_name = $name . "さん\n";
}

//グループ or トークルームの場合は人数を取得
$cnt_member = 0;
if ($ch_type == 'group' || $ch_type == 'room') {
    $cnt_member = count_db_group_member($db_link, $group_id);
}

$insert_flag = false;
$del_flag = false;
$upd_flag = false;

//返信メッセージ
if ($message_text == 'いくら' || $message_text == '幾ら') {
    $sum_price = sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id);
    if ($ch_type == 'user') {
        $return_message_text .= '今月の支出は' . $sum_price . '円';
    } else {
        // グループの合計金額
        $return_message_text .= '今月のグループ合計金額: ';
        $return_message_text .= number_format($sum_price) . "円\n";

        // メッセージ送信者のグループでの合計金額(光熱費抜)
        $return_message_text .= "\n各メンバーの負担金(光熱費抜)\n";
        $user_sum_price = get_sum_user_price_from_group($db_link, $user_id, $group_id, 0);
        $return_message_text .= get_user_name($db_link, $user_id) . 'さん: ' . number_format($user_sum_price) . "円\n";

        // メッセージ送信者のグループ内の他ユーザの個別の合計金額(光熱費抜)
        $ids = get_user_id_from_group($db_link, $group_id);
        foreach ($ids as $id) {
            if ($id != $user_id) {
                $another_sum_price .= get_sum_user_price_from_group($db_link, $id, $group_id, 0);
                $return_message_text .= get_user_name($db_link, $id) . 'さん: ' . number_format($another_sum_price) . "円\n";
            }
        }

        // 光熱費の合計
        $utility_costs = get_sum_utility_costs_from_group($db_link, $group_id, 0);
        $return_message_text .= "\nグループ内の光熱費: " . number_format($utility_costs) . "円\n";

        // 合計金額をグループのメンバーで割る
        $return_message_text .= "\n1人あたり" . number_format($sum_price / $cnt_member) . '円';
    }
} elseif ($message_text == 'ねんかん' || $message_text == '年間') {
    //  過去12ヶ月間で毎月ごとの金額を集計
    $return_message_text = "過去12ヶ月間の出費\n";
    $res = get_price_one_year($db_link, $ch_type, $user_id, $group_id);
    if (!$res) {
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . 'ERROR::get_price_one_year');
    }
    $ave_cnt = 0;
    $all_sum_price = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $return_message_text .= $row['monthly'] . ' => ' . number_format($row['sam_price']) . "円\n";
        // 12ヶ月間の出費を合算
        $all_sum_price += $row['sam_price'];
        // アベレージを算出するときに使う
        $ave_cnt += 1;
    }
    mysqli_free_result($res);

    // 12ヶ月のデータのアベレージを計算して出力
    $return_message_text .= "\n出費平均(過去12ヶ月間より)\n1ヶ月あたり => " . number_format($all_sum_price / $ave_cnt) . "円\n";

    //  過去12ヶ月間で分類ごとの金額を集計
    $return_message_text .= "\n過去12ヶ月間の分類別出費\n";
    $spending_array = classify_spending();
    $res = get_classify_price_one_year($db_link, $ch_type, $user_id, $group_id);
    if (!$res) {
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . 'ERROR::get_classify_price_one_year');
    }
    $ave_classify_price = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $return_message_text .= $spending_array[$row['classify_id']] . ' => ' . number_format($row['sam_price']) . "円\n";
        // アベレージを算出
        array_push($ave_classify_price, $spending_array[$row['classify_id']] . ' => ' . number_format($row['sam_price'] / $ave_cnt) . "円\n");
    }
    mysqli_free_result($res);

    // 分類ごとに12ヶ月のデータのアベレージを計算して出力
    $return_message_text .= "\n分類別平均(過去12ヶ月間より)\n";
    foreach ($ave_classify_price as $row) {
        $return_message_text .= $row;
    }
} elseif ($message_text == 'せんげつ' || $message_text == '先月') {
    // 先月の分類別の出費
    $return_message_text = "\n分類別出費(先月分より)\n";
    $res = get_classify_price_specific_month($db_link, $ch_type, $user_id, $group_id, 1);
    if (!$res) {
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . 'ERROR::get_classify_price_specific_month');
    }
    $sum = 0;
    $spending_array = classify_spending();
    while ($row = mysqli_fetch_assoc($res)) {
        $return_message_text .= $spending_array[$row['classify_id']] . ' => ' . number_format($row['sam_price']) . "円\n";
        $sum += $row['sam_price'];
    }
    mysqli_free_result($res);

    // グループ合計
    $return_message_text .=  "\n先月の合計金額: " . number_format($sum) . "円\n";

    if ($ch_type != 'user') {
        // メッセージ送信者のグループでの合計金額(光熱費抜)
        $return_message_text .= "\n各メンバーの負担金(光熱費抜)\n";
        $user_sum_price = get_sum_user_price_from_group($db_link, $user_id, $group_id, 1);
        $return_message_text .= get_user_name($db_link, $user_id) . 'さん: ' . number_format($user_sum_price) . "円\n";

        // メッセージ送信者のグループ内の他ユーザの個別の合計金額(光熱費抜)
        $ids = get_user_id_from_group($db_link, $group_id);
        foreach ($ids as $id) {
            if ($id != $user_id) {
                $another_sum_price .= get_sum_user_price_from_group($db_link, $id, $group_id, 1);
                $return_message_text .= get_user_name($db_link, $id) . 'さん: ' . number_format($another_sum_price) . "円\n";
            }
        }

        // 合計金額をグループのメンバーで割る
        $return_message_text .= "\n1人あたり" . number_format($sum / $cnt_member) . '円';
    }
} elseif ($message_text == 'こんげつ' || $message_text == '今月') {
    // 今月の分類別の出費
    $spending_array = classify_spending();
    $return_message_text .= "\n分類別出費(今月分より)\n";
    $res = get_classify_price_specific_month($db_link, $ch_type, $user_id, $group_id, 0);
    if (!$res) {
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . 'ERROR::get_classify_price_specific_month');
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $return_message_text .= $spending_array[$row['classify_id']] . ' => ' . number_format($row['sam_price']) . "円\n";
    }
    mysqli_free_result($res);

    //  今月の1日毎の金額を集計
    $return_message_text .= "\n今月の1日毎の出費\n";
    $res = get_price_this_month($db_link, $ch_type, $user_id, $group_id);
    if (!$res) {
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . 'ERROR::get_price_this_month');
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $return_message_text .= $row['date'] . ' => ' . number_format($row['sam_price']) . "円\n";
    }
    mysqli_free_result($res);
} elseif (preg_match("/^[0-9]+$/", $message_text)) { //1~9のみをTRUE
    if ($follow_flag) { //フォロー済み記録可
        //-の位置が[0]かfalseとなる場合のみTRUE
        $mb_str = mb_strpos($message_text, '-');
        if ($mb_str === 0 || $mb_str === false) {
            $insert_flag = true;
        }
        //-が1個以下のみTRUE
        if (substr_count($message_text, '-') > 1) {
            $insert_flag = false;
        }
        if ($insert_flag) {
            $res = insert_kakeibo($db_link, $message_id, $user_id, $group_id, $message_text, $ch_type);
            if (!$res) {
                mysqli_free_result($res);
                mysqli_close($db_link);
                sending_messages($replyToken, $message_type, $line_name . 'ERROR::insert_kakeibo');
            }
            mysqli_free_result($res);
            $path = ROOT_DIRECTOR . '/json/classification.json';
            $send_json = file_get_contents($path);
            mysqli_close($db_link);
            send_flex_message($send_json, $replyToken);
        } else {
            $return_message_text = "「-(ハイフン)」の位置は先頭のみニャ\nまた、-は2回以上は使えませんにゃ〜〜";
        }
    } else { //未フォロー記録不可
        $return_message_text = "友達登録がされていませんにゃ〜〜\nKakeiBotとととととと友達になってくださいニャ、、、。";
    }
} elseif (strpos($message_text, '!') !== false) {
    $message_text = str_replace('!', '', $message_text);
    if (!preg_match("/^[0-9]+$/", $message_text)) {
        mysqli_close($db_link);
        exit();
    }
    if ($follow_flag) {
        $res = update_kakeibo_classify($db_link, $user_id, $group_id, $message_text, $ch_type);
        if (!$res) {
            mysqli_free_result($res);
            mysqli_close($db_link);
            sending_messages($replyToken, $message_type, $line_name . 'ERROR::update_kakeibo_classify');
        }
        mysqli_free_result($res);
    } else { //未フォロー記録不可
        $return_message_text = "友達登録がされていませんにゃ〜〜\nKakeiBotとととととと友達になってくださいニャ、、、。";
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $line_name . $return_message_text);
    }

    $spending_array = classify_spending();
    $return_message_text = $spending_array[$message_text] . "に分類したにゃ\n\n";
    $sum_price = sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id);
    if ($ch_type == 'user') {
        $return_message_text .= '今月の支出は' . $sum_price . '円';
    } else {
        // グループの合計金額
        $return_message_text .= '今月のグループ合計金額: ';
        $return_message_text .= number_format($sum_price) . "円\n";

        // メッセージ送信者のグループでの合計金額(光熱費抜)
        $return_message_text .= "\n各メンバーの負担金(光熱費抜)\n";
        $user_sum_price = get_sum_user_price_from_group($db_link, $user_id, $group_id, 0);
        $return_message_text .= get_user_name($db_link, $user_id) . 'さん: ' . number_format($user_sum_price) . "円\n";

        // メッセージ送信者のグループ内の他ユーザの個別の合計金額(光熱費抜)
        $ids = get_user_id_from_group($db_link, $group_id);
        foreach ($ids as $id) {
            if ($id != $user_id) {
                $another_sum_price .= get_sum_user_price_from_group($db_link, $id, $group_id, 0);
                $return_message_text .= get_user_name($db_link, $id) . 'さん: ' . number_format($another_sum_price) . "円\n";
            }
        }

        // 光熱費の合計
        $utility_costs = get_sum_utility_costs_from_group($db_link, $group_id, 0);
        $return_message_text .= "\nグループ内の光熱費: " . number_format($utility_costs) . "円\n";

        // 合計金額をグループのメンバーで割る
        $return_message_text .= "\n1人あたり" . number_format($sum_price / $cnt_member) . '円';
    }
} elseif ($message_text == 'おーい') {
    $return_message_text = <<<EOT
■使い方
・新たな支出の登録は「1000」,「1,000」と入力して送信すると支出の分類を聞かれるから答えて欲しいニャ🐱
・グループやトークルームで使った場合は、そのチャンネル内での合計支出を出せますニャ。またグループ内のメンバー数で割った一人当たりの支出も出力されますニャ🐱
・「いくら(幾ら)」と送ると今月の簡単な出費が確認できますニャ🐱
・「ねんかん(年間)」と送ると年間の出費が確認できますニャ🐱
・「せんげつ(先月)」と送ると先月の出費が確認できますニャ🐱
・「こんげつ(今月)」と送ると今月の出費が確認できますニャ🐱

■注意
・因みに支出の記録は友達登録していただいている方のみが可能ニャ🐱
・修正を押した際に見れる家計簿データは記帳したことがあるチャンネルだけニャ🐱
・記録したことがないけど見たい場合はそのチャンネルで0と記帳すると見れるニャ🐱
・友達登録を解除するデータが全部消えるから気をつけるにゃ🐱
EOT;
} else {
    mysqli_close($db_link);
    exit();
}

// DBとの接続解除
mysqli_close($db_link);

$return_message_text .=  "\n" . KAKEIBO_WEBPAGE_URL;

//返信実行
sending_messages($replyToken, $message_type, $line_name . $return_message_text);