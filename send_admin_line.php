<?php
require('line_bot.inc.php');

//特定のユーザにメッセージを送る
function post_messages($message) {
    // リクエストヘッダ
    $header = [
        'Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];

    // 送信するメッセージの下準備
    $post_values[] =
        [
            "type" => "text",
            "text" => $message
        ];
    // 送信するデータ
    $post_data = [
        "to" => DEV_LINE_PRIVATE_ID,
        "messages" => $post_values
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, LINE_PUSH_URL);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_data));

    $result = curl_exec($curl);
    $getinfo = curl_getinfo($curl);

    curl_close($curl);

    @receipt_curl_response($result, $getinfo, 'POST');
}