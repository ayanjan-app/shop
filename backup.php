<?php
// backup.php

$host = "localhost";
$user = "root";
$password = "";
$dbName = "ph";

// backups folder
$backupFolder = __DIR__ . '/backups/';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}

// file name
$backupFile = $backupFolder . "db_backup_" . date("Y-m-d_H-i-s") . ".sql";

// ✅ FULL PATH (IMPORTANT)
$mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

// command
$command = "\"$mysqldump\" --user={$user} --password={$password} --host={$host} {$dbName} > \"$backupFile\" 2>&1";

// run
exec($command, $output, $result);

if ($result === 0) {
    echo "✅ Backup successful!<br>Saved as: " . $backupFile;
} else {
    echo "❌ Backup failed!<br>";
    print_r($output); // shows error if something wrong
}
?>
