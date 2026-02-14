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
$searchTerm = trim($_GET['q'] ?? '');
$students = [];

$adminDepartment = '';
$adminProfileStmt = $conn->prepare("SELECT department FROM super_admin WHERE super_admin_id = ?");
$adminProfileStmt->bind_param("i", $adminId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult ? $adminProfileResult->fetch_assoc() : null;
$adminProfileStmt->close();

if (!$adminProfile || empty($adminProfile['department'])) {
    $error = 'Super Admin department profile is missing. Please configure super_admin table entry first.';
} else {
    $adminDepartment = trim((string)$adminProfile['department']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reenroll_student'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($studentId <= 0 || $courseId <= 0) {
        $error = 'Please select a valid student and course.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } else {
        $scopeStmt = $conn->prepare("SELECT COUNT(*) AS matched_count
                                     FROM student s
                                     INNER JOIN courses c ON c.course_id = ?
                                     WHERE s.student_id = ?
                                       AND LOWER(TRIM(s.department)) = LOWER(TRIM(?))
                                       AND LOWER(TRIM(c.department)) = LOWER(TRIM(?))");
        $scopeStmt->bind_param("iiss", $courseId, $studentId, $adminDepartment, $adminDepartment);
        $scopeStmt->execute();
        $scopeResult = $scopeStmt->get_result();
        $scopeRow = $scopeResult ? $scopeResult->fetch_assoc() : ['matched_count' => 0];
        $scopeStmt->close();

        if ((int)($scopeRow['matched_count'] ?? 0) === 0) {
            $error = 'Re-enroll blocked: student/course is outside your department scope.';
        } else {
            $assignmentStmt = $conn->prepare("SELECT COUNT(*) AS assigned_count
                                              FROM teacher_course_assignments tca
                                              INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                                              INNER JOIN courses c ON c.course_id = tca.course_id
                                              WHERE tca.course_id = ?
                                                AND LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                                                AND LOWER(TRIM(c.department)) = LOWER(TRIM(?))");
            $assignmentStmt->bind_param("iss", $courseId, $adminDepartment, $adminDepartment);
            $assignmentStmt->execute();
            $assignmentResult = $assignmentStmt->get_result();
            $assignmentRow = $assignmentResult ? $assignmentResult->fetch_assoc() : ['assigned_count' => 0];
            $assignmentStmt->close();

            if ((int)($assignmentRow['assigned_count'] ?? 0) === 0) {
                $error = 'Re-enroll blocked: selected course has no assigned teacher in your department.';
            } else {
                $checkStmt = $conn->prepare("SELECT enrollment_id, status FROM student_course_enrollments WHERE student_id = ? AND course_id = ? LIMIT 1");
                $checkStmt->bind_param("ii", $studentId, $courseId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result();
                $existingEnrollment = $existing ? $existing->fetch_assoc() : null;
                $checkStmt->close();

                if ($existingEnrollment) {
                    $error = 'Re-enroll blocked: this student already has a record for the selected course.';
                } else {
                    $insertStmt = $conn->prepare("INSERT INTO student_course_enrollments (student_id, course_id, enrolled_by) VALUES (?, ?, ?)");
                    $insertStmt->bind_param("iii", $studentId, $courseId, $adminId);
                    if ($insertStmt->execute()) {
                        $success = 'Student re-enrolled successfully.';
                    } else {
                        $error = 'Failed to re-enroll student: ' . $conn->error;
                    }
                    $insertStmt->close();
                }
            }
        }
    }
}

$courses = [];
$courseStmt = $conn->prepare("SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.semester_no
                              FROM courses c
                              INNER JOIN teacher_course_assignments tca ON tca.course_id = c.course_id
                              INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                              WHERE LOWER(TRIM(c.department)) = LOWER(TRIM(?))
                                AND LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                              ORDER BY c.course_code ASC");
$courseStmt->bind_param("ss", $adminDepartment, $adminDepartment);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
    }
}
$courseStmt->close();

if ($searchTerm !== '') {
    $likeTerm = '%' . $searchTerm . '%';
    $stmt = $conn->prepare("SELECT s.student_id, s.name, s.Roll_no, s.department, s.session, s.semester_no, u.email
                            FROM student s
                            INNER JOIN user u ON u.user_id = s.student_id
                            WHERE LOWER(TRIM(s.department)) = LOWER(TRIM(?))
                              AND (s.Roll_no LIKE ? OR s.name LIKE ? OR u.email LIKE ?)
                            ORDER BY s.name ASC");
    $stmt->bind_param("ssss", $adminDepartment, $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-enroll Students - Super Admin</title>
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
                <span><strong>Department:</strong> <?php echo htmlspecialchars($adminDepartment ?: 'Not Set'); ?></span>
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
                        <a class="nav-link text-white mb-2" href="dashboard.php"><i class="fas fa-gauge me-2"></i>Dashboard</a>
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="re_enroll.php"><i class="fas fa-search me-2"></i>Re-enroll Search</a>
                        <a class="nav-link text-white mb-2" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2 class="mb-3">Re-enroll Student by Search</h2>
                    <p class="text-muted">Use this page for special cases (e.g., failed course). Batch enroll remains available on dashboard.</p>

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

                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3 align-items-end">
                                <div class="col-md-9">
                                    <label for="q" class="form-label">Search Student</label>
                                    <input type="text" class="form-control" id="q" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Enter roll number, name, or email" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($searchTerm !== ''): ?>
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">Search Results (<?php echo count($students); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($students)): ?>
                                    <p class="text-muted mb-0">No students found for "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Roll No</th>
                                                    <th>Info</th>
                                                    <th style="min-width: 280px;">Re-enroll Course</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $student): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($student['Roll_no']); ?></td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($student['department']); ?><br>
                                                                <?php echo htmlspecialchars($student['session']); ?> | Sem <?php echo (int)$student['semester_no']; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <form method="POST" action="" class="d-flex gap-2">
                                                                <input type="hidden" name="student_id" value="<?php echo (int)$student['student_id']; ?>">
                                                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                                                <select class="form-select" name="course_id" required>
                                                                    <option value="">Select Course</option>
                                                                    <?php foreach ($courses as $course): ?>
                                                                        <option value="<?php echo (int)$course['course_id']; ?>">
                                                                            <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?>
                                                                            (Sem <?php echo (int)$course['semester_no']; ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <button type="submit" name="reenroll_student" class="btn btn-success" <?php echo empty($courses) ? 'disabled' : ''; ?>>Re-enroll</button>
                                                            </form>
                                                            <?php if (empty($courses)): ?>
                                                                <small class="text-muted d-block mt-1">No courses available. Assign a teacher first.</small>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
