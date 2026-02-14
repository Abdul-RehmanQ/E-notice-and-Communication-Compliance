<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$password_error = '';
$password_success = '';

$adminId = (int)$_SESSION['user_id'];
$adminDepartment = '';
$adminProfileStmt = $conn->prepare("SELECT department FROM super_admin WHERE super_admin_id = ?");
$adminProfileStmt->bind_param("i", $adminId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult ? $adminProfileResult->fetch_assoc() : null;
$adminProfileStmt->close();

if ($adminProfile && !empty($adminProfile['department'])) {
    $adminDepartment = trim((string)$adminProfile['department']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $repeatNewPassword = $_POST['repeatNewPassword'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        $password_error = 'User record not found.';
    } else {
        $passwordValid = ($currentPassword === $user['password']) || password_verify($currentPassword, $user['password']);

        if (!$passwordValid) {
            $password_error = 'Current password is incorrect!';
        } elseif ($newPassword !== $repeatNewPassword) {
            $password_error = 'New passwords do not match!';
        } elseif (strlen($newPassword) < 6) {
            $password_error = 'Password must be at least 6 characters!';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
            if ($updateStmt->execute()) {
                $password_success = 'Password updated successfully!';
            } else {
                $password_error = 'Failed to update password!';
            }
            $updateStmt->close();
        }
    }
}

$countStmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM courses WHERE LOWER(TRIM(department)) = LOWER(TRIM(?))) AS courses,
    (SELECT COUNT(*)
        FROM teacher_course_assignments tca
        INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
        WHERE LOWER(TRIM(t.department)) = LOWER(TRIM(?))) AS assignments,
    (SELECT COUNT(*)
        FROM student_course_enrollments sce
        INNER JOIN student s ON s.student_id = sce.student_id
        WHERE sce.status = 'active' AND LOWER(TRIM(s.department)) = LOWER(TRIM(?))) AS enrollments");
$countStmt->bind_param("sss", $adminDepartment, $adminDepartment, $adminDepartment);
$countStmt->execute();
$countResult = $countStmt->get_result();
$counts = $countResult ? $countResult->fetch_assoc() : ['courses' => 0, 'assignments' => 0, 'enrollments' => 0];
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Super Admin</title>
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
                        <a class="nav-link text-white mb-2" href="re_enroll.php"><i class="fas fa-search me-2"></i>Re-enroll Search</a>
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2>Settings</h2>

                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-info text-white"><div class="card-body text-center"><h4 class="mb-0"><?php echo (int)$counts['courses']; ?></h4><small>Active Courses</small></div></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-warning text-dark"><div class="card-body text-center"><h4 class="mb-0"><?php echo (int)$counts['assignments']; ?></h4><small>Teacher Assignments</small></div></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-secondary text-white"><div class="card-body text-center"><h4 class="mb-0"><?php echo (int)$counts['enrollments']; ?></h4><small>Active Enrollments</small></div></div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Update Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($password_error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($password_error); ?></div>
                            <?php endif; ?>
                            <?php if ($password_success): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($password_success); ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="currentPassword" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                </div>
                                <div class="mb-3">
                                    <label for="repeatNewPassword" class="form-label">Repeat New Password</label>
                                    <input type="password" class="form-control" id="repeatNewPassword" name="repeatNewPassword" required>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                            </form>
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
