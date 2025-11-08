<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$dbname = 'db_siap_siswa';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get all records from a table
function getAll($table, $where = '', $params = []) {
    global $pdo;
    $sql = "SELECT * FROM $table";
    if ($where) {
        $sql .= " WHERE $where";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get single record
function getOne($table, $where, $params = []) {
    global $pdo;
    $sql = "SELECT * FROM $table WHERE $where LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Function to insert record
function insert($table, $data) {
    global $pdo;
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

// Function to update record
function update($table, $data, $where, $whereParams = []) {
    global $pdo;
    $set = [];
    $params = [];
    $counter = 1;
    
    // Build SET clause with numbered parameters
    foreach ($data as $key => $value) {
        $paramName = "param" . $counter;
        $set[] = "$key = :$paramName";
        $params[$paramName] = $value;
        $counter++;
    }
    
    // Build WHERE clause with numbered parameters
    $whereConditions = [];
    if (!empty($where)) {
        $whereParts = explode('?', $where);
        $newWhere = '';
        for ($i = 0; $i < count($whereParts) - 1; $i++) {
            $paramName = "whereparam" . $counter;
            $newWhere .= $whereParts[$i] . ':' . $paramName;
            $params[$paramName] = $whereParams[$i];
            $counter++;
        }
        $newWhere .= $whereParts[count($whereParts) - 1];
        $where = $newWhere;
    }
    
    $setStr = implode(', ', $set);
    $sql = "UPDATE $table SET $setStr WHERE $where";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute($params);
}

// Function to delete record
function delete($table, $where, $params = []) {
    global $pdo;
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// Function to validate user session
function validateUserSession($role) {
    if (!isset($_SESSION['user']) || 
        empty($_SESSION['user']) || 
        $_SESSION['user']['role'] !== $role) {
        return false;
    }
    
    $userId = $_SESSION['user']['id'];
    switch ($role) {
        case 'guru':
            $user = getOne('teachers', 'user_id = ?', [$userId]);
            break;
        case 'admin':
            $user = getOne('admins', 'user_id = ?', [$userId]);
            break;
        case 'orangtua':
            $user = getOne('parents', 'user_id = ?', [$userId]);
            break;
        default:
            return false;
    }
    
    return !empty($user);
}

// Function to clean session
function cleanSession() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    session_destroy();
}
?>
