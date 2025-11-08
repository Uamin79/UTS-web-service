<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_siap_siswa', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('correct_relationships.sql');
    $pdo->exec($sql);

    echo 'Relasi tabel berhasil dikoreksi!';
} catch (Exception $e) {
    echo 'Error koreksi relasi: ' . $e->getMessage();
}
?>
