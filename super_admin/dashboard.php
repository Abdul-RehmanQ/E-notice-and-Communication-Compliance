<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$adminId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_course'])) {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($teacherId <= 0 || $courseId <= 0) {
        $error = 'Please select both teacher and course.';
    } else {
        $checkStmt = $conn->prepare("SELECT assignment_id FROM teacher_course_assignments WHERE teacher_id = ? AND course_id = ?");
        $checkStmt->bind_param("ii", $teacherId, $courseId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        $alreadyExists = $existing && $existing->num_rows > 0;
        $checkStmt->close();

        if ($alreadyExists) {
            $error = 'This teacher is already assigned to the selected course.';
        } else {
            $stmt = $conn->prepare("INSERT INTO teacher_course_assignments (teacher_id, course_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $teacherId, $courseId, $adminId);
            if ($stmt->execute()) {
                $success = 'Course assigned to teacher successfully.';
            } else {
                $error = 'Failed to assign course: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_student'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $courseId = (int)($_POST['enroll_course_id'] ?? 0);

    if ($studentId <= 0 || $courseId <= 0) {
        $error = 'Please select both student and course.';
    } else {
        $checkStmt = $conn->prepare("SELECT enrollment_id FROM student_course_enrollments WHERE student_id = ? AND course_id = ?");
        $checkStmt->bind_param("ii", $studentId, $courseId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        $alreadyExists = $existing && $existing->num_rows > 0;
        $checkStmt->close();

        if ($alreadyExists) {
            $error = 'This student is already enrolled in the selected course.';
        } else {
            $stmt = $conn->prepare("INSERT INTO student_course_enrollments (student_id, course_id, enrolled_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $studentId, $courseId, $adminId);
            if ($stmt->execute()) {
                $success = 'Student enrolled successfully.';
            } else {
                $error = 'Enrollment failed: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

$teachers = [];
$teacherResult = $conn->query("SELECT teacher_id, name, department FROM teacher ORDER BY name ASC");
if ($teacherResult) {
    while ($row = $teacherResult->fetch_assoc()) {
        $teachers[] = $row;
    }
}

$students = [];
$studentResult = $conn->query("SELECT student_id, name, Roll_no, department, semester_no FROM student ORDER BY name ASC");
if ($studentResult) {
    while ($row = $studentResult->fetch_assoc()) {
        $students[] = $row;
    }
}

$courses = [];
$courseResult = $conn->query("SELECT course_id, course_code, course_title, department, semester_no FROM courses WHERE is_active = 1 ORDER BY course_code ASC");
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
    }
}

$recentAssignments = [];
$assignmentResult = $conn->query("SELECT tca.assigned_at, t.name AS teacher_name, c.course_code, c.course_title, u.email AS assigned_by_email
                                 FROM teacher_course_assignments tca
                                 INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                                 INNER JOIN courses c ON c.course_id = tca.course_id
                                 INNER JOIN user u ON u.user_id = tca.assigned_by
                                 ORDER BY tca.assigned_at DESC LIMIT 10");
if ($assignmentResult) {
    while ($row = $assignmentResult->fetch_assoc()) {
        $recentAssignments[] = $row;
    }
}

$recentEnrollments = [];
$enrollmentResult = $conn->query("SELECT sce.enrolled_at, s.name AS student_name, s.Roll_no, c.course_code, c.course_title, u.email AS enrolled_by_email
                                 FROM student_course_enrollments sce
                                 INNER JOIN student s ON s.student_id = sce.student_id
                                 INNER JOIN courses c ON c.course_id = sce.course_id
                                 INNER JOIN user u ON u.user_id = sce.enrolled_by
                                 ORDER BY sce.enrolled_at DESC LIMIT 10");
if ($enrollmentResult) {
    while ($row = $enrollmentResult->fetch_assoc()) {
        $recentEnrollments[] = $row;
    }
}

$counts = [
    'teachers' => 0,
    'students' => 0,
    'courses' => 0,
    'assignments' => 0,
    'enrollments' => 0
];

$countResult = $conn->query("SELECT
    (SELECT COUNT(*) FROM teacher) AS teachers,
    (SELECT COUNT(*) FROM student) AS students,
    (SELECT COUNT(*) FROM courses WHERE is_active = 1) AS courses,
    (SELECT COUNT(*) FROM teacher_course_assignments) AS assignments,
    (SELECT COUNT(*) FROM student_course_enrollments WHERE status = 'active') AS enrollments");
if ($countResult) {
    $counts = $countResult->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { padding-top: 56px; }

        @media (min-width: 992px) {
            body { padding-top: 70px; }
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1040;
            }
            main {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary fixed-top d-none d-lg-flex" style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Super Admin:</strong> <?php echo htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin'); ?></span>
                <span>|</span>
                <span><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['super_admin_email'] ?? ''); ?></span>
            </div>
        </div>
    </nav>

    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Super Admin</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar" style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Super Admin Panel</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php"><i class="fas fa-gauge me-2"></i>Dashboard</a>
                        <a class="nav-link text-white mb-2" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2 class="mb-4">Academic Assignment Dashboard</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-primary"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['teachers']; ?></h5><small>Teachers</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-success"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['students']; ?></h5><small>Students</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-info"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['courses']; ?></h5><small>Courses</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card text-bg-warning"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['assignments']; ?></h5><small>Teacher Assignments</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card text-bg-secondary"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['enrollments']; ?></h5><small>Active Enrollments</small></div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Assign Course to Teacher</h5></div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="teacher_id" class="form-label">Teacher</label>
                                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                                <option value="">Select Teacher</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo (int)$teacher['teacher_id']; ?>">
                                                        <?php echo htmlspecialchars($teacher['name']); ?> (<?php echo htmlspecialchars($teacher['department']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="course_id" class="form-label">Course</label>
                                            <select class="form-select" id="course_id" name="course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo (int)$course['course_id']; ?>">
                                                        <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?>
                                                        (Sem <?php echo (int)$course['semester_no']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="assign_course" class="btn btn-primary">Assign Course</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Enroll Student in Course</h5></div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="student_id" class="form-label">Student</label>
                                            <select class="form-select" id="student_id" name="student_id" required>
                                                <option value="">Select Student</option>
                                                <?php foreach ($students as $student): ?>
                                                    <option value="<?php echo (int)$student['student_id']; ?>">
                                                        <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['Roll_no']); ?>)
                                                        - Sem <?php echo (int)$student['semester_no']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="enroll_course_id" class="form-label">Course</label>
                                            <select class="form-select" id="enroll_course_id" name="enroll_course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo (int)$course['course_id']; ?>">
                                                        <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?>
                                                        (Sem <?php echo (int)$course['semester_no']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="enroll_student" class="btn btn-success">Enroll Student</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Recent Teacher Assignments</h5></div>
                                <div class="card-body">
                                    <?php if (empty($recentAssignments)): ?>
                                        <p class="text-muted mb-0">No assignments yet.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recentAssignments as $item): ?>
                                                <li class="list-group-item px-0">
                                                    <strong><?php echo htmlspecialchars($item['teacher_name']); ?></strong>
                                                    → <?php echo htmlspecialchars($item['course_code']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y h:i A', strtotime($item['assigned_at'])); ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Recent Student Enrollments</h5></div>
                                <div class="card-body">
                                    <?php if (empty($recentEnrollments)): ?>
                                        <p class="text-muted mb-0">No enrollments yet.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recentEnrollments as $item): ?>
                                                <li class="list-group-item px-0">
                                                    <strong><?php echo htmlspecialchars($item['student_name']); ?></strong>
                                                    → <?php echo htmlspecialchars($item['course_code']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y h:i A', strtotime($item['enrolled_at'])); ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    </script>
</body>

</html>
