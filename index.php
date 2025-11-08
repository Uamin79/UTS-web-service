<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
    $redirected = false;
    switch ($_SESSION['user']['role']) {
        case 'admin':
            header('Location: admin.php');
            $redirected = true;
            break;
        case 'guru':
            header('Location: guru.php');
            $redirected = true;
            break;
        case 'orangtua':
            header('Location: orangtua.php');
            $redirected = true;
            break;
    }
    if ($redirected) {
        exit;
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check user credentials
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Verify additional data based on role
        $validUser = false;
        
        switch ($user['role']) {
            case 'admin':
                $admin = getOne('admins', 'user_id = ?', [$user['id']]);
                $validUser = !empty($admin);
                break;
            case 'guru':
                $guru = getOne('teachers', 'user_id = ?', [$user['id']]);
                $validUser = !empty($guru);
                break;
            case 'orangtua':
                $orangtua = getOne('parents', 'user_id = ?', [$user['id']]);
                $validUser = !empty($orangtua);
                break;
        }

        if ($validUser) {
            $_SESSION['user'] = $user;
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin.php');
                    break;
                case 'guru':
                    header('Location: guru.php');
                    break;
                case 'orangtua':
                    header('Location: orangtua.php');
                    break;
            }
            exit;
        } else {
            $message = 'Akun tidak valid atau tidak memiliki akses!';
        }
    } else {
        $message = 'Username atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAP-Siswa - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 0 1rem;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
        }
        .login-header {
            background: #4e73df;
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem 1.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            body {
                padding: 0.5rem;
            }
            .login-container {
                padding: 0 0.5rem;
                max-width: 100%;
            }
            .login-header {
                padding: 1.5rem 1rem;
            }
            .login-header h3 {
                font-size: 1.5rem;
            }
            .login-body {
                padding: 1.5rem 1rem;
            }
            .login-card {
                border-radius: 10px;
            }
        }

        @media (max-width: 480px) {
            .login-header {
                padding: 1.25rem 0.75rem;
            }
            .login-body {
                padding: 1.25rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
                    <div class="login-header">
                        <h3><i class="fas fa-school"></i> SIAP-Siswa</h3>
                        <p>Sistem Informasi Akademik dan Prestasi Siswa</p>
                    </div>
                    <div class="login-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user"></i> Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                        </form>

                        <hr class="my-4">
                        <div class="text-center">
                            <small class="text-muted">
                                --@@--<br>
                                24.01.53.7009<br>
                                admin : admin/admin123
                            </small>
                        </div>
                    </div>
                </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
