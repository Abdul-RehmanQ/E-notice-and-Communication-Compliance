<?php
session_start();
include '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch student data
$stmt = $conn->prepare("SELECT s.name, s.Roll_no, s.department, s.session FROM student s WHERE s.student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Student Dashboard</title>
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

            .navbar .d-flex.text-white span:contains('|') {
                display: none;
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
                <span><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></span>
                <span>|</span>
                <span><strong>Roll No:</strong> <?php echo htmlspecialchars($student['Roll_no']); ?></span>
                <span>|</span>
                <span><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></span>
                <span>|</span>
                <span><strong>Session:</strong> <?php echo htmlspecialchars($student['session']); ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Student Dashboard</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Offcanvas on mobile, fixed on desktop -->
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar"
                style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Student
                            Dashboard</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php"><i
                                class="fas fa-bell me-2"></i>Notifications</a>
                        <a class="nav-link text-white mb-2" href="community.php"><i
                                class="fas fa-users me-2"></i>Community</a>
                        <a class="nav-link text-white mb-2" href="settings.php"><i
                                class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i
                                class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2>Notifications</h2>
                    <h5 class="text-muted mb-3">Notifications from Teachers</h5>

                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <p>Assignment on Data Structures is due tomorrow.</p>
                            <small class="text-muted">From: Dr. Smith</small>
                            <br>
                            <button class="btn btn-primary btn-sm mt-2">Reply</button>
                        </div>
                    </div>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <p>Class on Algorithms is cancelled today.</p>
                            <small class="text-muted">From: Prof. Johnson</small>
                            <br>
                            <button class="btn btn-primary btn-sm mt-2">Reply</button>
                        </div>
                    </div>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <p>Project submission deadline extended to next week.</p>
                            <small class="text-muted">From: Dr. Smith</small>
                            <br>
                            <button class="btn btn-primary btn-sm mt-2">Reply</button>
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
