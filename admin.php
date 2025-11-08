<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student':
                // Validation
                $errors = [];
                $nis = trim($_POST['nis'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $birth_date = $_POST['birth_date'] ?? '';
                $address = trim($_POST['address'] ?? '');
                $class_id = $_POST['class_id'] ?? '';

                // Required fields
                if (empty($nis)) $errors[] = 'NIS wajib diisi';
                if (empty($full_name)) $errors[] = 'Nama lengkap wajib diisi';
                if (empty($gender)) $errors[] = 'Jenis kelamin wajib dipilih';
                if (empty($class_id)) $errors[] = 'Kelas wajib dipilih';

                // NIS uniqueness
                if (!empty($nis)) {
                    $existing = getOne('students', 'nis = ?', [$nis]);
                    if ($existing) $errors[] = 'NIS sudah digunakan oleh siswa lain';
                }

                // Birth date validation
                if (!empty($birth_date)) {
                    $birthDateTime = strtotime($birth_date);
                    if ($birthDateTime > time()) $errors[] = 'Tanggal lahir tidak boleh di masa depan';
                    if ($birthDateTime < strtotime('-100 years')) $errors[] = 'Tanggal lahir tidak valid';
                }

                if (empty($errors)) {
                    $data = [
                        'nis' => $nis,
                        'full_name' => $full_name,
                        'gender' => $gender,
                        'birth_date' => $birth_date ?: null,
                        'address' => $address ?: null,
                        'class_id' => $class_id
                    ];
                    if (insert('students', $data)) {
                        $message = '<div class="alert alert-success">Siswa berhasil ditambahkan!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal menambahkan siswa. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'edit_student':
                // Validation
                $errors = [];
                $nis = trim($_POST['nis'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $birth_date = $_POST['birth_date'] ?? '';
                $address = trim($_POST['address'] ?? '');
                $class_id = $_POST['class_id'] ?? '';
                $student_id = $_POST['student_id'] ?? '';

                // Required fields
                if (empty($nis)) $errors[] = 'NIS wajib diisi';
                if (empty($full_name)) $errors[] = 'Nama lengkap wajib diisi';
                if (empty($gender)) $errors[] = 'Jenis kelamin wajib dipilih';
                if (empty($class_id)) $errors[] = 'Kelas wajib dipilih';

                // NIS uniqueness (exclude current student)
                if (!empty($nis)) {
                    $existing = getOne('students', 'nis = ? AND id != ?', [$nis, $student_id]);
                    if ($existing) $errors[] = 'NIS sudah digunakan oleh siswa lain';
                }

                // Birth date validation
                if (!empty($birth_date)) {
                    $birthDateTime = strtotime($birth_date);
                    if ($birthDateTime > time()) $errors[] = 'Tanggal lahir tidak boleh di masa depan';
                    if ($birthDateTime < strtotime('-100 years')) $errors[] = 'Tanggal lahir tidak valid';
                }

                if (empty($errors)) {
                    $data = [
                        'nis' => $nis,
                        'full_name' => $full_name,
                        'gender' => $gender,
                        'birth_date' => $birth_date ?: null,
                        'address' => $address ?: null,
                        'class_id' => $class_id
                    ];
                    if (update('students', $data, 'id = ?', [$student_id])) {
                        $message = '<div class="alert alert-success">Siswa berhasil diperbarui!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal memperbarui siswa. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'delete_student':
                // Check if student has related records
                $hasAttendance = getOne('attendances', 'student_id = ?', [$_POST['student_id']]);
                $hasGrades = getOne('grades', 'student_id = ?', [$_POST['student_id']]);
                $hasRelations = getOne('student_parent_relations', 'student_id = ?', [$_POST['student_id']]);

                if ($hasAttendance || $hasGrades || $hasRelations) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus siswa dengan catatan yang ada!</div>';
                } else {
                    if (delete('students', 'id = ?', [$_POST['student_id']])) {
                        $message = '<div class="alert alert-success">Siswa berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus siswa!</div>';
                    }
                }
                break;

            case 'add_teacher':
                // Validation
                $errors = [];
                $nip = trim($_POST['nip'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone_number = trim($_POST['phone_number'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                // Required fields
                if (empty($nip)) $errors[] = 'NIP wajib diisi';
                if (empty($full_name)) $errors[] = 'Nama lengkap wajib diisi';
                if (empty($username)) $errors[] = 'Username wajib diisi';
                if (empty($password)) $errors[] = 'Password wajib diisi';

                // NIP uniqueness
                if (!empty($nip)) {
                    $existing = getOne('teachers', 'nip = ?', [$nip]);
                    if ($existing) $errors[] = 'NIP sudah digunakan oleh guru lain';
                }

                // Username validation
                if (!empty($username)) {
                    if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter';
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'Username hanya boleh berisi huruf, angka, dan underscore';
                    $existing = getOne('users', 'username = ?', [$username]);
                    if ($existing) $errors[] = 'Username sudah digunakan';
                }

                // Password validation
                if (!empty($password) && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';

                // Email validation
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Format email tidak valid';
                }

                if (empty($errors)) {
                    // First create user account
                    $userData = [
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => 'guru'
                    ];

                    if (insert('users', $userData)) {
                        $userId = $pdo->lastInsertId();
                        $data = [
                            'user_id' => $userId,
                            'full_name' => $full_name,
                            'nip' => $nip,
                            'email' => $email ?: null,
                            'phone_number' => $phone_number ?: null
                        ];

                        if (insert('teachers', $data)) {
                            $message = '<div class="alert alert-success">Guru berhasil ditambahkan dengan username: <strong>' . htmlspecialchars($username) . '</strong></div>';
                        } else {
                            // Rollback user creation
                            delete('users', 'id = ?', [$userId]);
                            $message = '<div class="alert alert-danger">Gagal menambahkan guru. Silakan coba lagi.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Gagal membuat akun pengguna. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'edit_teacher':
                // Validation
                $errors = [];
                $nip = trim($_POST['nip'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone_number = trim($_POST['phone_number'] ?? '');
                $teacher_id = $_POST['teacher_id'] ?? '';

                // Required fields
                if (empty($nip)) $errors[] = 'NIP wajib diisi';
                if (empty($full_name)) $errors[] = 'Nama lengkap wajib diisi';

                // NIP uniqueness (exclude current teacher)
                if (!empty($nip)) {
                    $existing = getOne('teachers', 'nip = ? AND id != ?', [$nip, $teacher_id]);
                    if ($existing) $errors[] = 'NIP sudah digunakan oleh guru lain';
                }

                // Email validation
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Format email tidak valid';
                }

                if (empty($errors)) {
                    $data = [
                        'full_name' => $full_name,
                        'nip' => $nip,
                        'email' => $email ?: null,
                        'phone_number' => $phone_number ?: null
                    ];
                    if (update('teachers', $data, 'id = ?', [$teacher_id])) {
                        $message = '<div class="alert alert-success">Guru berhasil diperbarui!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal memperbarui guru. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'delete_teacher':
                // Check if teacher is homeroom teacher or has subjects assigned
                $isHomeroom = getOne('classes', 'homeroom_teacher_id = ?', [$_POST['teacher_id']]);
                $hasSubjects = getOne('teacher_subjects', 'teacher_id = ?', [$_POST['teacher_id']]);

                if ($isHomeroom || $hasSubjects) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus guru yang ditugaskan ke kelas atau mata pelajaran!</div>';
                } else {
                    $teacher = getOne('teachers', 'id = ?', [$_POST['teacher_id']]);
                    if ($teacher && delete('teachers', 'id = ?', [$_POST['teacher_id']])) {
                        // Also delete user account
                        delete('users', 'id = ?', [$teacher['user_id']]);
                        $message = '<div class="alert alert-success">Guru berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus guru!</div>';
                    }
                }
                break;



            case 'add_class':
                $data = [
                    'class_name' => $_POST['class_name'],
                    'homeroom_teacher_id' => $_POST['homeroom_teacher_id'] ?: null
                ];
                if (insert('classes', $data)) {
                    $message = '<div class="alert alert-success">Kelas berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan menambahkan kelas!</div>';
                }
                break;

            case 'edit_class':
                $data = [
                    'class_name' => $_POST['class_name'],
                    'homeroom_teacher_id' => $_POST['homeroom_teacher_id'] ?: null
                ];
                if (update('classes', $data, 'id = ?', [$_POST['class_id']])) {
                    $message = '<div class="alert alert-success">Kelas berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan memperbarui kelas!</div>';
                }
                break;

            case 'delete_class':
                // Check if class has students
                $hasStudents = getOne('students', 'class_id = ?', [$_POST['class_id']]);
                $hasSubjects = getOne('teacher_subjects', 'class_id = ?', [$_POST['class_id']]);

                if ($hasStudents || $hasSubjects) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus kelas dengan siswa atau mata pelajaran yang ditugaskan!</div>';
                } else {
                    if (delete('classes', 'id = ?', [$_POST['class_id']])) {
                        $message = '<div class="alert alert-success">Kelas berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus kelas!</div>';
                    }
                }
                break;

            case 'add_subject':
                $data = [
                    'subject_name' => $_POST['subject_name'],
                    'description' => $_POST['description']
                ];
                if (insert('subjects', $data)) {
                    $message = '<div class="alert alert-success">Mata pelajaran berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan menambahkan mata pelajaran!</div>';
                }
                break;

            case 'edit_subject':
                $data = [
                    'subject_name' => $_POST['subject_name'],
                    'description' => $_POST['description']
                ];
                if (update('subjects', $data, 'id = ?', [$_POST['subject_id']])) {
                    $message = '<div class="alert alert-success">Mata pelajaran berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan memperbarui mata pelajaran!</div>';
                }
                break;

            case 'delete_subject':
                // Check if subject is assigned to teachers
                $hasAssignments = getOne('teacher_subjects', 'subject_id = ?', [$_POST['subject_id']]);
                $hasGrades = getOne('grades', 'subject_id = ?', [$_POST['subject_id']]);
                $hasAttendance = getOne('attendances', 'subject_id = ?', [$_POST['subject_id']]);

                if ($hasAssignments || $hasGrades || $hasAttendance) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus mata pelajaran dengan catatan yang ada!</div>';
                } else {
                    if (delete('subjects', 'id = ?', [$_POST['subject_id']])) {
                        $message = '<div class="alert alert-success">Mata pelajaran berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus mata pelajaran!</div>';
                    }
                }
                break;

            case 'add_parent':
                $data = [
                    'full_name' => $_POST['full_name'],
                    'email' => $_POST['email'],
                    'phone_number' => $_POST['phone_number']
                ];
                if (insert('parents', $data)) {
                    $message = '<div class="alert alert-success">Orang tua berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan menambahkan orang tua!</div>';
                }
                break;

            case 'edit_parent':
                $data = [
                    'full_name' => $_POST['full_name'],
                    'email' => $_POST['email'],
                    'phone_number' => $_POST['phone_number']
                ];
                if (update('parents', $data, 'id = ?', [$_POST['parent_id']])) {
                    $message = '<div class="alert alert-success">Orang tua berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Kesalahan memperbarui orang tua!</div>';
                }
                break;

            case 'delete_parent':
                // Check if parent has related students
                $hasRelations = getOne('student_parent_relations', 'parent_id = ?', [$_POST['parent_id']]);

                if ($hasRelations) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus orang tua dengan hubungan siswa yang ada!</div>';
                } else {
                    if (delete('parents', 'id = ?', [$_POST['parent_id']])) {
                        $message = '<div class="alert alert-success">Orang tua berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus orang tua!</div>';
                    }
                }
                break;

            case 'add_user':
                // Validation
                $errors = [];
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';

                // Required fields
                if (empty($username)) $errors[] = 'Username wajib diisi';
                if (empty($password)) $errors[] = 'Password wajib diisi';
                if (empty($role)) $errors[] = 'Role wajib dipilih';

                // Username validation
                if (!empty($username)) {
                    if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter';
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'Username hanya boleh berisi huruf, angka, dan underscore';
                    $existing = getOne('users', 'username = ?', [$username]);
                    if ($existing) $errors[] = 'Username sudah digunakan';
                }

                // Password validation
                if (!empty($password) && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';

                // Role validation
                $validRoles = ['admin', 'guru'];
                if (!empty($role) && !in_array($role, $validRoles)) $errors[] = 'Role tidak valid';

                if (empty($errors)) {
                    $data = [
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role
                    ];
                    if (insert('users', $data)) {
                        $message = '<div class="alert alert-success">User berhasil ditambahkan!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal menambahkan user. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'edit_user':
                // Validation
                $errors = [];
                $username = trim($_POST['username'] ?? '');
                $role = $_POST['role'] ?? '';
                $user_id = $_POST['user_id'] ?? '';

                // Required fields
                if (empty($username)) $errors[] = 'Username wajib diisi';
                if (empty($role)) $errors[] = 'Role wajib dipilih';

                // Username validation
                if (!empty($username)) {
                    if (strlen($username) < 3) $errors[] = 'Username minimal 3 karakter';
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'Username hanya boleh berisi huruf, angka, dan underscore';
                    $existing = getOne('users', 'username = ? AND id != ?', [$username, $user_id]);
                    if ($existing) $errors[] = 'Username sudah digunakan';
                }

                // Password validation (optional for edit)
                if (!empty($_POST['password']) && strlen($_POST['password']) < 6) $errors[] = 'Password minimal 6 karakter';

                // Role validation
                $validRoles = ['admin', 'guru'];
                if (!empty($role) && !in_array($role, $validRoles)) $errors[] = 'Role tidak valid';

                if (empty($errors)) {
                    $data = [
                        'username' => $username,
                        'role' => $role
                    ];
                    if (!empty($_POST['password'])) {
                        $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }
                    if (update('users', $data, 'id = ?', [$user_id])) {
                        $message = '<div class="alert alert-success">User berhasil diperbarui!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal memperbarui user. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'delete_user':
                // Check if user is linked to teacher
                $linkedTeacher = getOne('teachers', 'user_id = ?', [$_POST['user_id']]);
                if ($linkedTeacher) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus user yang terhubung dengan guru!</div>';
                } else {
                    if (delete('users', 'id = ?', [$_POST['user_id']])) {
                        $message = '<div class="alert alert-success">User berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Kesalahan menghapus user!</div>';
                    }
                }
                break;

            case 'add_relation':
                // Validation
                $errors = [];
                $student_id = $_POST['student_id'] ?? '';
                $parent_id = $_POST['parent_id'] ?? '';
                $relationship_type = $_POST['relationship_type'] ?? '';

                // Required fields
                if (empty($student_id)) $errors[] = 'Siswa wajib dipilih';
                if (empty($parent_id)) $errors[] = 'Orang tua wajib dipilih';
                if (empty($relationship_type)) $errors[] = 'Hubungan wajib dipilih';

                // Validate relationship type
                $validRelationships = ['ayah', 'ibu', 'wali', 'kakek', 'nenek', 'saudara'];
                if (!empty($relationship_type) && !in_array($relationship_type, $validRelationships)) {
                    $errors[] = 'Tipe hubungan tidak valid';
                }

                // Check if relation already exists
                if (!empty($student_id) && !empty($parent_id)) {
                    $existing = getOne('student_parent_relations', 'student_id = ? AND parent_id = ?', [$student_id, $parent_id]);
                    if ($existing) $errors[] = 'Relasi antara siswa dan orang tua ini sudah ada';
                }

                if (empty($errors)) {
                    $data = [
                        'student_id' => $student_id,
                        'parent_id' => $parent_id,
                        'relationship_type' => $relationship_type
                    ];
                    if (insert('student_parent_relations', $data)) {
                        $message = '<div class="alert alert-success">Relasi siswa-orang tua berhasil ditambahkan!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal menambahkan relasi. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'edit_relation':
                // Validation
                $errors = [];
                $relation_id = $_POST['relation_id'] ?? '';
                $student_id = $_POST['student_id'] ?? '';
                $parent_id = $_POST['parent_id'] ?? '';
                $relationship_type = $_POST['relationship_type'] ?? '';

                // Required fields
                if (empty($relation_id)) $errors[] = 'ID relasi tidak valid';
                if (empty($student_id)) $errors[] = 'Siswa wajib dipilih';
                if (empty($parent_id)) $errors[] = 'Orang tua wajib dipilih';
                if (empty($relationship_type)) $errors[] = 'Hubungan wajib dipilih';

                // Validate relationship type
                $validRelationships = ['ayah', 'ibu', 'wali', 'kakek', 'nenek', 'saudara'];
                if (!empty($relationship_type) && !in_array($relationship_type, $validRelationships)) {
                    $errors[] = 'Tipe hubungan tidak valid';
                }

                // Check if relation already exists (exclude current relation)
                if (!empty($student_id) && !empty($parent_id) && !empty($relation_id)) {
                    $existing = getOne('student_parent_relations', 'student_id = ? AND parent_id = ? AND id != ?', [$student_id, $parent_id, $relation_id]);
                    if ($existing) $errors[] = 'Relasi antara siswa dan orang tua ini sudah ada';
                }

                if (empty($errors)) {
                    $data = [
                        'student_id' => $student_id,
                        'parent_id' => $parent_id,
                        'relationship_type' => $relationship_type
                    ];
                    if (update('student_parent_relations', $data, 'id = ?', [$relation_id])) {
                        $message = '<div class="alert alert-success">Relasi siswa-orang tua berhasil diperbarui!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal memperbarui relasi. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'delete_relation':
                // Validate relation_id
                $relation_id = $_POST['relation_id'] ?? '';
                if (empty($relation_id)) {
                    $message = '<div class="alert alert-danger">ID relasi tidak valid.</div>';
                    break;
                }

                // Check if relation exists
                $relation = getOne('student_parent_relations', 'id = ?', [$relation_id]);
                if (!$relation) {
                    $message = '<div class="alert alert-danger">Relasi tidak ditemukan.</div>';
                    break;
                }

                // Delete relation
                if (delete('student_parent_relations', 'id = ?', [$relation_id])) {
                    $message = '<div class="alert alert-success">Relasi siswa-orang tua berhasil dihapus!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Gagal menghapus relasi.</div>';
                }
                break;

            case 'add_teacher_subject':
                // Validation
                $errors = [];
                $teacher_id = $_POST['teacher_id'] ?? '';
                $subject_id = $_POST['subject_id'] ?? '';
                $class_id = $_POST['class_id'] ?? '';

                // Required fields
                if (empty($teacher_id)) $errors[] = 'Guru wajib dipilih';
                if (empty($subject_id)) $errors[] = 'Mata pelajaran wajib dipilih';
                if (empty($class_id)) $errors[] = 'Kelas wajib dipilih';

                // Check if assignment already exists
                if (!empty($teacher_id) && !empty($subject_id) && !empty($class_id)) {
                    $existing = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ? AND class_id = ?', [$teacher_id, $subject_id, $class_id]);
                    if ($existing) $errors[] = 'Penugasan guru untuk mata pelajaran dan kelas ini sudah ada';
                }

                if (empty($errors)) {
                    $data = [
                        'teacher_id' => $teacher_id,
                        'subject_id' => $subject_id,
                        'class_id' => $class_id
                    ];
                    if (insert('teacher_subjects', $data)) {
                        $message = '<div class="alert alert-success">Penugasan guru berhasil ditambahkan!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal menambahkan penugasan guru. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'edit_teacher_subject':
                // Validation
                $errors = [];
                $assignment_id = $_POST['assignment_id'] ?? '';
                $teacher_id = $_POST['teacher_id'] ?? '';
                $subject_id = $_POST['subject_id'] ?? '';
                $class_id = $_POST['class_id'] ?? '';

                // Required fields
                if (empty($assignment_id)) $errors[] = 'ID penugasan tidak valid';
                if (empty($teacher_id)) $errors[] = 'Guru wajib dipilih';
                if (empty($subject_id)) $errors[] = 'Mata pelajaran wajib dipilih';
                if (empty($class_id)) $errors[] = 'Kelas wajib dipilih';

                // Check if assignment already exists (exclude current assignment)
                if (!empty($teacher_id) && !empty($subject_id) && !empty($class_id) && !empty($assignment_id)) {
                    $existing = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ? AND class_id = ? AND id != ?', [$teacher_id, $subject_id, $class_id, $assignment_id]);
                    if ($existing) $errors[] = 'Penugasan guru untuk mata pelajaran dan kelas ini sudah ada';
                }

                if (empty($errors)) {
                    $data = [
                        'teacher_id' => $teacher_id,
                        'subject_id' => $subject_id,
                        'class_id' => $class_id
                    ];
                    if (update('teacher_subjects', $data, 'id = ?', [$assignment_id])) {
                        $message = '<div class="alert alert-success">Penugasan guru berhasil diperbarui!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal memperbarui penugasan guru. Silakan coba lagi.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><strong>Kesalahan Validasi:</strong><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    $message .= '</ul></div>';
                }
                break;

            case 'delete_teacher_subject':
                // Validate assignment_id
                $assignment_id = $_POST['assignment_id'] ?? '';
                if (empty($assignment_id)) {
                    $message = '<div class="alert alert-danger">ID penugasan tidak valid.</div>';
                    break;
                }

                // Check if assignment exists
                $assignment = getOne('teacher_subjects', 'id = ?', [$assignment_id]);
                if (!$assignment) {
                    $message = '<div class="alert alert-danger">Penugasan tidak ditemukan.</div>';
                    break;
                }

                // Check if there are related records (grades, attendance)
                $hasGrades = getOne('grades', 'subject_id = ? AND student_id IN (SELECT id FROM students WHERE class_id = ?)', [$assignment['subject_id'], $assignment['class_id']]);
                $hasAttendance = getOne('attendances', 'subject_id = ? AND student_id IN (SELECT id FROM students WHERE class_id = ?)', [$assignment['subject_id'], $assignment['class_id']]);

                if ($hasGrades || $hasAttendance) {
                    $message = '<div class="alert alert-danger">Tidak dapat menghapus penugasan karena ada data nilai atau absensi terkait!</div>';
                } else {
                    if (delete('teacher_subjects', 'id = ?', [$assignment_id])) {
                        $message = '<div class="alert alert-success">Penugasan guru berhasil dihapus!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Gagal menghapus penugasan guru.</div>';
                    }
                }
                break;
        }
    }
}

// Get data for display
$students = getAll('students');
$teachers = getAll('teachers');
$classes = getAll('classes');
$subjects = getAll('subjects');
$parents = getAll('parents');
$users = getAll('users');
$admins = array_filter($users, function($user) { return $user['role'] === 'admin'; });
$teacher_subjects = getAll('teacher_subjects');
$grades = getAll('grades');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SIAP-Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Base Styles */
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
            border-radius: 0.25rem;
            margin: 0.125rem 0.5rem;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background: #495057;
        }
        .content-wrapper {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show {
            display: block;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #343a40;
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s ease;
        }
        .mobile-menu-toggle:hover {
            background: rgba(0,0,0,0.1);
        }

        /* Responsive Design - Large Tablets and Desktops */
        @media (max-width: 1199px) {
            .sidebar {
                width: 220px;
            }
            .content-wrapper {
                margin-left: 220px;
            }
        }

        /* Responsive Design - Tablets */
        @media (max-width: 991px) {
            .sidebar {
                width: 200px;
            }
            .content-wrapper {
                margin-left: 200px;
            }
            .calendar-grid {
                min-width: 500px;
            }
        }

        /* Responsive Design - Small Tablets and Large Mobile */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content-wrapper {
                margin-left: 0;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .mobile-menu-toggle {
                display: block;
            }

            /* Table Responsiveness */
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive .table {
                margin-bottom: 0;
                min-width: 600px;
            }
            .table-responsive .table thead th {
                position: sticky;
                top: 0;
                background: #f8f9fa;
                z-index: 10;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            /* Calendar Responsiveness */
            .calendar-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 0.375rem;
                border: 1px solid #dee2e6;
            }
            .calendar-grid {
                min-width: 400px;
                font-size: 0.85rem;
            }
            .calendar-grid .day {
                min-height: 70px;
                padding: 0.25rem;
            }
            .calendar-day-header {
                padding: 0.375rem;
                font-size: 0.875rem;
            }
        }

        /* Responsive Design - Mobile */
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            /* Header Responsiveness */
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: stretch !important;
                gap: 1rem;
            }
            .d-flex.justify-content-between.align-items-center.mb-4 .d-md-none {
                order: -1;
                margin-bottom: 0;
            }
            .d-flex.justify-content-between.align-items-center.mb-4 h2 {
                font-size: 1.5rem;
                text-align: center;
            }

            /* Button Groups */
            .btn-group-responsive {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .btn-group-responsive .btn {
                width: 100%;
                min-height: 44px; /* Touch-friendly */
                font-size: 1rem;
            }

            /* Form Responsiveness */
            .row.mb-4 .col-md-3,
            .row.mb-4 .col-md-4,
            .row.mb-4 .col-md-6,
            .row.mb-4 .col-md-12 {
                margin-bottom: 1rem;
            }
            .card-body .row .col-md-6,
            .card-body .row .col-md-4,
            .card-body .row .col-md-3 {
                margin-bottom: 1rem;
            }
            .card-body .form-control,
            .card-body .form-select {
                min-height: 44px; /* Touch-friendly */
                font-size: 1rem;
            }
            .card-body .btn {
                min-height: 44px;
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }

            /* Table Enhancements */
            .table-responsive {
                font-size: 0.875rem;
            }
            .table-responsive .table {
                min-width: 700px; /* Ensure readability */
            }

            /* Calendar Mobile */
            .calendar-grid {
                min-width: 350px;
                font-size: 0.75rem;
            }
            .calendar-grid .day {
                min-height: 60px;
                padding: 0.125rem;
            }
            .calendar-day-header {
                padding: 0.25rem;
                font-size: 0.75rem;
            }
            .day-number {
                font-size: 0.7rem;
            }
            .day-stats {
                font-size: 0.6rem;
                margin-top: 10px;
            }

            /* Stats Cards */
            .row.mb-4 .col-md-3 {
                margin-bottom: 1rem;
            }
            .card.bg-success,
            .card.bg-warning,
            .card.bg-danger,
            .card.bg-info {
                margin-bottom: 1rem;
            }

            /* Modal-like forms */
            .card.mb-4 {
                margin-left: -10px;
                margin-right: -10px;
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            /* Navigation Improvements */
            .sidebar .nav-link {
                padding: 1rem;
                font-size: 1rem;
            }
            .sidebar .sidebar-sticky .p-3 h5 {
                font-size: 1.25rem;
            }
        }

        /* Extra Small Mobile */
        @media (max-width: 480px) {
            .container-fluid {
                padding-left: 5px;
                padding-right: 5px;
            }

            .sidebar {
                width: 100%;
            }

            .calendar-grid {
                min-width: 300px;
            }

            .table-responsive .table {
                min-width: 800px;
            }

            /* Stack everything vertically */
            .d-flex.justify-content-between.align-items-center.mb-3 {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            .d-flex.justify-content-between.align-items-center.mb-3 .btn {
                width: 100%;
                min-height: 48px;
                font-size: 1.1rem;
            }

            /* Improve readability */
            body {
                font-size: 0.9rem;
            }
            h2 {
                font-size: 1.25rem;
            }
            h3 {
                font-size: 1.125rem;
            }
            .card-header {
                font-size: 1rem;
                padding: 0.75rem;
            }
            .card-body {
                padding: 1rem 0.75rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .sidebar .nav-link {
                padding: 1rem;
                min-height: 48px;
            }
            .btn {
                min-height: 44px;
                font-size: 1rem;
            }
            .form-control,
            .form-select {
                min-height: 44px;
                font-size: 1rem;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .sidebar {
                background: #000;
                color: #fff;
            }
            .sidebar .nav-link:hover {
                background: #fff;
                color: #000;
            }
            .sidebar .nav-link.active {
                background: #fff;
                color: #000;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .sidebar,
            .content-wrapper {
                transition: none;
            }
        }

        /* Print styles */
        @media print {
            .sidebar,
            .sidebar-overlay,
            .mobile-menu-toggle {
                display: none !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .calendar-container {
                overflow: visible !important;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar col-md-2 d-none d-md-block">
            <div class="sidebar-sticky">
                    <div class="p-3">
                    <h5><i class="fas fa-school"></i> SIAP-Siswa</h5>
                    <p class="mb-4">Dashboard Admin</p>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#students" onclick="showTab('students')">
                            <i class="fas fa-user-graduate"></i> Data Siswa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#teachers" onclick="showTab('teachers')">
                            <i class="fas fa-chalkboard-teacher"></i> Data Guru
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#classes" onclick="showTab('classes')">
                            <i class="fas fa-school"></i> Data Kelas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#subjects" onclick="showTab('subjects')">
                            <i class="fas fa-book"></i> Mata Pelajaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#parents" onclick="showTab('parents')">
                            <i class="fas fa-users"></i> Data Orang Tua
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#relations" onclick="showTab('relations')">
                            <i class="fas fa-link"></i> Relasi Siswa-Orang Tua
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#users" onclick="showTab('users')">
                            <i class="fas fa-users-cog"></i> Pengelolaan User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#admins" onclick="showTab('admins')">
                            <i class="fas fa-user-shield"></i> Data Admin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#teacher-subjects" onclick="showTab('teacher-subjects')">
                            <i class="fas fa-chalkboard-teacher"></i> Penugasan Guru
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#attendances" onclick="showTab('attendances')">
                            <i class="fas fa-calendar-check"></i> Absensi Siswa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#grades" onclick="showTab('grades')">
                            <i class="fas fa-graduation-cap"></i> Nilai Siswa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#reports" onclick="showTab('reports')">
                            <i class="fas fa-chart-bar"></i> Laporan & Export
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="content-wrapper flex-fill">
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                    <div class="d-md-none">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                </div>



            </div>

                <?php echo $message; ?>

                <!-- Students Tab -->
                <div id="students-tab" class="tab-content">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Manajemen Data Siswa</h3>
                        <button class="btn btn-primary" onclick="showAddForm('student')">
                            <i class="fas fa-plus"></i> Tambah Siswa Baru
                        </button>
                    </div>

                    <div id="add-student-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Siswa Baru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_student">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">NIS</label>
                                        <input type="text" class="form-control" name="nis" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Jenis Kelamin</label>
                                        <select class="form-control" name="gender" required>
                                            <option value="L">Laki-laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-control" name="class_id" required>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Lahir</label>
                                        <input type="date" class="form-control" name="birth_date">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Alamat</label>
                                        <textarea class="form-control" name="address" rows="2"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Data</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('student')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Jenis Kelamin</th>
                                            <th>Kelas</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['nis']); ?></td>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td><?php echo $student['gender'] === 'L' ? 'Male' : 'Female'; ?></td>
                                                <td>
                                                    <?php
                                                    $class = getOne('classes', 'id = ?', [$student['class_id']]);
                                                    echo htmlspecialchars($class['class_name'] ?? 'N/A');
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo addslashes($student['nis']); ?>', '<?php echo addslashes($student['full_name']); ?>', '<?php echo $student['gender']; ?>', '<?php echo $student['birth_date']; ?>', '<?php echo addslashes($student['address'] ?? ''); ?>', <?php echo $student['class_id']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teachers Tab -->
                <div id="teachers-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Manajemen Data Guru</h3>
                        <button class="btn btn-primary" onclick="showAddForm('teacher')">
                            <i class="fas fa-plus"></i> Tambah Guru Baru
                        </button>
                    </div>

                    <div id="add-teacher-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Guru Baru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_teacher">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">NIP</label>
                                        <input type="text" class="form-control" name="nip" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nomor Telepon</label>
                                        <input type="text" class="form-control" name="phone_number">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Guru</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('teacher')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>NIP</th>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Telepon</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($teacher['nip']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['phone_number'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editTeacher(<?php echo $teacher['id']; ?>, '<?php echo addslashes($teacher['nip']); ?>', '<?php echo addslashes($teacher['full_name']); ?>', '<?php echo addslashes($teacher['email'] ?? ''); ?>', '<?php echo addslashes($teacher['phone_number'] ?? ''); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteTeacher(<?php echo $teacher['id']; ?>, '<?php echo addslashes($teacher['full_name']); ?>')">Hapus</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Classes Tab -->
                <div id="classes-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Manajemen Kelas</h3>
                        <button class="btn btn-primary" onclick="showAddForm('class')">
                            <i class="fas fa-plus"></i> Tambah Kelas
                        </button>
                    </div>

                    <div id="add-class-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Kelas Baru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_class">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Kelas</label>
                                        <input type="text" class="form-control" name="class_name" placeholder="contoh: Kelas 7A" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Guru Wali Kelas</label>
                                        <select class="form-control" name="homeroom_teacher_id">
                                            <option value="">Pilih Guru</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Kelas</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('class')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Homeroom Teacher</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($class['homeroom_teacher_id']) {
                                                        $teacher = getOne('teachers', 'id = ?', [$class['homeroom_teacher_id']]);
                                                        echo htmlspecialchars($teacher['full_name'] ?? 'N/A');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editClass(<?php echo $class['id']; ?>, '<?php echo addslashes($class['class_name']); ?>', <?php echo $class['homeroom_teacher_id'] ?? 'null'; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>, '<?php echo addslashes($class['class_name']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subjects Tab -->
                <div id="subjects-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Subjects Management</h3>
                        <button class="btn btn-primary" onclick="showAddForm('subject')">
                            <i class="fas fa-plus"></i> Add Subject
                        </button>
                    </div>

                    <div id="add-subject-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Add New Subject</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_subject">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Subject Name</label>
                                        <input type="text" class="form-control" name="subject_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Save Subject</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('subject')">Cancel</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Subject Name</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['description'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editSubject(<?php echo $subject['id']; ?>, '<?php echo addslashes($subject['subject_name']); ?>', '<?php echo addslashes($subject['description'] ?? ''); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteSubject(<?php echo $subject['id']; ?>, '<?php echo addslashes($subject['subject_name']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parents Tab -->
                <div id="parents-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Parents Management</h3>
                        <button class="btn btn-primary" onclick="showAddForm('parent')">
                            <i class="fas fa-plus"></i> Add Parent
                        </button>
                    </div>

                    <div id="add-parent-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Add New Parent</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_parent">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="phone_number">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Save Parent</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('parent')">Cancel</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($parents as $parent): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($parent['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($parent['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($parent['phone_number'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editParent(<?php echo $parent['id']; ?>, '<?php echo addslashes($parent['full_name']); ?>', '<?php echo addslashes($parent['email'] ?? ''); ?>', '<?php echo addslashes($parent['phone_number'] ?? ''); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteParent(<?php echo $parent['id']; ?>, '<?php echo addslashes($parent['full_name']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Relations Tab -->
                <div id="relations-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Manajemen Relasi Siswa-Orang Tua</h3>
                        <button class="btn btn-primary" onclick="showAddForm('relation')">
                            <i class="fas fa-plus"></i> Tambah Relasi
                        </button>
                    </div>

                    <div id="add-relation-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Relasi Siswa-Orang Tua</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_relation">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Siswa</label>
                                        <select class="form-control" name="student_id" required>
                                            <option value="">Pilih Siswa</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name'] . ' (NIS: ' . $student['nis'] . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Orang Tua</label>
                                        <select class="form-control" name="parent_id" required>
                                            <option value="">Pilih Orang Tua</option>
                                            <?php foreach ($parents as $parent): ?>
                                                <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Hubungan</label>
                                        <select class="form-control" name="relationship_type" required>
                                            <option value="ayah">Ayah</option>
                                            <option value="ibu">Ibu</option>
                                            <option value="wali">Wali</option>
                                            <option value="kakek">Kakek</option>
                                            <option value="nenek">Nenek</option>
                                            <option value="saudara">Saudara</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Relasi</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('relation')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th>NIS</th>
                                            <th>Nama Orang Tua</th>
                                            <th>Hubungan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $relations = getAll('student_parent_relations');
                                        foreach ($relations as $relation):
                                            $student = getOne('students', 'id = ?', [$relation['student_id']]);
                                            $parent = getOne('parents', 'id = ?', [$relation['parent_id']]);
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['nis'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($parent['full_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($relation['relationship_type'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editRelation(<?php echo $relation['id']; ?>, <?php echo $relation['student_id']; ?>, <?php echo $relation['parent_id']; ?>, '<?php echo $relation['relationship_type']; ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteRelation(<?php echo $relation['id']; ?>, '<?php echo addslashes($student['full_name'] ?? 'Unknown'); ?> - <?php echo addslashes($parent['full_name'] ?? 'Unknown'); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Tab -->
                <div id="users-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Pengelolaan User</h3>
                        <button class="btn btn-primary" onclick="showAddForm('user')">
                            <i class="fas fa-plus"></i> Tambah User Baru
                        </button>
                    </div>

                    <div id="add-user-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah User Baru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_user">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role</label>
                                        <select class="form-control" name="role" required>
                                            <option value="admin">Admin</option>
                                            <option value="guru">Guru</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan User</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('user')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '<?php echo $user['role']; ?>', 'users-tab')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admins Tab -->
                <div id="admins-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Data Admin</h3>
                        <button class="btn btn-primary" onclick="showAddForm('admin')">
                            <i class="fas fa-plus"></i> Tambah Admin Baru
                        </button>
                    </div>

                    <div id="add-admin-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Admin Baru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_user">
                                <input type="hidden" name="role" value="admin">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Admin</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('admin')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['role']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['username']); ?>', '<?php echo $admin['role']; ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['username']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teacher-Subjects Tab -->
                <div id="teacher-subjects-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Penugasan Guru ke Mata Pelajaran & Kelas</h3>
                        <button class="btn btn-primary" onclick="showAddForm('teacher-subject')">
                            <i class="fas fa-plus"></i> Tambah Penugasan
                        </button>
                    </div>

                    <div id="add-teacher-subject-form" class="card mb-4" style="display: none;">
                        <div class="card-header">Tambah Penugasan Guru</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_teacher_subject">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Guru</label>
                                        <select class="form-control" name="teacher_id" required>
                                            <option value="">Pilih Guru</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name'] . ' (NIP: ' . $teacher['nip'] . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Mata Pelajaran</label>
                                        <select class="form-control" name="subject_id" required>
                                            <option value="">Pilih Mata Pelajaran</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-control" name="class_id" required>
                                            <option value="">Pilih Kelas</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Simpan Penugasan</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddForm('teacher-subject')">Batal</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Guru</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Kelas</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teacher_subjects as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $teacher = getOne('teachers', 'id = ?', [$assignment['teacher_id']]);
                                                    echo htmlspecialchars($teacher['full_name'] ?? 'N/A');
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $subject = getOne('subjects', 'id = ?', [$assignment['subject_id']]);
                                                    echo htmlspecialchars($subject['subject_name'] ?? 'N/A');
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $class = getOne('classes', 'id = ?', [$assignment['class_id']]);
                                                    echo htmlspecialchars($class['class_name'] ?? 'N/A');
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editTeacherSubject(<?php echo $assignment['id']; ?>, <?php echo $assignment['teacher_id']; ?>, <?php echo $assignment['subject_id']; ?>, <?php echo $assignment['class_id']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteTeacherSubject(<?php echo $assignment['id']; ?>, '<?php echo addslashes($teacher['full_name'] ?? 'Unknown'); ?> - <?php echo addslashes($subject['subject_name'] ?? 'Unknown'); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendances Tab -->
                <div id="attendances-tab" class="tab-content" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>Manajemen Absensi Siswa</h3>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="showAttendanceTab('summary')">
                                <i class="fas fa-chart-bar"></i> Ringkasan Absensi
                            </button>
                            <button class="btn btn-outline-success" onclick="showAttendanceTab('calendar')">
                                <i class="fas fa-calendar-alt"></i> Kalender Absensi
                            </button>
                        </div>
                    </div>

                    <!-- Attendance Summary Tab -->
                    <div id="attendance-summary-tab" class="attendance-subtab">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Ringkasan Absensi</h5>
                            </div>
                            <div class="card-body">
                                <!-- Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-control" id="summary_class_filter">
                                            <option value="">Semua Kelas</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Mata Pelajaran</label>
                                        <select class="form-control" id="summary_subject_filter">
                                            <option value="">Semua Mata Pelajaran</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tanggal Mulai</label>
                                        <input type="date" class="form-control" id="summary_start_date">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tanggal Akhir</label>
                                        <input type="date" class="form-control" id="summary_end_date">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <button class="btn btn-primary me-2" onclick="loadAttendanceSummary()">
                                            <i class="fas fa-search"></i> Tampilkan Ringkasan
                                        </button>
                                        <button class="btn btn-success" onclick="exportAttendanceToExcel()">
                                            <i class="fas fa-download"></i> Export ke Excel
                                        </button>
                                    </div>
                                </div>

                                <!-- Summary Statistics -->
                                <div class="row mb-4" id="summary-stats" style="display: none;">
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h4 id="total-present">0</h4>
                                                <small>Hadir</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-white">
                                            <div class="card-body text-center">
                                                <h4 id="total-late">0</h4>
                                                <small>Terlambat</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-danger text-white">
                                            <div class="card-body text-center">
                                                <h4 id="total-absent">0</h4>
                                                <small>Tidak Hadir</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h4 id="total-percentage">0%</h4>
                                                <small>Kehadiran Rata-rata</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Table -->
                                <div class="table-responsive">
                                    <table class="table table-striped" id="attendance-summary-table">
                                        <thead>
                                            <tr>
                                                <th>NIS</th>
                                                <th>Nama Siswa</th>
                                                <th>Kelas</th>
                                                <th>Hadir</th>
                                                <th>Terlambat</th>
                                                <th>Tidak Hadir</th>
                                                <th>Total Hari</th>
                                                <th>Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody id="summary-table-body">
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">Pilih filter dan klik "Tampilkan Ringkasan" untuk melihat data</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Calendar Tab -->
                    <div id="attendance-calendar-tab" class="attendance-subtab" style="display: none;">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-calendar-alt"></i> Kalender Absensi</h5>
                                <div class="d-flex align-items-center">
                                    <button class="btn btn-sm btn-outline-secondary me-2" onclick="changeMonth(-1)">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h6 class="mb-0 me-2" id="calendar-month-year">November 2024</h6>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="changeMonth(1)">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Calendar Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-control" id="calendar_class_filter">
                                            <option value="">Pilih Kelas</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Mata Pelajaran</label>
                                        <select class="form-control" id="calendar_subject_filter">
                                            <option value="">Pilih Mata Pelajaran</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <button class="btn btn-primary w-100" onclick="loadAttendanceCalendar()">
                                            <i class="fas fa-calendar-day"></i> Tampilkan Kalender
                                        </button>
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-center">
                                            <div class="me-4">
                                                <span class="badge bg-success me-1">&nbsp;&nbsp;</span>
                                                <small>Baik (≥90%)</small>
                                            </div>
                                            <div class="me-4">
                                                <span class="badge bg-warning me-1">&nbsp;&nbsp;</span>
                                                <small>Sedang (70-89%)</small>
                                            </div>
                                            <div class="me-4">
                                                <span class="badge bg-danger me-1">&nbsp;&nbsp;</span>
                                                <small>Buruk (<70%)</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-light text-dark border me-1">&nbsp;&nbsp;</span>
                                                <small>Tidak Ada Data</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Calendar Grid -->
                                <div class="calendar-container">
                                    <div class="calendar-grid" id="calendar-grid">
                                        <!-- Calendar will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grades Tab -->
                <div id="grades-tab" class="tab-content" style="display: none;">
                    <h3>Nilai Siswa</h3>
                    <p>Manage student grades.</p>
                </div>

                <!-- Reports Tab -->
                <div id="reports-tab" class="tab-content" style="display: none;">
                    <h3>Laporan & Export</h3>
                    <p>Generate reports and export data.</p>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.style.display = 'none');

            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';

            // Update active nav link
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

        function showAddForm(type) {
            document.getElementById('add-' + type + '-form').style.display = 'block';
        }

        function hideAddForm(type) {
            document.getElementById('add-' + type + '-form').style.display = 'none';
        }

        // Edit functions
        function editStudent(id, nis, fullName, gender, birthDate, address, classId) {
            // Create or show edit form
            let editForm = document.getElementById('edit-student-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-student-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Siswa</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_student">
                            <input type="hidden" name="student_id" id="edit_student_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NIS</label>
                                    <input type="text" class="form-control" name="nis" id="edit_nis" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jenis Kelamin</label>
                                    <select class="form-control" name="gender" id="edit_gender" required>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kelas</label>
                                    <select class="form-control" name="class_id" id="edit_class_id" required>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" name="birth_date" id="edit_birth_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Perbarui Siswa</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('student')">Batal</button>
                        </form>
                    </div>
                `;
                document.getElementById('students-tab').insertBefore(editForm, document.querySelector('#students-tab .card'));
            }

            // Populate form
            document.getElementById('edit_student_id').value = id;
            document.getElementById('edit_nis').value = nis;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_gender').value = gender;
            document.getElementById('edit_birth_date').value = birthDate;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_class_id').value = classId;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function editTeacher(id, nip, fullName, email, phone) {
            let editForm = document.getElementById('edit-teacher-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-teacher-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Guru</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_teacher">
                            <input type="hidden" name="teacher_id" id="edit_teacher_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NIP</label>
                                    <input type="text" class="form-control" name="nip" id="edit_nip" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="full_name" id="edit_teacher_full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nomor Telepon</label>
                                    <input type="text" class="form-control" name="phone_number" id="edit_phone_number">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Perbarui Guru</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('teacher')">Batal</button>
                        </form>
                    </div>
                `;
                document.getElementById('teachers-tab').insertBefore(editForm, document.querySelector('#teachers-tab .card'));
            }

            document.getElementById('edit_teacher_id').value = id;
            document.getElementById('edit_nip').value = nip;
            document.getElementById('edit_teacher_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone_number').value = phone;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function editClass(id, className, homeroomTeacherId) {
            let editForm = document.getElementById('edit-class-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-class-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Class</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_class">
                            <input type="hidden" name="class_id" id="edit_class_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Class Name</label>
                                    <input type="text" class="form-control" name="class_name" id="edit_class_name" placeholder="e.g., Kelas 7A" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Homeroom Teacher</label>
                                    <select class="form-control" name="homeroom_teacher_id" id="edit_homeroom_teacher_id">
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Update Class</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('class')">Cancel</button>
                        </form>
                    </div>
                `;
                document.getElementById('classes-tab').insertBefore(editForm, document.querySelector('#classes-tab .card'));
            }

            document.getElementById('edit_class_id').value = id;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_homeroom_teacher_id').value = homeroomTeacherId;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function editSubject(id, subjectName, description) {
            let editForm = document.getElementById('edit-subject-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-subject-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Subject</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_subject">
                            <input type="hidden" name="subject_id" id="edit_subject_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Name</label>
                                    <input type="text" class="form-control" name="subject_name" id="edit_subject_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Update Subject</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('subject')">Cancel</button>
                        </form>
                    </div>
                `;
                document.getElementById('subjects-tab').insertBefore(editForm, document.querySelector('#subjects-tab .card'));
            }

            document.getElementById('edit_subject_id').value = id;
            document.getElementById('edit_subject_name').value = subjectName;
            document.getElementById('edit_description').value = description;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function hideEditForm(type) {
            const editForm = document.getElementById('edit-' + type + '-form');
            if (editForm) {
                editForm.style.display = 'none';
            }
        }

        // Delete functions with confirmation
        function deleteStudent(id, name) {
            if (confirm('Anda yakin ingin menghapus data siswa "' + name + '"? Tindakan ini tidak dapat dibatalkan.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="student_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteTeacher(id, name) {
            if (confirm('Are you sure you want to delete teacher "' + name + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_teacher">
                    <input type="hidden" name="teacher_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteClass(id, name) {
            if (confirm('Are you sure you want to delete class "' + name + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_class">
                    <input type="hidden" name="class_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteSubject(id, name) {
            if (confirm('Are you sure you want to delete subject "' + name + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_subject">
                    <input type="hidden" name="subject_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editParent(id, fullName, email, phone) {
            let editForm = document.getElementById('edit-parent-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-parent-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Parent</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_parent">
                            <input type="hidden" name="parent_id" id="edit_parent_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" id="edit_parent_full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_parent_email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" name="phone_number" id="edit_parent_phone_number">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Update Parent</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('parent')">Cancel</button>
                        </form>
                    </div>
                `;
                document.getElementById('parents-tab').insertBefore(editForm, document.querySelector('#parents-tab .card'));
            }

            document.getElementById('edit_parent_id').value = id;
            document.getElementById('edit_parent_full_name').value = fullName;
            document.getElementById('edit_parent_email').value = email;
            document.getElementById('edit_parent_phone_number').value = phone;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function deleteParent(id, name) {
            if (confirm('Are you sure you want to delete parent "' + name + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_parent">
                    <input type="hidden" name="parent_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editUser(id, username, role) {
            let editForm = document.getElementById('edit-user-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-user-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit User</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" id="edit_username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" name="password" id="edit_password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="form-control" name="role" id="edit_role" required>
                                        <option value="admin">Admin</option>
                                        <option value="guru">Guru</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Update User</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('user')">Cancel</button>
                        </form>
                    </div>
                `;
                document.getElementById('users-tab').insertBefore(editForm, document.querySelector('#users-tab .card'));
            }

            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function deleteUser(id, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editRelation(id, studentId, parentId, relationshipType) {
            let editForm = document.getElementById('edit-relation-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-relation-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Relasi Siswa-Orang Tua</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_relation">
                            <input type="hidden" name="relation_id" id="edit_relation_id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Siswa</label>
                                    <select class="form-control" name="student_id" id="edit_relation_student_id" required>
                                        <option value="">Pilih Siswa</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name'] . ' (NIS: ' . $student['nis'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Orang Tua</label>
                                    <select class="form-control" name="parent_id" id="edit_relation_parent_id" required>
                                        <option value="">Pilih Orang Tua</option>
                                        <?php foreach ($parents as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hubungan</label>
                                    <select class="form-control" name="relationship_type" id="edit_relation_relationship_type" required>
                                        <option value="ayah">Ayah</option>
                                        <option value="ibu">Ibu</option>
                                        <option value="wali">Wali</option>
                                        <option value="kakek">Kakek</option>
                                        <option value="nenek">Nenek</option>
                                        <option value="saudara">Saudara</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Perbarui Relasi</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('relation')">Batal</button>
                        </form>
                    </div>
                `;
                document.getElementById('relations-tab').insertBefore(editForm, document.querySelector('#relations-tab .card'));
            }

            document.getElementById('edit_relation_id').value = id;
            document.getElementById('edit_relation_student_id').value = studentId;
            document.getElementById('edit_relation_parent_id').value = parentId;
            document.getElementById('edit_relation_relationship_type').value = relationshipType;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function deleteRelation(id, name) {
            if (confirm('Anda yakin ingin menghapus relasi "' + name + '"? Tindakan ini tidak dapat dibatalkan.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_relation">
                    <input type="hidden" name="relation_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editTeacherSubject(id, teacherId, subjectId, classId) {
            let editForm = document.getElementById('edit-teacher-subject-form');
            if (!editForm) {
                editForm = document.createElement('div');
                editForm.id = 'edit-teacher-subject-form';
                editForm.className = 'card mb-4';
                editForm.innerHTML = `
                    <div class="card-header">Edit Penugasan Guru</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_teacher_subject">
                            <input type="hidden" name="assignment_id" id="edit_assignment_id">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Guru</label>
                                    <select class="form-control" name="teacher_id" id="edit_teacher_subject_teacher_id" required>
                                        <option value="">Pilih Guru</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name'] . ' (NIP: ' . $teacher['nip'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select class="form-control" name="subject_id" id="edit_teacher_subject_subject_id" required>
                                        <option value="">Pilih Mata Pelajaran</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Kelas</label>
                                    <select class="form-control" name="class_id" id="edit_teacher_subject_class_id" required>
                                        <option value="">Pilih Kelas</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Perbarui Penugasan</button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('teacher-subject')">Batal</button>
                        </form>
                    </div>
                `;
                document.getElementById('teacher-subjects-tab').insertBefore(editForm, document.querySelector('#teacher-subjects-tab .card'));
            }

            document.getElementById('edit_assignment_id').value = id;
            document.getElementById('edit_teacher_subject_teacher_id').value = teacherId;
            document.getElementById('edit_teacher_subject_subject_id').value = subjectId;
            document.getElementById('edit_teacher_subject_class_id').value = classId;

            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }

        function deleteTeacherSubject(id, name) {
            if (confirm('Anda yakin ingin menghapus penugasan guru "' + name + '"? Tindakan ini tidak dapat dibatalkan.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_teacher_subject">
                    <input type="hidden" name="assignment_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
