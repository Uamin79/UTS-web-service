<?php
require_once 'config.php';

try {
    // Baca file SQL
    $sql = file_get_contents('fix_login_data.sql');
    
    // Pisahkan statement SQL
    $queries = explode(';', $sql);
    
    echo "<h2>Memperbaiki Data Login</h2>";
    
    foreach($queries as $query) {
        $query = trim($query);
        if(empty($query)) continue;
        
        try {
            $pdo->exec($query);
            echo "<p style='color: green'>✓ Query berhasil: " . substr($query, 0, 50) . "...</p>";
        } catch(PDOException $e) {
            echo "<p style='color: red'>✗ Error pada query: " . $e->getMessage() . "</p>";
            echo "<p>Query: " . $query . "</p>";
        }
    }
    
    echo "<h3>Verifikasi Data User:</h3>";
    
    // Cek user accounts
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Username</th><th>Role</th><th>Profile Status</th></tr>";
    
    foreach($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        
        // Cek profile
        $profile_exists = false;
        switch($user['role']) {
            case 'admin':
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
                break;
            case 'guru':
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
                break;
            case 'orangtua':
                $stmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
                break;
        }
        
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        
        echo "<td>" . ($profile ? "✓ OK" : "✗ Missing") . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<p><b>Login credentials:</b></p>";
    echo "<ul>";
    echo "<li>Admin: admin/admin123</li>";
    echo "<li>Guru: guru/guru123</li>";
    echo "<li>Orang Tua: ortu/ortu123</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php'>Kembali ke halaman login</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red'>Error koneksi database: " . $e->getMessage() . "</p>";
}