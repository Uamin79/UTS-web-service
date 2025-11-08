<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_siap_siswa', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('add_foreign_keys.sql');
    $pdo->exec($sql);

    echo 'Foreign keys added successfully to database!';
} catch (Exception $e) {
    echo 'Error adding foreign keys: ' . $e->getMessage();
}
?>
