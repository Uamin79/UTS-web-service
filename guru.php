<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is guru
if (!isset($_SESSION['user']) || empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'guru') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

// Get teacher info
$teacher = getOne('teachers', 'user_id = ?', [$user['id']]);

// If teacher info not found, logout
if (!$teacher) {
    session_destroy();
    header('Location: index.php?error=invalid_teacher');
    exit;
}
// Ensure CSRF token exists for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get teacher's classes and subjects (used for authorization in POST handlers)
$teacherSubjects = getAll('teacher_subjects', 'teacher_id = ?', [$teacher['id']]);
$classIds = array_unique(array_column($teacherSubjects, 'class_id'));
$classes = [];
foreach ($classIds as $classId) {
    $classes[] = getOne('classes', 'id = ?', [$classId]);
}

// Build subjects list assigned to this teacher (fallback to all subjects)
$subjects = [];
$subjectIds = array_unique(array_column($teacherSubjects, 'subject_id'));
if (!empty($subjectIds)) {
    foreach ($subjectIds as $sid) {
        $s = getOne('subjects', 'id = ?', [$sid]);
        if ($s) $subjects[] = $s;
    }
} else {
    $subjects = getAll('subjects');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_attendance':
                // Enhanced validation for attendance data
                $attendanceJson = trim($_POST['attendance_data'] ?? '');
                if (empty($attendanceJson)) {
                    $message = '<div class="alert alert-danger">Data absensi tidak boleh kosong.</div>';
                    break;
                }

                $attendanceData = json_decode($attendanceJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = '<div class="alert alert-danger">Format data absensi tidak valid.</div>';
                    break;
                }

                if (!is_array($attendanceData) || empty($attendanceData)) {
                    $message = '<div class="alert alert-danger">Data absensi kosong atau tidak valid.</div>';
                    break;
                }

                // Validate teacher has permission for the subject/class combination
                $firstData = $attendanceData[0];
                $subjectId = (int)$firstData['subject_id'];
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $subjectId]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                $saved = 0; $updated = 0; $skipped = 0; $errors = [];

                foreach ($attendanceData as $index => $data) {
                    // Comprehensive validation
                    if (empty($data['student_id']) || !is_numeric($data['student_id'])) {
                        $errors[] = "Baris " . ($index + 1) . ": ID siswa tidak valid";
                        $skipped++;
                        continue;
                    }

                    if (empty($data['subject_id']) || !is_numeric($data['subject_id'])) {
                        $errors[] = "Baris " . ($index + 1) . ": ID mata pelajaran tidak valid";
                        $skipped++;
                        continue;
                    }

                    if (empty($data['date']) || !strtotime($data['date'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Tanggal tidak valid";
                        $skipped++;
                        continue;
                    }

                    // Validate date is not in future
                    if (strtotime($data['date']) > time()) {
                        $errors[] = "Baris " . ($index + 1) . ": Tanggal tidak boleh di masa depan";
                        $skipped++;
                        continue;
                    }

                    // Validate status
                    $validStatuses = ['hadir', 'sakit', 'izin', 'alpa'];
                    $status = $data['status'] ?? 'hadir';
                    if (!in_array($status, $validStatuses)) {
                        $errors[] = "Baris " . ($index + 1) . ": Status absensi tidak valid";
                        $skipped++;
                        continue;
                    }

                    // Verify student exists and belongs to a class assigned to teacher
                    $studentId = (int)$data['student_id'];
                    $student = getOne('students', 'id = ?', [$studentId]);
                    if (!$student) {
                        $errors[] = "Baris " . ($index + 1) . ": Siswa tidak ditemukan";
                        $skipped++;
                        continue;
                    }

                    // Check if student belongs to a class assigned to this teacher
                    $classAssigned = false;
                    foreach ($classes as $class) {
                        if ($class['id'] == $student['class_id']) {
                            $classAssigned = true;
                            break;
                        }
                    }
                    if (!$classAssigned) {
                        $errors[] = "Baris " . ($index + 1) . ": Siswa tidak termasuk dalam kelas yang Anda ajar";
                        $skipped++;
                        continue;
                    }

                    // Normalize values
                    $subjectId = (int)$data['subject_id'];
                    $date = $data['date'];
                    $notes = trim($data['notes'] ?? '');

                    // Prevent duplicate entries for same student/subject/date
                    $existing = getOne('attendances', 'student_id = ? AND subject_id = ? AND date = ?', [$studentId, $subjectId, $date]);
                    if ($existing) {
                        // Update existing
                        if (update('attendances', [
                            'status' => $status,
                            'notes' => $notes
                        ], 'id = ?', [$existing['id']])) {
                            $updated++;
                        } else {
                            $errors[] = "Baris " . ($index + 1) . ": Gagal memperbarui data absensi";
                            $skipped++;
                        }
                    } else {
                        // Insert new
                        if (insert('attendances', [
                            'student_id' => $studentId,
                            'subject_id' => $subjectId,
                            'date' => $date,
                            'status' => $status,
                            'notes' => $notes
                        ])) {
                            $saved++;
                        } else {
                            $errors[] = "Baris " . ($index + 1) . ": Gagal menyimpan data absensi";
                            $skipped++;
                        }
                    }
                }

                // Build success message with details
                $successMsg = '<div class="alert alert-success">';
                $successMsg .= '<strong>Absensi berhasil diproses!</strong><br>';
                $successMsg .= 'Disimpan baru: ' . $saved . '<br>';
                $successMsg .= 'Diperbarui: ' . $updated . '<br>';
                $successMsg .= 'Dilewati: ' . $skipped;
                if (!empty($errors)) {
                    $successMsg .= '<br><br><strong>Peringatan:</strong><br>' . implode('<br>', array_slice($errors, 0, 5)); // Show first 5 errors
                    if (count($errors) > 5) {
                        $successMsg .= '<br>... dan ' . (count($errors) - 5) . ' error lainnya';
                    }
                }
                $successMsg .= '</div>';
                $message = $successMsg;
                break;

            case 'save_grades':
                // Enhanced validation for grades data
                $gradeJson = trim($_POST['grade_data'] ?? '');
                if (empty($gradeJson)) {
                    $message = '<div class="alert alert-danger">Data nilai tidak boleh kosong.</div>';
                    break;
                }

                $gradeData = json_decode($gradeJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = '<div class="alert alert-danger">Format data nilai tidak valid.</div>';
                    break;
                }

                if (!is_array($gradeData) || empty($gradeData)) {
                    $message = '<div class="alert alert-danger">Data nilai kosong atau tidak valid.</div>';
                    break;
                }

                // Validate teacher has permission for the subject/class combination
                $firstData = $gradeData[0];
                $subjectId = (int)$firstData['subject_id'];
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $subjectId]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                $saved = 0; $updated = 0; $skipped = 0; $errors = [];

                foreach ($gradeData as $index => $data) {
                    // Comprehensive validation
                    if (empty($data['student_id']) || !is_numeric($data['student_id'])) {
                        $errors[] = "Baris " . ($index + 1) . ": ID siswa tidak valid";
                        $skipped++;
                        continue;
                    }

                    if (empty($data['subject_id']) || !is_numeric($data['subject_id'])) {
                        $errors[] = "Baris " . ($index + 1) . ": ID mata pelajaran tidak valid";
                        $skipped++;
                        continue;
                    }

                    if (!isset($data['score']) || !is_numeric($data['score'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Nilai harus berupa angka";
                        $skipped++;
                        continue;
                    }

                    $score = floatval($data['score']);
                    if ($score < 0 || $score > 100) {
                        $errors[] = "Baris " . ($index + 1) . ": Nilai harus antara 0-100";
                        $skipped++;
                        continue;
                    }

                    // Validate assessment type
                    $validAssessmentTypes = ['tugas', 'uts', 'uas'];
                    $assessmentType = $data['assessment_type'] ?? 'tugas';
                    if (!in_array($assessmentType, $validAssessmentTypes)) {
                        $errors[] = "Baris " . ($index + 1) . ": Tipe penilaian tidak valid";
                        $skipped++;
                        continue;
                    }

                    // Validate grade date
                    $gradeDate = $data['grade_date'] ?? date('Y-m-d');
                    if (!strtotime($gradeDate)) {
                        $errors[] = "Baris " . ($index + 1) . ": Tanggal nilai tidak valid";
                        $skipped++;
                        continue;
                    }

                    // Verify student exists and belongs to a class assigned to teacher
                    $studentId = (int)$data['student_id'];
                    $student = getOne('students', 'id = ?', [$studentId]);
                    if (!$student) {
                        $errors[] = "Baris " . ($index + 1) . ": Siswa tidak ditemukan";
                        $skipped++;
                        continue;
                    }

                    // Check if student belongs to a class assigned to this teacher
                    $classAssigned = false;
                    foreach ($classes as $class) {
                        if ($class['id'] == $student['class_id']) {
                            $classAssigned = true;
                            break;
                        }
                    }
                    if (!$classAssigned) {
                        $errors[] = "Baris " . ($index + 1) . ": Siswa tidak termasuk dalam kelas yang Anda ajar";
                        $skipped++;
                        continue;
                    }

                    // Check existing grade for same student/subject/assessment/date
                    $existing = getOne('grades', 'student_id = ? AND subject_id = ? AND assessment_type = ? AND grade_date = ?', [$studentId, $subjectId, $assessmentType, $gradeDate]);
                    if ($existing) {
                        // Update existing
                        if (update('grades', [
                            'score' => $score
                        ], 'id = ?', [$existing['id']])) {
                            $updated++;
                        } else {
                            $errors[] = "Baris " . ($index + 1) . ": Gagal memperbarui data nilai";
                            $skipped++;
                        }
                    } else {
                        // Insert new
                        if (insert('grades', [
                            'student_id' => $studentId,
                            'subject_id' => $subjectId,
                            'assessment_type' => $assessmentType,
                            'score' => $score,
                            'grade_date' => $gradeDate
                        ])) {
                            $saved++;
                        } else {
                            $errors[] = "Baris " . ($index + 1) . ": Gagal menyimpan data nilai";
                            $skipped++;
                        }
                    }
                }

                // Build success message with details
                $successMsg = '<div class="alert alert-success">';
                $successMsg .= '<strong>Nilai berhasil diproses!</strong><br>';
                $successMsg .= 'Disimpan baru: ' . $saved . '<br>';
                $successMsg .= 'Diperbarui: ' . $updated . '<br>';
                $successMsg .= 'Dilewati: ' . $skipped;
                if (!empty($errors)) {
                    $successMsg .= '<br><br><strong>Peringatan:</strong><br>' . implode('<br>', array_slice($errors, 0, 5)); // Show first 5 errors
                    if (count($errors) > 5) {
                        $successMsg .= '<br>... dan ' . (count($errors) - 5) . ' error lainnya';
                    }
                }
                $successMsg .= '</div>';
                $message = $successMsg;
                break;

            case 'edit_attendance':
                // Validate attendance_id
                $attendanceId = (int)($_POST['attendance_id'] ?? 0);
                if ($attendanceId <= 0) {
                    $message = '<div class="alert alert-danger">ID kehadiran tidak valid.</div>';
                    break;
                }

                // Get attendance record
                $attendance = getOne('attendances', 'id = ?', [$attendanceId]);
                if (!$attendance) {
                    $message = '<div class="alert alert-danger">Record kehadiran tidak ditemukan.</div>';
                    break;
                }

                // Validate teacher has permission for the subject
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $attendance['subject_id']]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                // Verify student exists and belongs to a class assigned to teacher
                $student = getOne('students', 'id = ?', [$attendance['student_id']]);
                if (!$student) {
                    $message = '<div class="alert alert-danger">Siswa tidak ditemukan.</div>';
                    break;
                }

                $classAssigned = false;
                foreach ($classes as $class) {
                    if ($class['id'] == $student['class_id']) {
                        $classAssigned = true;
                        break;
                    }
                }
                if (!$classAssigned) {
                    $message = '<div class="alert alert-danger">Siswa tidak termasuk dalam kelas yang Anda ajar.</div>';
                    break;
                }

                // Validate status
                $validStatuses = ['hadir', 'sakit', 'izin', 'alpa'];
                $status = $_POST['status'] ?? '';
                if (!in_array($status, $validStatuses)) {
                    $message = '<div class="alert alert-danger">Status absensi tidak valid.</div>';
                    break;
                }

                // Validate date is not in future (if date is being changed, but here it's not, so optional)
                // Notes can be updated freely

                // Update attendance
                $updateData = [
                    'status' => $status,
                    'notes' => trim($_POST['notes'] ?? '')
                ];

                if (update('attendances', $updateData, 'id = ?', [$attendanceId])) {
                    $message = '<div class="alert alert-success">Kehadiran berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Gagal memperbarui kehadiran.</div>';
                }
                break;

            case 'delete_attendance':
                // Validate attendance_id
                $attendanceId = (int)($_POST['attendance_id'] ?? 0);
                if ($attendanceId <= 0) {
                    $message = '<div class="alert alert-danger">ID kehadiran tidak valid.</div>';
                    break;
                }

                // Get attendance record
                $attendance = getOne('attendances', 'id = ?', [$attendanceId]);
                if (!$attendance) {
                    $message = '<div class="alert alert-danger">Record kehadiran tidak ditemukan.</div>';
                    break;
                }

                // Validate teacher has permission for the subject
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $attendance['subject_id']]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                // Verify student exists and belongs to a class assigned to teacher
                $student = getOne('students', 'id = ?', [$attendance['student_id']]);
                if (!$student) {
                    $message = '<div class="alert alert-danger">Siswa tidak ditemukan.</div>';
                    break;
                }

                $classAssigned = false;
                foreach ($classes as $class) {
                    if ($class['id'] == $student['class_id']) {
                        $classAssigned = true;
                        break;
                    }
                }
                if (!$classAssigned) {
                    $message = '<div class="alert alert-danger">Siswa tidak termasuk dalam kelas yang Anda ajar.</div>';
                    break;
                }

                // Delete attendance
                if (delete('attendances', 'id = ?', [$attendanceId])) {
                    $message = '<div class="alert alert-success">Kehadiran berhasil dihapus!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Gagal menghapus kehadiran.</div>';
                }
                break;

            case 'edit_grade':
                update('grades', [
                    'score' => $_POST['score'],
                    'assessment_type' => $_POST['assessment_type']
                ], 'id = ?', [$_POST['grade_id']]);
                $message = '<div class="alert alert-success">Nilai berhasil diperbarui!</div>';
                break;

            case 'delete_grade':
                // Validate grade_id
                $gradeId = (int)($_POST['grade_id'] ?? 0);
                if ($gradeId <= 0) {
                    $message = '<div class="alert alert-danger">ID nilai tidak valid.</div>';
                    break;
                }

                // Get grade record
                $grade = getOne('grades', 'id = ?', [$gradeId]);
                if (!$grade) {
                    $message = '<div class="alert alert-danger">Record nilai tidak ditemukan.</div>';
                    break;
                }

                // Validate teacher has permission for the subject
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $grade['subject_id']]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                // Verify student exists and belongs to a class assigned to teacher
                $student = getOne('students', 'id = ?', [$grade['student_id']]);
                if (!$student) {
                    $message = '<div class="alert alert-danger">Siswa tidak ditemukan.</div>';
                    break;
                }

                $classAssigned = false;
                foreach ($classes as $class) {
                    if ($class['id'] == $student['class_id']) {
                        $classAssigned = true;
                        break;
                    }
                }
                if (!$classAssigned) {
                    $message = '<div class="alert alert-danger">Siswa tidak termasuk dalam kelas yang Anda ajar.</div>';
                    break;
                }

                // Delete grade
                if (delete('grades', 'id = ?', [$gradeId])) {
                    $message = '<div class="alert alert-success">Nilai berhasil dihapus!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Gagal menghapus nilai.</div>';
                }
                break;

            case 'bulk_delete_attendance':
                $attendanceIds = json_decode($_POST['attendance_ids'] ?? '[]', true);
                if (empty($attendanceIds) || !is_array($attendanceIds)) {
                    $message = '<div class="alert alert-danger">ID kehadiran tidak valid.</div>';
                    break;
                }

                $deleted = 0;
                $errors = [];

                foreach ($attendanceIds as $attendanceId) {
                    $attendanceId = (int)$attendanceId;
                    if ($attendanceId <= 0) {
                        $errors[] = "ID kehadiran tidak valid: $attendanceId";
                        continue;
                    }

                    // Get attendance record
                    $attendance = getOne('attendances', 'id = ?', [$attendanceId]);
                    if (!$attendance) {
                        $errors[] = "Record kehadiran tidak ditemukan: $attendanceId";
                        continue;
                    }

                    // Validate teacher has permission for the subject
                    $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $attendance['subject_id']]);
                    if (!$teacherSubject) {
                        $errors[] = "Tidak memiliki izin untuk mata pelajaran: $attendanceId";
                        continue;
                    }

                    // Verify student exists and belongs to a class assigned to teacher
                    $student = getOne('students', 'id = ?', [$attendance['student_id']]);
                    if (!$student) {
                        $errors[] = "Siswa tidak ditemukan: $attendanceId";
                        continue;
                    }

                    $classAssigned = false;
                    foreach ($classes as $class) {
                        if ($class['id'] == $student['class_id']) {
                            $classAssigned = true;
                            break;
                        }
                    }
                    if (!$classAssigned) {
                        $errors[] = "Siswa tidak termasuk dalam kelas yang Anda ajar: $attendanceId";
                        continue;
                    }

                    // Delete attendance
                    if (delete('attendances', 'id = ?', [$attendanceId])) {
                        $deleted++;
                    } else {
                        $errors[] = "Gagal menghapus kehadiran: $attendanceId";
                    }
                }

                $successMsg = '<div class="alert alert-success">';
                $successMsg .= '<strong>Bulk delete kehadiran selesai!</strong><br>';
                $successMsg .= 'Dihapus: ' . $deleted . '<br>';
                if (!empty($errors)) {
                    $successMsg .= '<br><strong>Errors:</strong><br>' . implode('<br>', $errors);
                }
                $successMsg .= '</div>';
                $message = $successMsg;
                break;

            case 'bulk_delete_grades':
                $gradeIds = json_decode($_POST['grade_ids'] ?? '[]', true);
                if (empty($gradeIds) || !is_array($gradeIds)) {
                    $message = '<div class="alert alert-danger">ID nilai tidak valid.</div>';
                    break;
                }

                $deleted = 0;
                $errors = [];

                foreach ($gradeIds as $gradeId) {
                    $gradeId = (int)$gradeId;
                    if ($gradeId <= 0) {
                        $errors[] = "ID nilai tidak valid: $gradeId";
                        continue;
                    }

                    // Get grade record
                    $grade = getOne('grades', 'id = ?', [$gradeId]);
                    if (!$grade) {
                        $errors[] = "Record nilai tidak ditemukan: $gradeId";
                        continue;
                    }

                    // Validate teacher has permission for the subject
                    $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $grade['subject_id']]);
                    if (!$teacherSubject) {
                        $errors[] = "Tidak memiliki izin untuk mata pelajaran: $gradeId";
                        continue;
                    }

                    // Verify student exists and belongs to a class assigned to teacher
                    $student = getOne('students', 'id = ?', [$grade['student_id']]);
                    if (!$student) {
                        $errors[] = "Siswa tidak ditemukan: $gradeId";
                        continue;
                    }

                    $classAssigned = false;
                    foreach ($classes as $class) {
                        if ($class['id'] == $student['class_id']) {
                            $classAssigned = true;
                            break;
                        }
                    }
                    if (!$classAssigned) {
                        $errors[] = "Siswa tidak termasuk dalam kelas yang Anda ajar: $gradeId";
                        continue;
                    }

                    // Delete grade
                    if (delete('grades', 'id = ?', [$gradeId])) {
                        $deleted++;
                    } else {
                        $errors[] = "Gagal menghapus nilai: $gradeId";
                    }
                }

                $successMsg = '<div class="alert alert-success">';
                $successMsg .= '<strong>Bulk delete nilai selesai!</strong><br>';
                $successMsg .= 'Dihapus: ' . $deleted . '<br>';
                if (!empty($errors)) {
                    $successMsg .= '<br><strong>Errors:</strong><br>' . implode('<br>', $errors);
                }
                $successMsg .= '</div>';
                $message = $successMsg;
                break;

            case 'add_individual_attendance':
                // Validate required fields
                $studentId = (int)($_POST['student_id'] ?? 0);
                $subjectId = (int)($_POST['subject_id'] ?? 0);
                $date = trim($_POST['date'] ?? '');
                $status = $_POST['status'] ?? '';
                $notes = trim($_POST['notes'] ?? '');

                if (!$studentId) {
                    $message = '<div class="alert alert-danger">ID siswa tidak valid.</div>';
                    break;
                }

                if (!$subjectId) {
                    $message = '<div class="alert alert-danger">ID mata pelajaran tidak valid.</div>';
                    break;
                }

                if (empty($date) || !strtotime($date)) {
                    $message = '<div class="alert alert-danger">Tanggal tidak valid.</div>';
                    break;
                }

                // Validate date is not in future
                if (strtotime($date) > time()) {
                    $message = '<div class="alert alert-danger">Tanggal tidak boleh di masa depan.</div>';
                    break;
                }

                // Validate status
                $validStatuses = ['hadir', 'sakit', 'izin', 'alpa'];
                if (!in_array($status, $validStatuses)) {
                    $message = '<div class="alert alert-danger">Status absensi tidak valid.</div>';
                    break;
                }

                // Validate teacher has permission for the subject
                $teacherSubject = getOne('teacher_subjects', 'teacher_id = ? AND subject_id = ?', [$teacher['id'], $subjectId]);
                if (!$teacherSubject) {
                    $message = '<div class="alert alert-danger">Anda tidak memiliki izin untuk mata pelajaran ini.</div>';
                    break;
                }

                // Verify student exists and belongs to a class assigned to teacher
                $student = getOne('students', 'id = ?', [$studentId]);
                if (!$student) {
                    $message = '<div class="alert alert-danger">Siswa tidak ditemukan.</div>';
                    break;
                }

                $classAssigned = false;
                foreach ($classes as $class) {
                    if ($class['id'] == $student['class_id']) {
                        $classAssigned = true;
                        break;
                    }
                }
                if (!$classAssigned) {
                    $message = '<div class="alert alert-danger">Siswa tidak termasuk dalam kelas yang Anda ajar.</div>';
                    break;
                }

                // Get student's class_id
                $student = getOne('students', 'id = ?', [$studentId]);
                if (!$student) {
                    $message = '<div class="alert alert-danger">Data siswa tidak ditemukan.</div>';
                    break;
                }
                $classId = $student['class_id'];

                // Prevent duplicate entries for same student/subject/class/date
                $existing = getOne('attendances', 'student_id = ? AND subject_id = ? AND class_id = ? AND date = ?', [$studentId, $subjectId, $classId, $date]);
                if ($existing) {
                    $message = '<div class="alert alert-warning">Data absensi untuk siswa ini pada tanggal tersebut sudah ada.</div>';
                    break;
                }

                // Insert new attendance record
                if (insert('attendances', [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'class_id' => $classId,
                    'date' => $date,
                    'status' => $status,
                    'notes' => $notes
                ])) {
                    $message = '<div class="alert alert-success">Absensi individu berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Gagal menambahkan absensi individu.</div>';
                }
                break;

            case 'save_report_notes':
                update('report_cards', [
                    'homeroom_teacher_notes' => $_POST['notes']
                ], 'id = ?', [$_POST['report_id']]);
                $message = '<div class="alert alert-success">Catatan rapor berhasil diperbarui!</div>';
                break;
        }
    }
}

