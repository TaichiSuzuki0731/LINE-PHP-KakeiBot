<?php
require('send_admin_line.php'); //管理者にLineメッセージを送る

$unlink_files = '';
$del_errors_files = '';

// mysql_dumpのフォルダ
$backup_db_path = '/backup_db/';

// dbのバックアップは2世代まで保存
$generations = date("Y-m-d", strtotime("-14 day"));

// dbのバックアップ内のフォルダ・ファイルを収集
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(ROOT_DIRECTOR . $backup_db_path,
            FilesystemIterator::CURRENT_AS_FILEINFO |
            FilesystemIterator::KEY_AS_PATHNAME |
            FilesystemIterator::SKIP_DOTS
    )
);

$loop_cnt = 0;
foreach($files as $file_info) {
    if (!$file_info->isFile()) {
        continue;
    }
    $del_files[$loop_cnt] = $file_info;
    $loop_cnt += 1;
}

// mysql_dumpファイル削除
foreach ($del_files as $row) {
    if (is_file($row) && strpos($row, '.ht') === false) {
        $filedate = date("Y-m-d", filemtime($row));
        if($filedate < $generations) {
            if (unlink($row)) {
                $unlink_files .= basename($row) . "\n";
            } else {
                $del_errors_files .= basename($row) . "\n";
            }
        }
    }
}

if ($unlink_files != '') {
    $message = "Unlink_files\n" . $unlink_files . "\nDelete_Error_Files\n" . $del_errors_files;
} else {
    $message = "No_unlink_file";
}

post_messages($message);