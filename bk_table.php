<?php
require('send_admin_line.php'); //管理者にLineメッセージを送る

$db_info = db_info();
$srv = $db_info['host'];
$db_user = $db_info['user'];
$db_pass = $db_info['pass'];
$db_name = $db_info['name'];

$pwd = createRandomStr(40, TRUE, TRUE, TRUE, TRUE, TRUE);
$message = 'Pass =>' . "\n" . $pwd . "\n\n";

$temp_folder_path = ROOT_DIRECTOR . '/backup_db/temp/';
$zip_folder_name =  ROOT_DIRECTOR . '/backup_db/mysqldump_db_date' . date('ymd') . '_' . date('His') . '.zip';

//DBに接続
$db_link = db_connect();

if(!$db_link){
    // MySQLに接続できなかったら
    $message .= "MySQL Connect Error => " . mysqli_error($db_link);
    post_messages($message);
    exit();
}

// MySQLに接続できたら
$res = mysqli_query($db_link, "SHOW DATABASES");
while ($row = mysqli_fetch_assoc($res)) {
    $fileName = $row['Database'] . '_' . date('ymd') . '_' . date('His') . '.sql';
    $command = "mysqldump " . $db_name . " --host=" . $srv . " --user=" . $db_user . " --password=" . $db_pass . " > " . $temp_folder_path . $fileName;
    system($command);
}

mysqli_free_result($res);
mysqli_close($db_link);

$message .= "Database Dump Success\n";

// zip化する.sqlを格納
$compress_files = glob($temp_folder_path . '*.sql');

// 圧縮・解凍するためのオブジェクト生成
$zip = new ZipArchive();

foreach ($compress_files as $compress_file) {
    $file_name = basename($compress_file);
    // zipフォルダを開く
    $result = $zip->open($zip_folder_name, ZipArchive::CREATE);
    if ($result === true) {
        // 圧縮
        $zip->addFile($compress_file, $file_name);
        if (!$zip) {
            $message .= 'ErrorType => addFile';
            continue;
        }

        // パスワードをつける
        $zip->setEncryptionName($file_name, ZipArchive::EM_AES_256, $pwd);
        if (!$zip) {
            $message .= 'ErrorType => setEncryptionName';
            continue;
        }

        // ファイルを生成
        $zip->close();
        if (!$zip) {
            $message .= 'ErrorType => close';
            continue;
        }

        // tempデータを削除
        if (!unlink($compress_file)){
            $message .= 'ErrorType => unlink' . "\n" . 'ErrorFile => $compress_file' . "\n";
        }
    } else {
        $message .= 'ErrorType => open';
    }
}
$message .= 'Database Backup Success';
post_messages($message);