// Get teacher's classes and subjects
$teacherSubjects = getAll('teacher_subjects', 'teacher_id = ?', [$teacher['id']]);
$classIds = array_unique(array_column($teacherSubjects, 'class_id'));
$classes = [];
foreach ($classIds as $classId) {
    $classes[] = getOne('classes', 'id = ?', [$classId]);
}

// Build subjects list assigned to this teacher (fallback to all subjects)
$subjects = [];
$subjectIds = array_unique(array_column($teacherSubjects, 'subject_id'));
if (!empty($subjectIds)) {
    foreach ($subjectIds as $sid) {
        $s = getOne('subjects', 'id = ?', [$sid]);
        if ($s) $subjects[] = $s;
    }
} else {
    $subjects = getAll('subjects');
}

// Get homeroom class
$homeroomClass = getOne('classes', 'homeroom_teacher_id = ?', [$teacher['id']]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guru Dashboard - SIAP-Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease, visibility 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
            opacity: 0;
            visibility: hidden;
        }
        .sidebar.show {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: 12px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 8px;
            margin: 2px 10px;
            font-size: 16px;
            min-height: 48px;
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,.1);
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            color: white;
            background: #495057;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }
        .content-wrapper {
            margin-left: 250px;
            transition: margin-left 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            min-height: 100vh;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 999;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            transition: opacity 0.3s ease, visibility 0.3s ease;
            visibility: hidden;
            opacity: 0;
            touch-action: none;
        }
        .sidebar-overlay.show {
            display: block;
            visibility: visible;
            opacity: 1;
        }
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #343a40;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 18px;
            min-width: 48px;
            min-height: 48px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.9;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            aria-label: "Toggle sidebar";
            role: "button";
        }
        .mobile-toggle:hover {
            background: #495057;
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
            opacity: 1;
        }
        .mobile-toggle:active {
            transform: scale(0.95);
        }
        .mobile-toggle:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }

        /* Enhanced responsive breakpoints */
        @media (max-width: 1199px) {
            .content-wrapper {
                margin-left: 0;
            }
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 4px 0 20px rgba(0,0,0,0.2);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .mobile-toggle {
                display: flex;
            }
        }

        @media (min-width: 1200px) {
            .sidebar {
                transform: translateX(0);
            }
            .content-wrapper {
                margin-left: 250px;
            }
        }

        /* 992px breakpoint for better tablet experience */
        @media (max-width: 991px) {
            .container-fluid {
                padding-left: 15px;
                padding-right: 15px;
            }
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .table-responsive .table {
                margin-bottom: 0;
                font-size: 14px;
                line-height: 1.4;
            }
            .table-responsive .table th,
            .table-responsive .table td {
                padding: 12px 8px;
                vertical-align: middle;
            }
            .btn {
                min-height: 48px;
                font-size: 16px;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .form-control {
                font-size: 16px;
                min-height: 48px;
                border-radius: 8px;
                padding: 12px 16px;
                border: 2px solid #dee2e6;
                transition: border-color 0.2s ease;
            }
            .form-control:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            }
            .modal-dialog {
                margin: 10px;
                max-width: calc(100vw - 20px);
            }
            .row.mb-4 .col-md-3,
            .row.mb-4 .col-md-4,
            .row.mb-4 .col-md-2 {
                margin-bottom: 20px;
            }
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 3px;
                font-size: 13px;
            }
            .calendar-day {
                padding: 8px;
                min-height: 48px;
                font-size: 12px;
                border-radius: 6px;
            }
            .calendar-header {
                padding: 8px;
                font-size: 13px;
                font-weight: 600;
            }
            .card {
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
                border: none;
            }
            .card-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 12px 12px 0 0 !important;
                padding: 16px;
                font-weight: 600;
            }
        }

        /* Enhanced 992px breakpoint for tablets */
        @media (max-width: 992px) and (min-width: 768px) {
            .sidebar {
                width: 220px;
            }
            .content-wrapper {
                margin-left: 220px;
            }
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 4px;
                font-size: 14px;
            }
            .calendar-day {
                padding: 10px 6px;
                min-height: 52px;
                font-size: 13px;
                border-radius: 8px;
            }
            .calendar-header {
                padding: 10px 6px;
                font-size: 14px;
            }
            .table-responsive .table th,
            .table-responsive .table td {
                padding: 14px 10px;
                font-size: 15px;
            }
            .btn {
                min-height: 50px;
                font-size: 17px;
                padding: 14px 26px;
            }
            .form-control {
                min-height: 50px;
                font-size: 17px;
                padding: 14px 18px;
            }
            .form-select {
                min-height: 50px;
                font-size: 17px;
                padding: 14px 18px;
            }
        }

        /* 768px breakpoint for tablets */
        @media (max-width: 767px) {
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            .d-flex.justify-content-between.align-items-center.mb-4 h2 {
                text-align: center;
                margin-bottom: 0;
                font-size: 1.8rem;
            }
            .row.mb-4 .col-md-3,
            .row.mb-4 .col-md-4,
            .row.mb-4 .col-md-2 {
                margin-bottom: 20px;
            }
            .d-flex.justify-content-between.align-items-center.mb-3 {
                flex-direction: column;
                gap: 15px;
            }
            .text-muted.small {
                text-align: center;
                font-size: 14px;
            }
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 2px;
                font-size: 12px;
            }
            .calendar-day {
                padding: 6px;
                min-height: 44px;
                font-size: 11px;
                border-radius: 4px;
            }
            .calendar-header {
                padding: 6px;
                font-size: 12px;
            }
            .form-row {
                margin-left: -8px;
                margin-right: -8px;
            }
            .form-row > .col,
            .form-row > [class*="col-"] {
                padding-left: 8px;
                padding-right: 8px;
                margin-bottom: 16px;
            }
            .alert {
                padding: 16px;
                border-radius: 8px;
                font-size: 15px;
            }
            .badge {
                font-size: 12px;
                padding: 6px 12px;
            }
        }

        /* 576px breakpoint for small mobile */
        @media (max-width: 575px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 1px;
            }
            .calendar-day {
                padding: 4px;
                min-height: 40px;
                font-size: 10px;
                border-radius: 3px;
            }
            .calendar-header {
                padding: 4px;
                font-size: 11px;
            }
            .table-responsive {
                font-size: 13px;
                border-radius: 6px;
            }
            .table-responsive .table th,
            .table-responsive .table td {
                padding: 8px 4px;
                font-size: 13px;
            }
            .btn {
                min-height: 50px;
                font-size: 16px;
                padding: 14px 20px;
                border-radius: 8px;
                width: 100%;
                margin-bottom: 8px;
            }
            .btn-sm {
                min-height: 44px;
                font-size: 14px;
                padding: 10px 16px;
            }
            .form-control {
                min-height: 50px;
                font-size: 16px;
                padding: 14px 16px;
                border-radius: 8px;
            }
            .form-select {
                min-height: 50px;
                font-size: 16px;
                padding: 14px 16px;
            }
            body {
                font-size: 16px;
                line-height: 1.6;
            }
            h2 {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            h3 {
                font-size: 1.6rem;
                margin-bottom: 0.8rem;
            }
            .alert {
                font-size: 14px;
                padding: 12px;
                margin-bottom: 16px;
            }
            .card-header {
                padding: 12px;
                font-size: 16px;
            }
            .modal-dialog {
                margin: 5px;
                max-width: calc(100vw - 10px);
            }
            .mobile-toggle {
                top: 10px;
                left: 10px;
                padding: 10px;
                min-width: 44px;
                min-height: 44px;
                font-size: 16px;
            }
        }

        /* Enhanced table responsiveness with scroll indicators */
        .table-responsive {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-responsive::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0.9), transparent);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        .table-responsive::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(to left, rgba(255,255,255,0.9), transparent);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        .table-responsive.has-scroll-left::before {
            opacity: 1;
        }
        .table-responsive.has-scroll-right::after {
            opacity: 1;
        }

        /* Sticky table headers with enhanced styling */
        .table-responsive .table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        /* Better form layouts with improved spacing */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -8px;
            margin-left: -8px;
        }
        .form-row > .col,
        .form-row > [class*="col-"] {
            padding-right: 8px;
            padding-left: 8px;
            margin-bottom: 16px;
        }

        /* Enhanced calendar with better responsiveness */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            max-width: 100%;
        }
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            border-radius: 8px;
            font-size: 14px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .calendar-day {
            padding: 8px 4px;
            text-align: center;
            border: 2px solid #dee2e6;
            min-height: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
        }
        .calendar-day:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .calendar-day:active {
            transform: scale(0.98);
        }
        .calendar-day.good {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-color: #c3e6cb;
        }
        .calendar-day.medium {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-color: #ffeaa7;
        }
        .calendar-day.poor {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-color: #f5c6cb;
        }
        .calendar-day.no-data {
            background: linear-gradient(135deg, #e9ecef 0%, #d6d8db 100%);
            color: #383d41;
            border-color: #d6d8db;
        }
        .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }
        .calendar-day.empty:hover {
            transform: none;
            box-shadow: none;
        }

        /* General improvements with better typography */
        body {
            font-size: 16px;
            line-height: 1.6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
        }
        .content-wrapper {
            background: #f8f9fa;
        }
        h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #343a40;
            margin-bottom: 1.5rem;
        }
        h3 {
            font-size: 1.6rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
        }
        .alert {
            font-size: 1rem;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .badge {
            font-size: 0.875rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* Touch-friendly elements with better feedback */
        button, .btn, input, select, textarea, .nav-link {
            touch-action: manipulation;
            transition: all 0.2s ease;
        }
        button:active, .btn:active, .nav-link:active {
            transform: scale(0.98);
        }

        /* Loading states with better styling */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            border-radius: 8px;
            backdrop-filter: blur(2px);
        }

        /* Enhanced navigation and button accessibility */
        .btn:focus, .form-control:focus, .form-select:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }

        /* Better spacing for mobile forms */
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
        }

        /* Improved modal responsiveness */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            border-radius: 12px 12px 0 0;
            padding: 20px;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 20px;
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-sticky">
                <div class="p-3">
                    <h5><i class="fas fa-chalkboard-teacher"></i> SIAP-Siswa</h5>
                    <p class="mb-4">Guru Dashboard</p>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#attendance" onclick="showTab('attendance')">
                            <i class="fas fa-clipboard-check"></i> <span class="nav-text">Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#attendance-summary" onclick="showTab('attendance-summary')">
                            <i class="fas fa-chart-bar"></i> <span class="nav-text">Attendance Summary</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#attendance-calendar" onclick="showTab('attendance-calendar')">
                            <i class="fas fa-calendar-alt"></i> <span class="nav-text">Attendance Calendar</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#grades" onclick="showTab('grades')">
                            <i class="fas fa-book-open"></i> <span class="nav-text">Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#classes" onclick="showTab('classes')">
                            <i class="fas fa-school"></i> <span class="nav-text">Classes & Subjects</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#analysis" onclick="showTab('analysis')">
                            <i class="fas fa-chart-bar"></i> <span class="nav-text">Analysis</span>
                        </a>
                    </li>
                    <?php if ($homeroomClass): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#reports" onclick="showTab('reports')">
                            <i class="fas fa-file-document"></i> <span class="nav-text">Report Cards</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="content-wrapper flex-fill">
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($teacher['full_name']); ?>!</h2>
                </div>

                <?php echo $message; ?>

                <!-- Attendance Summary Tab -->
                <div id="attendance-summary-tab" class="tab-content" style="display: none;">
                    <h3>Ringkasan Absensi Siswa</h3>

                    <!-- Low Attendance Alerts -->
                    <div id="low-attendance-alerts" class="mb-4" style="display: none;">
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> Peringatan Absensi Rendah</h5>
                            <div id="low-attendance-list"></div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Kelas</label>
                            <select class="form-control" id="summary-class" onchange="loadAttendanceSummary()">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select class="form-control" id="summary-subject" onchange="loadAttendanceSummary()">
                                <option value="">Semua Mata Pelajaran</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Dari</label>
                            <input type="date" class="form-control" id="summary-date-from" onchange="loadAttendanceSummary()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="summary-date-to" onchange="loadAttendanceSummary()">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button class="btn btn-outline-primary" onclick="loadAttendanceSummary()">
                                <i class="fas fa-search"></i> Load Summary
                            </button>
                            <button class="btn btn-outline-success" onclick="exportAttendanceSummary()">
                                <i class="fas fa-file-excel"></i> Export Summary
                            </button>
                        </div>
                        <div id="summary-loading" style="display: none;">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div> Memuat...
                        </div>
                    </div>

                    <!-- Summary Chart -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Distribusi Status Absensi</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="attendanceSummaryChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Statistik Absensi</h5>
                                </div>
                                <div class="card-body">
                                    <div id="attendance-stats"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Total Hari</th>
                                    <th>Hadir</th>
                                    <th>Sakit</th>
                                    <th>Izin</th>
                                    <th>Alpa</th>
                                    <th>Persentase Kehadiran</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-summary-body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Attendance Calendar Tab -->
                <div id="attendance-calendar-tab" class="tab-content" style="display: none;">
                    <h3>Kalender Absensi</h3>

                    <!-- Calendar Filters -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Kelas</label>
                            <select class="form-control" id="calendar-class" onchange="loadAttendanceCalendar()">
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mata Pelajaran</label>
                            <select class="form-control" id="calendar-subject" onchange="loadAttendanceCalendar()">
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bulan</label>
                            <input type="month" class="form-control" id="calendar-month" onchange="loadAttendanceCalendar()" value="<?php echo date('Y-m'); ?>">
                        </div>
                    </div>

                    <!-- Calendar Navigation -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button class="btn btn-outline-secondary" onclick="previousMonth()">
                                <i class="fas fa-chevron-left"></i> Bulan Sebelumnya
                            </button>
                            <button class="btn btn-outline-secondary" onclick="nextMonth()">
                                <i class="fas fa-chevron-right"></i> Bulan Selanjutnya
                            </button>
                        </div>
                        <div id="calendar-loading" style="display: none;">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div> Memuat...
                        </div>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="card">
                        <div class="card-header">
                            <h5 id="calendar-title"></h5>
                        </div>
                        <div class="card-body">
                            <div id="attendance-calendar-grid" class="calendar-grid">
                                <!-- Calendar will be generated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="mt-3">
                        <h6>Legenda:</h6>
                        <div class="d-flex flex-wrap">
                            <div class="me-3 mb-2">
                                <span class="badge bg-success">Hijau</span> - Kehadiran Baik (>80%)
                            </div>
                            <div class="me-3 mb-2">
                                <span class="badge bg-warning">Kuning</span> - Kehadiran Sedang (60-80%)
                            </div>
                            <div class="me-3 mb-2">
                                <span class="badge bg-danger">Merah</span> - Kehadiran Rendah (<60%)
                            </div>
                            <div class="me-3 mb-2">
                                <span class="badge bg-light text-dark">Abu-abu</span> - Tidak Ada Data
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Tab -->
                <div id="attendance-tab" class="tab-content">
                    <h3>Input Absensi Siswa</h3>

                    <?php if (empty($classes)): ?>
                        <div class="alert alert-warning">Anda belum ditugaskan ke kelas manapun.</div>
                    <?php endif; ?>
                    <?php if (empty($subjects)): ?>
                        <div class="alert alert-warning">Tidak ada mata pelajaran yang terdaftar untuk Anda.</div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Pilih Kelas</label>
                            <select class="form-control" id="attendance-class" onchange="onClassChange()">
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pilih Mata Pelajaran</label>
                            <select class="form-control" id="attendance-subject" onchange="onSubjectChange()">
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="attendance-date" value="<?php echo date('Y-m-d'); ?>" onchange="onDateChange()">
                        </div>
                    </div>

                    <div id="attendance-table" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="openAddIndividualAttendanceModal()">
                                    <i class="fas fa-plus"></i> Tambah Absensi Individu
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="markAllPresent()">
                                    <i class="fas fa-check-circle"></i> Semua Hadir
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm me-2" onclick="markAllAbsent()">
                                    <i class="fas fa-times-circle"></i> Semua Alpa
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="resetAll()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                            <div class="text-muted small">
                                Total Siswa: <span id="total-students">0</span>
                            </div>
                        </div>

                        <form id="attendance-form" method="POST">
                            <input type="hidden" name="action" value="save_attendance">
                            <input type="hidden" name="attendance_data" id="attendance-data">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>NIS</th>
                                            <th>Nama Siswa</th>
                                            <th>Status</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendance-students">
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle"></i> Pastikan semua data sudah benar sebelum menyimpan
                                </div>
                                <button type="button" class="btn btn-success btn-lg" onclick="saveAttendance()">
                                    <i class="fas fa-save"></i> Simpan Absensi
                                </button>
                            </div>
                        </form>
                    </div>

                    <h4 class="mt-5">Riwayat Absensi</h4>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Filter Kelas</label>
                            <select class="form-control" id="history-attendance-class" onchange="loadAttendanceHistory()">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter Mata Pelajaran</label>
                            <select class="form-control" id="history-attendance-subject" onchange="loadAttendanceHistory()">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="history-attendance-status" onchange="loadAttendanceHistory()">
                                <option value="">All Status</option>
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpa">Alpa</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Dari</label>
                            <input type="date" class="form-control" id="history-attendance-date-from" onchange="loadAttendanceHistory()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="history-attendance-date-to" onchange="loadAttendanceHistory()">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="loadAttendanceHistory()">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button class="btn btn-outline-success me-2" onclick="exportAttendanceToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn btn-outline-danger me-2" onclick="bulkDeleteAttendance()" id="bulk-delete-attendance-btn" style="display: none;">
                                <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selected-count">0</span>)
                            </button>
                        </div>
                        <div id="attendance-loading" style="display: none;">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div> Memuat...
                        </div>
                    </div>

                    <div id="attendance-history-table" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-attendance" onchange="toggleSelectAllAttendance()"></th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-history-body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grades Tab -->
                <div id="grades-tab" class="tab-content" style="display: none;">
                    <h3>Input Grades</h3>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Pilih Kelas</label>
                            <select class="form-control" id="grades-class" onchange="loadStudentsForGrades()">
                                <option value="">Choose Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Pilih Mata Pelajaran</label>
                            <select class="form-control" id="grades-subject">
                                <option value="">Choose Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipe Penilaian</label>
                            <select class="form-control" id="assessment-type">
                                <option value="tugas">Tugas</option>
                                <option value="uts">UTS</option>
                                <option value="uas">UAS</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="grades-date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div id="grades-table" style="display: none;">
                        <form id="grades-form" method="POST">
                            <input type="hidden" name="action" value="save_grades">
                            <input type="hidden" name="grade_data" id="grade-data">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>NIS</th>
                                            <th>Name</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody id="grades-students">
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-success mt-3" onclick="saveGrades()">
                                <i class="fas fa-save"></i> Simpan Nilai
                            </button>
                        </form>
                    </div>

                    <h4 class="mt-5">Riwayat Nilai</h4>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Filter Kelas</label>
                            <select class="form-control" id="history-grades-class" onchange="loadGradesHistory()">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter Mata Pelajaran</label>
                            <select class="form-control" id="history-grades-subject" onchange="loadGradesHistory()">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipe Penilaian</label>
                            <select class="form-control" id="history-assessment-type" onchange="loadGradesHistory()">
                                <option value="">All Types</option>
                                <option value="tugas">Tugas</option>
                                <option value="uts">UTS</option>
                                <option value="uas">UAS</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nilai Min</label>
                            <input type="number" class="form-control" id="history-grades-score-min" onchange="loadGradesHistory()" min="0" max="100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nilai Max</label>
                            <input type="number" class="form-control" id="history-grades-score-max" onchange="loadGradesHistory()" min="0" max="100" value="100">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="loadGradesHistory()">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button class="btn btn-outline-success me-2" onclick="exportGradesToExcel()">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn btn-outline-danger me-2" onclick="bulkDeleteGrades()" id="bulk-delete-grades-btn" style="display: none;">
                                <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selected-grades-count">0</span>)
                            </button>
                        </div>
                        <div id="grades-loading" style="display: none;">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div> Memuat...
                        </div>
                    </div>

                    <div id="grades-history-table" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-grades" onchange="toggleSelectAllGrades()"></th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Assessment Type</th>
                                    <th>Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grades-history-body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Classes & Subjects Tab -->
                <div id="classes-tab" class="tab-content" style="display: none;">
                    <h3>Classes & Subjects</h3>

                    <div class="row">
                        <div class="col-md-6">
                            <h4>My Classes</h4>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Academic Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['academic_year'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4>My Subjects</h4>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Subject Name</th>
                                            <th>Class</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($teacherSubjects as $ts):
                                            $subject = getOne('subjects', 'id = ?', [$ts['subject_id']]);
                                            $class = getOne('classes', 'id = ?', [$ts['class_id']]);
                                            if ($subject && $class):
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                            </tr>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analysis Tab -->
                <div id="analysis-tab" class="tab-content" style="display: none;">
                    <h3>Analysis</h3>

                    <div class="row">
                        <div class="col-md-6">
                            <h4>Attendance Summary</h4>
                            <canvas id="attendanceChart" width="400" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <h4>Grades Distribution</h4>
                            <canvas id="gradesChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <script>
                        // Load analysis data on tab show
                        document.addEventListener('DOMContentLoaded', function() {
                            // Attendance Chart
                            fetch('api.php/attendances?filter=teacher_id,eq,<?php echo $teacher['id']; ?>')
                                .then(response => response.json())
                                .then(data => {
                                    const attendances = data.attendances.records;
                                    const statusCount = { hadir: 0, sakit: 0, izin: 0, alpa: 0 };
                                    attendances.forEach(att => {
                                        statusCount[att.status] = (statusCount[att.status] || 0) + 1;
                                    });

                                    const ctx = document.getElementById('attendanceChart').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'pie',
                                        data: {
                                            labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
                                            datasets: [{
                                                data: [statusCount.hadir, statusCount.sakit, statusCount.izin, statusCount.alpa],
                                                backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
                                            }]
                                        }
                                    });
                                });

                            // Grades Chart
                            fetch('api.php/grades?filter=teacher_id,eq,<?php echo $teacher['id']; ?>')
                                .then(response => response.json())
                                .then(data => {
                                    const grades = data.grades.records;
                                    const scoreRanges = { '0-50': 0, '51-70': 0, '71-85': 0, '86-100': 0 };
                                    grades.forEach(grade => {
                                        const score = grade.score;
                                        if (score <= 50) scoreRanges['0-50']++;
                                        else if (score <= 70) scoreRanges['51-70']++;
                                        else if (score <= 85) scoreRanges['71-85']++;
                                        else scoreRanges['86-100']++;
                                    });

                                    const ctx = document.getElementById('gradesChart').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: Object.keys(scoreRanges),
                                            datasets: [{
                                                label: 'Number of Grades',
                                                data: Object.values(scoreRanges),
                                                backgroundColor: '#007bff'
                                            }]
                                        }
                                    });
                                });
                        });
                    </script>
                </div>

                <!-- Report Cards Tab (Homeroom Teacher) -->
                <?php if ($homeroomClass): ?>
                <div id="reports-tab" class="tab-content" style="display: none;">
                    <h3>Report Cards - <?php echo htmlspecialchars($homeroomClass['class_name']); ?></h3>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Semester</th>
                                    <th>Academic Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $students = getAll('students', 'class_id = ?', [$homeroomClass['id']]);
                                foreach ($students as $student):
                                    $report = getOne('report_cards', 'student_id = ?', [$student['id']]);
                                    if ($report):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($report['academic_year']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editReportNotes(<?php echo $report['id']; ?>, '<?php echo addslashes($report['homeroom_teacher_notes'] ?? ''); ?>')">
                                                Edit Notes
                                            </button>
                                        </td>
                                    </tr>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Edit Report Notes Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Report Card Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_report_notes">
                        <input type="hidden" name="report_id" id="report-id">
                        <div class="mb-3">
                            <label class="form-label">Homeroom Teacher Notes</label>
                            <textarea class="form-control" name="notes" id="report-notes" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_attendance">
                        <input type="hidden" name="attendance_id" id="edit-attendance-id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit-attendance-status">
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpa">Alpa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes" id="edit-attendance-notes">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Grade Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Grade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_grade">
                        <input type="hidden" name="grade_id" id="edit-grade-id">
                        <div class="mb-3">
                            <label class="form-label">Score</label>
                            <input type="number" class="form-control" name="score" id="edit-grade-score" min="0" max="100" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assessment Type</label>
                            <select class="form-control" name="assessment_type" id="edit-grade-assessment-type">
                                <option value="tugas">Tugas</option>
                                <option value="uts">UTS</option>
                                <option value="uas">UAS</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let attendanceStudents = [];
        let gradesStudents = [];

        function showTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.style.display = 'none');
            document.getElementById(tabName + '-tab').style.display = 'block';

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

        function onClassChange() {
            loadStudentsForAttendance();
        }

        function onSubjectChange() {
            // Optional: could load if needed, but currently handled in loadStudentsForAttendance
        }

        function onDateChange() {
            loadStudentsForAttendance();
        }

        function markAllPresent() {
            attendanceStudents.forEach(student => {
                const statusSelect = document.getElementById(`status-${student.id}`);
                if (statusSelect) statusSelect.value = 'hadir';
            });
        }

        function markAllAbsent() {
            attendanceStudents.forEach(student => {
                const statusSelect = document.getElementById(`status-${student.id}`);
                if (statusSelect) statusSelect.value = 'alpa';
            });
        }

        function resetAll() {
            attendanceStudents.forEach(student => {
                const statusSelect = document.getElementById(`status-${student.id}`);
                const notesInput = document.getElementById(`notes-${student.id}`);
                if (statusSelect) statusSelect.value = 'hadir';
                if (notesInput) notesInput.value = '';
            });
        }

        async function loadStudentsForAttendance() {
            const classId = document.getElementById('attendance-class').value;
            const subjectId = document.getElementById('attendance-subject').value;
            const date = document.getElementById('attendance-date').value;

            // Client-side validation
            if (!classId) {
                alert('Silakan pilih kelas terlebih dahulu.');
                return;
            }
            if (!subjectId) {
                alert('Silakan pilih mata pelajaran terlebih dahulu.');
                return;
            }
            if (!date) {
                alert('Silakan pilih tanggal absensi.');
                return;
            }

            // Validate date is not in future
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate > today) {
                alert('Tanggal absensi tidak boleh di masa depan.');
                return;
            }

            try {
                const response = await fetch(`api.php/students?filter=class_id,eq,${classId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                attendanceStudents = data.students.records;

                const tbody = document.getElementById('attendance-students');
                tbody.innerHTML = '';

                attendanceStudents.forEach(student => {
                    const row = `
                        <tr>
                            <td>${student.nis}</td>
                            <td>${student.full_name}</td>
                            <td>
                                <select class="form-control" id="status-${student.id}">
                                    <option value="hadir">Hadir</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="izin">Izin</option>
                                    <option value="alpa">Alpa</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control" id="notes-${student.id}" placeholder="Notes" maxlength="255">
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });

                document.getElementById('attendance-table').style.display = 'block';
            } catch (error) {
                console.error('Error loading students:', error);
                alert('Gagal memuat data siswa. Silakan coba lagi.');
            }
        }

        async function loadStudentsForGrades() {
            const classId = document.getElementById('grades-class').value;
            const date = document.getElementById('grades-date').value;

            // Client-side validation - only require classId and date
            if (!classId) {
                alert('Silakan pilih kelas terlebih dahulu.');
                return;
            }
            if (!date) {
                alert('Silakan pilih tanggal nilai.');
                return;
            }

            // Validate date is not in future
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate > today) {
                alert('Tanggal nilai tidak boleh di masa depan.');
                return;
            }

            try {
                const response = await fetch(`api.php/students?filter=class_id,eq,${classId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                gradesStudents = data.students.records;

                const tbody = document.getElementById('grades-students');
                tbody.innerHTML = '';

                gradesStudents.forEach(student => {
                    const row = `
                        <tr>
                            <td>${student.nis}</td>
                            <td>${student.full_name}</td>
                            <td>
                                <input type="number" class="form-control" id="score-${student.id}" min="0" max="100" step="0.01" placeholder="0-100">
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });

                document.getElementById('grades-table').style.display = 'block';
            } catch (error) {
                console.error('Error loading students:', error);
                alert('Gagal memuat data siswa. Silakan coba lagi.');
            }
        }

        function saveAttendance() {
            const subjectId = document.getElementById('attendance-subject').value;
            const date = document.getElementById('attendance-date').value;

            const attendanceData = attendanceStudents.map(student => ({
                student_id: student.id,
                subject_id: subjectId,
                date: date,
                status: document.getElementById(`status-${student.id}`).value,
                notes: document.getElementById(`notes-${student.id}`).value
            }));

            document.getElementById('attendance-data').value = JSON.stringify(attendanceData);
            document.getElementById('attendance-form').submit();
        }

        function saveGrades() {
            const subjectId = document.getElementById('grades-subject').value;
            const assessmentType = document.getElementById('assessment-type').value;
            const date = document.getElementById('grades-date').value;

            const gradeData = gradesStudents
                .map(student => ({
                    student_id: student.id,
                    subject_id: subjectId,
                    assessment_type: assessmentType,
                    score: parseFloat(document.getElementById(`score-${student.id}`).value) || 0,
                    grade_date: date
                }))
                .filter(grade => grade.score > 0);

            document.getElementById('grade-data').value = JSON.stringify(gradeData);
            document.getElementById('grades-form').submit();
        }

        function editReportNotes(reportId, notes) {
            document.getElementById('report-id').value = reportId;
            document.getElementById('report-notes').value = notes;
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        async function loadAttendanceHistory() {
            const classId = document.getElementById('history-attendance-class').value;
            const subjectId = document.getElementById('history-attendance-subject').value;
            const date = document.getElementById('history-attendance-date').value;

            let url = 'api.php/attendances?';
            const filters = [];
            if (classId) filters.push(`filter=class_id,eq,${classId}`);
            if (subjectId) filters.push(`filter=subject_id,eq,${subjectId}`);
            if (date) filters.push(`filter=date,eq,${date}`);
            url += filters.join('&');

            const response = await fetch(url);
            const data = await response.json();
            const attendances = data.attendances.records;

            const tbody = document.getElementById('attendance-history-body');
            tbody.innerHTML = '';

            attendances.forEach(attendance => {
                const student = attendance.student || {};
                const subject = attendance.subject || {};
                const row = `
                    <tr>
                        <td><input type="checkbox" class="attendance-checkbox" value="${attendance.id}" onchange="updateBulkDeleteButton()"></td>
                        <td>${attendance.date}</td>
                        <td>${student.full_name || 'N/A'}</td>
                        <td>${subject.subject_name || 'N/A'}</td>
                        <td>${attendance.status}</td>
                        <td>${attendance.notes || ''}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editAttendance(${attendance.id}, '${attendance.status}', '${attendance.notes || ''}')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteAttendance(${attendance.id})">Delete</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        async function loadGradesHistory() {
            const classId = document.getElementById('history-grades-class').value;
            const subjectId = document.getElementById('history-grades-subject').value;
            const assessmentType = document.getElementById('history-assessment-type').value;
            const scoreMin = document.getElementById('history-grades-score-min').value;
            const scoreMax = document.getElementById('history-grades-score-max').value;

            let url = 'api.php/grades?';
            const filters = [];
            if (classId) filters.push(`filter=class_id,eq,${classId}`);
            if (subjectId) filters.push(`filter=subject_id,eq,${subjectId}`);
            if (assessmentType) filters.push(`filter=assessment_type,eq,${assessmentType}`);
            if (scoreMin) filters.push(`filter=score,gte,${scoreMin}`);
            if (scoreMax) filters.push(`filter=score,lte,${scoreMax}`);
            url += filters.join('&');

            const response = await fetch(url);
            const data = await response.json();
            const grades = data.grades.records;

            const tbody = document.getElementById('grades-history-body');
            tbody.innerHTML = '';

            grades.forEach(grade => {
                const student = grade.student || {};
                const subject = grade.subject || {};
                const row = `
                    <tr>
                        <td><input type="checkbox" class="grades-checkbox" value="${grade.id}" onchange="updateBulkDeleteGradesButton()"></td>
                        <td>${grade.grade_date}</td>
                        <td>${student.full_name || 'N/A'}</td>
                        <td>${subject.subject_name || 'N/A'}</td>
                        <td>${grade.assessment_type}</td>
                        <td>${grade.score}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editGrade(${grade.id}, ${grade.score}, '${grade.assessment_type}')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteGrade(${grade.id})">Delete</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function editAttendance(attendanceId, status, notes) {
            document.getElementById('edit-attendance-id').value = attendanceId;
            document.getElementById('edit-attendance-status').value = status;
            document.getElementById('edit-attendance-notes').value = notes;
            new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();
        }

        function deleteAttendance(attendanceId) {
            if (confirm('Apakah Anda yakin ingin menghapus kehadiran ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_attendance">
                    <input type="hidden" name="attendance_id" value="${attendanceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editGrade(gradeId, score, assessmentType) {
            document.getElementById('edit-grade-id').value = gradeId;
            document.getElementById('edit-grade-score').value = score;
            document.getElementById('edit-grade-assessment-type').value = assessmentType;
            new bootstrap.Modal(document.getElementById('editGradeModal')).show();
        }

        function deleteGrade(gradeId) {
            if (confirm('Apakah Anda yakin ingin menghapus nilai ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_grade">
                    <input type="hidden" name="grade_id" value="${gradeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateBulkDeleteButton() {
            const checkboxes = document.querySelectorAll('.attendance-checkbox:checked');
            const count = checkboxes.length;
            const btn = document.getElementById('bulk-delete-attendance-btn');
            const countSpan = document.getElementById('selected-count');
            if (count > 0) {
                btn.style.display = 'inline-block';
                countSpan.textContent = count;
            } else {
                btn.style.display = 'none';
            }
        }

        function toggleSelectAllAttendance() {
            const selectAll = document.getElementById('select-all-attendance');
            const checkboxes = document.querySelectorAll('.attendance-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkDeleteButton();
        }

        function bulkDeleteAttendance() {
            const checkboxes = document.querySelectorAll('.attendance-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Pilih setidaknya satu record untuk dihapus.');
                return;
            }
            if (!confirm(`Apakah Anda yakin ingin menghapus ${checkboxes.length} record kehadiran?`)) {
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value);
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="bulk_delete_attendance">
                <input type="hidden" name="attendance_ids" value="${JSON.stringify(ids)}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function updateBulkDeleteGradesButton() {
            const checkboxes = document.querySelectorAll('.grades-checkbox:checked');
            const count = checkboxes.length;
            const btn = document.getElementById('bulk-delete-grades-btn');
            const countSpan = document.getElementById('selected-grades-count');
            if (count > 0) {
                btn.style.display = 'inline-block';
                countSpan.textContent = count;
            } else {
                btn.style.display = 'none';
            }
        }

        function toggleSelectAllGrades() {
            const selectAll = document.getElementById('select-all-grades');
            const checkboxes = document.querySelectorAll('.grades-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkDeleteGradesButton();
        }

        function bulkDeleteGrades() {
            const checkboxes = document.querySelectorAll('.grades-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Pilih setidaknya satu record untuk dihapus.');
                return;
            }
            if (!confirm(`Apakah Anda yakin ingin menghapus ${checkboxes.length} record nilai?`)) {
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value);
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="bulk_delete_grades">
                <input type="hidden" name="grade_ids" value="${JSON.stringify(ids)}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        async function exportAttendanceToExcel() {
            const classId = document.getElementById('history-attendance-class').value;
            const subjectId = document.getElementById('history-attendance-subject').value;
            const status = document.getElementById('history-attendance-status').value;
            const dateFrom = document.getElementById('history-attendance-date-from').value;
            const dateTo = document.getElementById('history-attendance-date-to').value;

            let url = 'api.php/attendances?';
            const filters = [];
            if (classId) filters.push(`filter=class_id,eq,${classId}`);
            if (subjectId) filters.push(`filter=subject_id,eq,${subjectId}`);
            if (status) filters.push(`filter=status,eq,${status}`);
            if (dateFrom) filters.push(`filter=date,gte,${dateFrom}`);
            if (dateTo) filters.push(`filter=date,lte,${dateTo}`);
            url += filters.join('&');

            try {
                const response = await fetch(url);
                const data = await response.json();
                const attendances = data.attendances.records;

                // Prepare CSV data
                const headers = ['Date', 'Student Name', 'NIS', 'Subject', 'Status', 'Notes'];
                const csvData = attendances.map(attendance => [
                    attendance.date,
                    attendance.student?.full_name || 'N/A',
                    attendance.student?.nis || 'N/A',
                    attendance.subject?.subject_name || 'N/A',
                    attendance.status,
                    attendance.notes || ''
                ]);

                // Create CSV string
                const csvContent = [headers, ...csvData]
                    .map(row => row.map(field => `"${field}"`).join(','))
                    .join('\n');

                // Generate filename with current date
                const now = new Date();
                const filename = `attendance_export_${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}.csv`;

                // Download CSV
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Error exporting attendance:', error);
                alert('Gagal mengekspor data absensi. Silakan coba lagi.');
            }
        }

        async function exportGradesToExcel() {
            const classId = document.getElementById('history-grades-class').value;
            const subjectId = document.getElementById('history-grades-subject').value;
            const assessmentType = document.getElementById('history-assessment-type').value;
            const scoreMin = document.getElementById('history-grades-score-min').value;
            const scoreMax = document.getElementById('history-grades-score-max').value;

            let url = 'api.php/grades?';
            const filters = [];
            if (classId) filters.push(`filter=class_id,eq,${classId}`);
            if (subjectId) filters.push(`filter=subject_id,eq,${subjectId}`);
            if (assessmentType) filters.push(`filter=assessment_type,eq,${assessmentType}`);
            if (scoreMin) filters.push(`filter=score,gte,${scoreMin}`);
            if (scoreMax) filters.push(`filter=score,lte,${scoreMax}`);
            url += filters.join('&');

            try {
                const response = await fetch(url);
                const data = await response.json();
                const grades = data.grades.records;

                // Prepare CSV data
                const headers = ['Date', 'Student Name', 'NIS', 'Subject', 'Assessment Type', 'Score'];
                const csvData = grades.map(grade => [
                    grade.grade_date,
                    grade.student?.full_name || 'N/A',
                    grade.student?.nis || 'N/A',
                    grade.subject?.subject_name || 'N/A',
                    grade.assessment_type,
                    grade.score
                ]);

                // Create CSV string
                const csvContent = [headers, ...csvData]
                    .map(row => row.map(field => `"${field}"`).join(','))
                    .join('\n');

                // Generate filename with current date
                const now = new Date();
                const filename = `grades_export_${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}.csv`;

                // Download CSV
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Error exporting grades:', error);
                alert('Gagal mengekspor data nilai. Silakan coba lagi.');
            }
        }

        async function loadAttendanceSummary() {
            const classId = document.getElementById('summary-class').value;
            const subjectId = document.getElementById('summary-subject').value;
            const dateFrom = document.getElementById('summary-date-from').value;
            const dateTo = document.getElementById('summary-date-to').value;

            document.getElementById('summary-loading').style.display = 'block';

            try {
                let url = 'api.php/attendances?join=students&join=subjects&join=classes';
                const filters = [];
                if (classId) filters.push(`filter=students.class_id,eq,${classId}`);
                if (subjectId) filters.push(`filter=subject_id,eq,${subjectId}`);
                if (dateFrom) filters.push(`filter=date,gte,${dateFrom}`);
                if (dateTo) filters.push(`filter=date,lte,${dateTo}`);
                if (filters.length > 0) url += '&' + filters.join('&');

                const response = await fetch(url);
                const data = await response.json();
                const attendances = data.attendances.records;

                const studentStats = {};
                attendances.forEach(att => {
                    const studentId = att.student_id;
                    if (!studentStats[studentId]) {
                        studentStats[studentId] = {
                            student: att.students || {},
                            total: 0,
                            hadir: 0,
                            sakit: 0,
                            izin: 0,
                            alpa: 0
                        };
                    }
                    studentStats[studentId].total++;
                    studentStats[studentId][att.status]++;
                });

                const tbody = document.getElementById('attendance-summary-body');
                tbody.innerHTML = '';
                const statusCounts = { hadir: 0, sakit: 0, izin: 0, alpa: 0 };
                Object.values(studentStats).forEach(stat => {
                    const percentage = stat.total > 0 ? ((stat.hadir / stat.total) * 100).toFixed(1) : 0;
                    let status = 'Baik';
                    if (percentage < 60) status = 'Buruk';
                    else if (percentage < 80) status = 'Sedang';
                    const row = `
                        <tr>
                            <td>${stat.student.nis || 'N/A'}</td>
                            <td>${stat.student.full_name || 'N/A'}</td>
                            <td>${stat.student.classes?.class_name || 'N/A'}</td>
                            <td>${stat.total}</td>
                            <td>${stat.hadir}</td>
                            <td>${stat.sakit}</td>
                            <td>${stat.izin}</td>
                            <td>${stat.alpa}</td>
                            <td>${percentage}%</td>
                            <td>${status}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                    statusCounts.hadir += stat.hadir;
                    statusCounts.sakit += stat.sakit;
                    statusCounts.izin += stat.izin;
                    statusCounts.alpa += stat.alpa;
                });

                const ctx = document.getElementById('attendanceSummaryChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
                        datasets: [{
                            data: [statusCounts.hadir, statusCounts.sakit, statusCounts.izin, statusCounts.alpa],
                            backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
                        }]
                    }
                });

                const statsDiv = document.getElementById('attendance-stats');
                const totalStudents = Object.keys(studentStats).length;
                const avgAttendance = totalStudents > 0 ? (Object.values(studentStats).reduce((sum, s) => sum + (s.hadir / s.total), 0) / totalStudents * 100).toFixed(1) : 0;
                statsDiv.innerHTML = `
                    <p>Total Records: ${attendances.length}</p>
                    <p>Total Students: ${totalStudents}</p>
                    <p>Average Attendance: ${avgAttendance}%</p>
                `;

                const lowAttendance = Object.values(studentStats).filter(s => (s.hadir / s.total) < 0.8);
                if (lowAttendance.length > 0) {
                    const list = lowAttendance.map(s => `<li>${s.student.full_name}: ${(s.hadir / s.total * 100).toFixed(1)}%</li>`).join('');
                    document.getElementById('low-attendance-list').innerHTML = `<ul>${list}</ul>`;
                    document.getElementById('low-attendance-alerts').style.display = 'block';
                } else {
                    document.getElementById('low-attendance-alerts').style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading summary:', error);
                alert('Gagal memuat ringkasan absensi.');
            } finally {
                document.getElementById('summary-loading').style.display = 'none';
            }
        }

        async function loadAttendanceCalendar() {
            const classId = document.getElementById('calendar-class').value;
            const subjectId = document.getElementById('calendar-subject').value;
            const month = document.getElementById('calendar-month').value;

            if (!classId || !subjectId || !month) {
                alert('Pilih kelas, mata pelajaran, dan bulan.');
                return;
            }

            document.getElementById('calendar-loading').style.display = 'block';

            try {
                const [year, monthNum] = month.split('-');
                const startDate = `${year}-${monthNum}-01`;
                const endDate = new Date(year, monthNum, 0).toISOString().split('T')[0];

                let url = `api.php/attendances?filter=date,gte,${startDate}&filter=date,lte,${endDate}&join=students`;
                if (subjectId) url += `&filter=subject_id,eq,${subjectId}`;

                const response = await fetch(url);
                const data = await response.json();
                const attendances = data.attendances.records.filter(att => att.students.class_id == classId);

                const studentsResponse = await fetch(`api.php/students?filter=class_id,eq,${classId}`);
                const studentsData = await studentsResponse.json();
                const totalStudents = studentsData.students.records.length;

                const dateStats = {};
                attendances.forEach(att => {
                    if (!dateStats[att.date]) dateStats[att.date] = { hadir: 0, total: totalStudents };
                    if (att.status === 'hadir') dateStats[att.date].hadir++;
                });

                const firstDay = new Date(year, monthNum - 1, 1);
                const lastDay = new Date(year, monthNum, 0);
                const startDayOfWeek = firstDay.getDay();
                const daysInMonth = lastDay.getDate();

                let calendarHTML = '';
                const daysOfWeek = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                daysOfWeek.forEach(day => {
                    calendarHTML += `<div class="calendar-header">${day}</div>`;
                });

                for (let i = 0; i < startDayOfWeek; i++) {
                    calendarHTML += '<div class="calendar-day empty"></div>';
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = `${year}-${monthNum.toString().padStart(2,'0')}-${day.toString().padStart(2,'0')}`;
                    const stat = dateStats[dateStr];
                    let className = 'calendar-day no-data';
                    let content = day;
                    if (stat) {
                        const percentage = (stat.hadir / stat.total) * 100;
                        if (percentage > 80) className = 'calendar-day good';
                        else if (percentage >= 60) className = 'calendar-day medium';
                        else className = 'calendar-day poor';
                        content = `${day}<br>${percentage.toFixed(0)}%`;
                    }
                    calendarHTML += `<div class="${className}">${content}</div>`;
                }

                document.getElementById('attendance-calendar-grid').innerHTML = calendarHTML;
                document.getElementById('calendar-title').textContent = `Absensi ${new Date(year, monthNum - 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}`;
            } catch (error) {
                console.error('Error loading calendar:', error);
                alert('Gagal memuat kalender absensi.');
            } finally {
                document.getElementById('calendar-loading').style.display = 'none';
            }
        }

        function previousMonth() {
            const input = document.getElementById('calendar-month');
            const current = new Date(input.value + '-01');
            current.setMonth(current.getMonth() - 1);
            input.value = current.toISOString().slice(0,7);
            loadAttendanceCalendar();
        }

        function nextMonth() {
            const input = document.getElementById('calendar-month');
            const current = new Date(input.value + '-01');
            current.setMonth(current.getMonth() + 1);
            input.value = current.toISOString().slice(0,7);
            loadAttendanceCalendar();
        }

        function exportAttendanceSummary() {
            const rows = document.querySelectorAll('#attendance-summary-body tr');
            const csvData = [['NIS', 'Nama Siswa', 'Kelas', 'Total Hari', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Persentase Kehadiran', 'Status']];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = Array.from(cells).map(cell => cell.textContent);
                csvData.push(rowData);
            });
            const csvContent = csvData.map(row => row.map(field => `"${field}"`).join(',')).join('\n');
            const now = new Date();
            const filename = `attendance_summary_${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}.csv`;
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }

        // Load history on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAttendanceHistory();
            loadGradesHistory();
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const isCollapsed = sidebar.classList.contains('collapsed');

            if (window.innerWidth < 1200) {
                if (isCollapsed) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.add('show');
                    overlay.classList.add('show');
                } else {
                    sidebar.classList.add('collapsed');
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            }
        }

        async function openAddIndividualAttendanceModal() {
            const classId = document.getElementById('attendance-class').value;
            const subjectId = document.getElementById('attendance-subject').value;

            if (!classId) {
                alert('Silakan pilih kelas terlebih dahulu.');
                return;
            }

            if (!subjectId) {
                alert('Silakan pilih mata pelajaran terlebih dahulu.');
                return;
            }

            try {
                const response = await fetch(`api.php/students?filter=class_id,eq,${classId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                const students = data.students.records;

                const studentSelect = document.getElementById('individual-student');
                studentSelect.innerHTML = '<option value="">Pilih Siswa</option>';
                students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = `${student.nis} - ${student.full_name}`;
                    studentSelect.appendChild(option);
                });

                // Set default values
                document.getElementById('individual-subject').value = subjectId;
                document.getElementById('individual-date').value = document.getElementById('attendance-date').value || new Date().toISOString().split('T')[0];
                document.getElementById('individual-status').value = 'hadir';
                document.getElementById('individual-notes').value = '';

                new bootstrap.Modal(document.getElementById('addIndividualAttendanceModal')).show();
            } catch (error) {
                console.error('Error loading students for modal:', error);
                alert('Gagal memuat data siswa. Silakan coba lagi.');
            }
        }
    </script>

    <!-- Add Individual Attendance Modal -->
    <div class="modal fade" id="addIndividualAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Absensi Individu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_individual_attendance">
                        <div class="mb-3">
                            <label class="form-label">Siswa</label>
                            <select class="form-control" name="student_id" id="individual-student" required>
                                <option value="">Pilih Siswa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select class="form-control" name="subject_id" id="individual-subject" required>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="date" id="individual-date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="individual-status" required>
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpa">Alpa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="notes" id="individual-notes" rows="3" placeholder="Catatan opsional"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Absensi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
