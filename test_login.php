<?php
require_once 'config.php';

echo "<h2>Test Login</h2>";

$testAccounts = [
    ['username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
    ['username' => 'guru', 'password' => 'guru123', 'role' => 'guru'],
    ['username' => 'ortu', 'password' => 'ortu123', 'role' => 'orangtua']
];

foreach ($testAccounts as $account) {
    echo "<h3>Testing {$account['username']}</h3>";

    // Simulate login
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$account['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "<p>User found: " . htmlspecialchars($user['username']) . " (" . htmlspecialchars($user['role']) . ")</p>";

        // Test password verification
        if (password_verify($account['password'], $user['password'])) {
            echo "<p style='color: green'>✓ Password verification successful</p>";

            // Check profile data
            $profileExists = false;
            switch ($user['role']) {
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

            if ($profile) {
                echo "<p style='color: green'>✓ Profile data exists</p>";
            } else {
                echo "<p style='color: red'>✗ Profile data missing</p>";
            }
        } else {
            echo "<p style='color: red'>✗ Password verification failed</p>";
        }
    } else {
        echo "<p style='color: red'>✗ User not found</p>";
    }

    echo "<hr>";
}

echo "<p><a href='index.php'>Back to login</a></p>";
?>
