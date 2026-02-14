<?php
session_start();
include '../config.php';

// Check if user is logged in as supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'community_supervisor') {
    header("Location: login.php");
    exit();
}

$password_error = '';
$password_success = '';

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $repeatNewPassword = $_POST['repeatNewPassword'];
    
    // Fetch current password from DB
    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
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
        $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
        if ($updateStmt->execute()) {
            $password_success = "Password updated successfully!";
        } else {
            $password_error = "Failed to update password!";
        }
        $updateStmt->close();
    }
}

// Count pending posts for sidebar badge
$statsResult = $conn->query("SELECT COUNT(*) as pending_count FROM posts WHERE status = 'pending' AND expires_at > NOW()");
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Community Supervisor</title>
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
    </style>
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-success fixed-top d-none d-lg-flex"
        style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Supervisor:</strong> <?php echo htmlspecialchars($_SESSION['supervisor_name']); ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-success fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Supervisor Dashboard</span>
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
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Supervisor Panel</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white mb-2" href="dashboard.php">
                            <i class="fas fa-tasks me-2"></i>Pending Posts
                            <?php if ($stats['pending_count'] > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $stats['pending_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn">
                            <i class="fas fa-sign-out-alt me-2"></i>Log out
                        </button>
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
