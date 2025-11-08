<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is orangtua
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'orangtua') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

// Get parent info
$parent = getOne('parents', 'user_id = ?', [$user['id']]);

// Get children
$relations = getAll('student_parent_relations', 'parent_id = ?', [$parent['id']]);
$children = [];
foreach ($relations as $relation) {
    $child = getOne('students', 'id = ?', [$relation['student_id']]);
    if ($child) {
        $class = getOne('classes', 'id = ?', [$child['class_id']]);
        $child['class_name'] = $class['class_name'] ?? 'N/A';
        $children[] = $child;
    }
}

// Handle form submission for parent reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_reply') {
    update('report_cards', [
        'parent_reply_notes' => $_POST['reply']
    ], 'id = ?', [$_POST['report_id']]);
    $message = '<div class="alert alert-success">Reply saved successfully!</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orang Tua Dashboard - SIAP-Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
        }
        .sidebar .nav-link.active {
            color: white;
            background: #495057;
        }
        .content-wrapper {
            margin-left: 250px;
        }
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
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
                    <h5><i class="fas fa-users"></i> SIAP-Siswa</h5>
                    <p class="mb-4">Orang Tua Dashboard</p>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#children" onclick="showTab('children')">
                            <i class="fas fa-child"></i> My Children
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#attendance" onclick="showTab('attendance')">
                            <i class="fas fa-clipboard-check"></i> Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#grades" onclick="showTab('grades')">
                            <i class="fas fa-chart-line"></i> Grades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#reports" onclick="showTab('reports')">
                            <i class="fas fa-file-document"></i> Report Cards
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
                    <h2>Welcome, <?php echo htmlspecialchars($parent['full_name']); ?>!</h2>
                </div>

                <?php echo $message; ?>

                <!-- Children Tab -->
                <div id="children-tab" class="tab-content">
                    <h3>My Children</h3>
                    <div class="row">
                        <?php foreach ($children as $child): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-graduate fa-3x text-primary mb-3"></i>
                                        <h5><?php echo htmlspecialchars($child['full_name']); ?></h5>
                                        <p class="text-muted">NIS: <?php echo htmlspecialchars($child['nis']); ?></p>
                                        <p class="text-muted">Class: <?php echo htmlspecialchars($child['class_name']); ?></p>
                                        <p class="text-muted">Gender: <?php echo $child['gender'] === 'L' ? 'Male' : 'Female'; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Attendance Tab -->
                <div id="attendance-tab" class="tab-content" style="display: none;">
                    <h3>Attendance Records</h3>

                    <div class="mb-4">
                        <label class="form-label">Select Child</label>
                        <select class="form-control" id="attendance-child" onchange="loadAttendance()">
                            <option value="">Choose Child</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="attendance-records" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="attendance-data">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Grades Tab -->
                <div id="grades-tab" class="tab-content" style="display: none;">
                    <h3>Grade Progress</h3>

                    <div class="mb-4">
                        <label class="form-label">Select Child</label>
                        <select class="form-control" id="grades-child" onchange="loadGrades()">
                            <option value="">Choose Child</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="grades-records" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Assessment Type</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="grades-data">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Report Cards Tab -->
                <div id="reports-tab" class="tab-content" style="display: none;">
                    <h3>Report Cards</h3>

                    <div class="mb-4">
                        <label class="form-label">Select Child</label>
                        <select class="form-control" id="reports-child" onchange="loadReportCards()">
                            <option value="">Choose Child</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="report-cards" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 id="report-title"></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Student Information</h6>
                                        <p><strong>Name:</strong> <span id="student-name"></span></p>
                                        <p><strong>NIS:</strong> <span id="student-nis"></span></p>
                                        <p><strong>Class:</strong> <span id="student-class"></span></p>
                                        <p><strong>Semester:</strong> <span id="report-semester"></span></p>
                                        <p><strong>Academic Year:</strong> <span id="report-year"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Homeroom Teacher Notes</h6>
                                        <p id="teacher-notes"></p>
                                    </div>
                                </div>

                                <hr>

                                <h6>Your Reply</h6>
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_reply">
                                    <input type="hidden" name="report_id" id="report-id">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="reply" id="parent-reply" rows="4" placeholder="Add your comments or questions here..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Reply</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.style.display = 'none');
            document.getElementById(tabName + '-tab').style.display = 'block';

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

        async function loadAttendance() {
            const childId = document.getElementById('attendance-child').value;
            if (!childId) {
                document.getElementById('attendance-records').style.display = 'none';
                return;
            }

            const response = await fetch(`api.php/attendances?filter=student_id,eq,${childId}`);
            const data = await response.json();

            const tbody = document.getElementById('attendance-data');
            tbody.innerHTML = '';

            for (const record of data.attendances.records) {
                const subjectResponse = await fetch(`api.php/subjects/${record.subject_id}`);
                const subjectData = await subjectResponse.json();

                const row = `
                    <tr>
                        <td>${record.date}</td>
                        <td>${subjectData.subject.subject_name}</td>
                        <td>
                            <span class="badge bg-${getStatusColor(record.status)}">${record.status}</span>
                        </td>
                        <td>${record.notes || '-'}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
            }

            document.getElementById('attendance-records').style.display = 'block';
        }

        async function loadGrades() {
            const childId = document.getElementById('grades-child').value;
            if (!childId) {
                document.getElementById('grades-records').style.display = 'none';
                return;
            }

            const response = await fetch(`api.php/grades?filter=student_id,eq,${childId}`);
            const data = await response.json();

            const tbody = document.getElementById('grades-data');
            tbody.innerHTML = '';

            for (const record of data.grades.records) {
                const subjectResponse = await fetch(`api.php/subjects/${record.subject_id}`);
                const subjectData = await subjectResponse.json();

                const row = `
                    <tr>
                        <td>${subjectData.subject.subject_name}</td>
                        <td>${record.assessment_type.toUpperCase()}</td>
                        <td><strong>${record.score}</strong></td>
                        <td>${record.grade_date}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
            }

            document.getElementById('grades-records').style.display = 'block';
        }

        async function loadReportCards() {
            const childId = document.getElementById('reports-child').value;
            if (!childId) {
                document.getElementById('report-cards').style.display = 'none';
                return;
            }

            const response = await fetch(`api.php/report_cards?filter=student_id,eq,${childId}`);
            const data = await response.json();

            if (data.report_cards.records.length > 0) {
                const report = data.report_cards.records[0];
                const studentResponse = await fetch(`api.php/students/${childId}`);
                const studentData = await studentResponse.json();
                const classResponse = await fetch(`api.php/classes/${studentData.student.class_id}`);
                const classData = await classResponse.json();

                document.getElementById('report-title').textContent = `${studentData.student.full_name} - Report Card`;
                document.getElementById('student-name').textContent = studentData.student.full_name;
                document.getElementById('student-nis').textContent = studentData.student.nis;
                document.getElementById('student-class').textContent = classData.class.class_name;
                document.getElementById('report-semester').textContent = report.semester;
                document.getElementById('report-year').textContent = report.academic_year;
                document.getElementById('teacher-notes').textContent = report.homeroom_teacher_notes || 'No notes from teacher';
                document.getElementById('parent-reply').value = report.parent_reply_notes || '';
                document.getElementById('report-id').value = report.id;

                document.getElementById('report-cards').style.display = 'block';
            } else {
                document.getElementById('report-cards').style.display = 'none';
                alert('No report card found for this student.');
            }
        }

        function getStatusColor(status) {
            switch (status) {
                case 'hadir': return 'success';
                case 'sakit': return 'warning';
                case 'izin': return 'info';
                case 'alpa': return 'danger';
                default: return 'secondary';
            }
        }
    </script>
</body>
</html>
