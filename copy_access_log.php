<?php
require('send_admin_line.php'); //管理者にLineメッセージを送る

$pwd = createRandomStr(40, TRUE, TRUE, TRUE, TRUE, TRUE);
$message = 'Pass =>' . "\n" . $pwd . "\n\n" . 'copy_access_log => ';
$compress_file = ROOT_DIRECTOR . '/compress_folder/access.log';
$file = ROOT_DIRECTOR . '/compress_folder/compress_access_log_' . date(Ymd) . '.zip';

// 圧縮・解凍するためのオブジェクト生成
$zip = new ZipArchive();

// zipフォルダを開く
$result = $zip->open($file, ZipArchive::CREATE);
$file_name = basename($compress_file);
if ($result === true) {
    // 圧縮
    $zip->addFile($compress_file, $file_name);
    if (!$zip) {
        post_messages($message . 'ErrorType => addFile');
        exit();
    }

    // パスワードをつける
    $zip->setEncryptionName($file_name, ZipArchive::EM_AES_256, $pwd);
    if (!$zip) {
        post_messages($message . 'ErrorType => setEncryptionName');
        exit();
    }

    // ファイルを生成
    $zip->close();
    if (!$zip) {
        post_messages($message . 'ErrorType => close');
        exit();
    }

    // ファイルの中身を削除
    file_put_contents($compress_file,'');
    $message .= 'Success';
} else {
    $message .= 'ErrorType => open';
}

post_messages($message);