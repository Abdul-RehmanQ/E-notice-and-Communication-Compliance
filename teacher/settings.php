<?php
session_start();
include '../config.php';
require_once __DIR__ . '/teacher_guard.php';

$teacher = requireTeacherIdentity($conn);
$teacherUserId = (int)$teacher['user_id'];

$password_error = '';
$password_success = '';
$email_error = '';
$email_success = '';

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $repeatNewPassword = $_POST['repeatNewPassword'];

    // Fetch current password from DB
    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $teacherUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $passwordValid = verifyPasswordArgon2id($currentPassword, $user['password']);

    if (!$passwordValid) {
        $password_error = "Current password is incorrect!";
    } elseif ($newPassword !== $repeatNewPassword) {
        $password_error = "New passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $password_error = "Password must be at least 6 characters!";
    } else {
        $hashedPassword = hashPasswordArgon2id($newPassword);
        $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ? AND role = 'teacher'");
        $updateStmt->bind_param("si", $hashedPassword, $teacherUserId);
        if ($updateStmt->execute()) {
            $password_success = "Password updated successfully!";
        } else {
            $password_error = "Failed to update password!";
        }
        $updateStmt->close();
    }
}

// Handle Email Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Invalid email format!";
    } else {
        // Check if email already exists for another user
        $checkStmt = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $checkStmt->bind_param("si", $email, $teacherUserId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $email_error = "This email is already in use by another account!";
        } else {
            // Update email
            $updateStmt = $conn->prepare("UPDATE user SET email = ? WHERE user_id = ? AND role = 'teacher'");
            $updateStmt->bind_param("si", $email, $teacherUserId);
            if ($updateStmt->execute()) {
                $email_success = "Email updated successfully!";
                // Update cached identity
                $teacher['email'] = $email;
            } else {
                $email_error = "Failed to update email!";
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

// Fetch current email
$currentEmail = (string)($teacher['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-top: 56px;
        }

        @media (min-width: 992px) {
            body {
                padding-top: 70px;
            }

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

        /* 1366x768 and similar laptop screens */
        @media (min-width: 992px) and (max-width: 1399px) {
            .navbar .d-flex.text-white {
                font-size: 0.85rem;
                gap: 0.5rem !important;
            }

            main .container {
                max-width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-none d-lg-flex"
        style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Teacher:</strong> <?php echo htmlspecialchars($teacher['name']); ?></span>
                <span>|</span>
                <span><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department']); ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Teacher Dashboard</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar"
                style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Teacher
                            Dashboard</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white mb-2" href="dashboard.php"><i
                                class="fas fa-bell me-2"></i>Notifications</a>
                        <a class="nav-link text-white mb-2" href="community.php"><i
                                class="fas fa-users me-2"></i>Community</a>
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="settings.php"><i
                                class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i
                                class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2>Settings</h2>

                    <!-- Password Update Section -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Update Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($password_error): ?>
                                <div class="alert alert-danger"><?php echo $password_error; ?></div>
                            <?php endif; ?>
                            <?php if ($password_success): ?>
                                <div class="alert alert-success"><?php echo $password_success; ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="currentPassword" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="currentPassword" name="currentPassword"
                                        placeholder="Enter current password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="newPassword" name="newPassword"
                                        placeholder="Enter new password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="repeatNewPassword" class="form-label">Repeat New Password</label>
                                    <input type="password" class="form-control" id="repeatNewPassword" name="repeatNewPassword"
                                        placeholder="Repeat new password" required>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                            </form>
                        </div>
                    </div>

                    <!-- Email Update Section -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo empty($currentEmail) ? 'Add Email' : 'Update Email'; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($email_error): ?>
                                <div class="alert alert-danger"><?php echo $email_error; ?></div>
                            <?php endif; ?>
                            <?php if ($email_success): ?>
                                <div class="alert alert-success"><?php echo $email_success; ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($currentEmail); ?>"
                                        placeholder="Enter your email" required>
                                    <div class="form-text">Student reply emails will be sent to this address.</div>
                                </div>
                                <button type="submit" name="update_email" class="btn btn-primary"><?php echo empty($currentEmail) ? 'Add Email' : 'Update Email'; ?></button>
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
