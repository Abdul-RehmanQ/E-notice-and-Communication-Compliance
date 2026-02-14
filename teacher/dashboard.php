<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT t.teacher_id, t.name, t.department, u.email
                        FROM teacher t
                        INNER JOIN user u ON t.teacher_id = u.user_id
                        WHERE t.teacher_id = ? AND u.role = 'teacher'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$teacher) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['teacher_id'] = $teacher['teacher_id'];
$_SESSION['teacher_name'] = $teacher['name'];
$_SESSION['teacher_department'] = $teacher['department'];

$success = '';
$error = '';

$assignedCourses = [];
$assignedCourseStmt = $conn->prepare("SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.department, c.semester_no
                                      FROM teacher_course_assignments tca
                                      INNER JOIN courses c ON c.course_id = tca.course_id
                                      WHERE tca.teacher_id = ?
                                      ORDER BY c.course_code ASC");
$assignedCourseStmt->bind_param("i", $teacher['teacher_id']);
$assignedCourseStmt->execute();
$assignedCourseResult = $assignedCourseStmt->get_result();
if ($assignedCourseResult) {
    while ($row = $assignedCourseResult->fetch_assoc()) {
        $assignedCourses[] = $row;
    }
}
$assignedCourseStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $selectedCourseId = (int)($_POST['course_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($selectedCourseId <= 0) {
        $error = 'Please select a course.';
    } elseif ($message === '') {
        $error = 'Please enter a notification message.';
    } elseif (mb_strlen($message) > 2000) {
        $error = 'Message is too long. Maximum 2000 characters allowed.';
    } else {
        $selectedCourse = null;
        foreach ($assignedCourses as $course) {
            if ((int)$course['course_id'] === $selectedCourseId) {
                $selectedCourse = $course;
                break;
            }
        }

        $assignmentCheckStmt = $conn->prepare("SELECT assignment_id
                                               FROM teacher_course_assignments
                                               WHERE teacher_id = ? AND course_id = ?
                                               LIMIT 1");
        $assignmentCheckStmt->bind_param("ii", $teacher['teacher_id'], $selectedCourseId);
        $assignmentCheckStmt->execute();
        $assignmentCheckResult = $assignmentCheckStmt->get_result();
        $isAssignedToCourse = $assignmentCheckResult && $assignmentCheckResult->num_rows > 0;
        $assignmentCheckStmt->close();

        if (!$isAssignedToCourse) {
            $error = 'You can only send notifications for courses assigned to you.';
        } else {
            $recipientIds = [];
            $recipientSessions = [];
            $recipientStmt = $conn->prepare("SELECT DISTINCT sce.student_id
                                             , s.session
                                             FROM student_course_enrollments sce
                                             INNER JOIN student s ON s.student_id = sce.student_id
                                             WHERE sce.course_id = ? AND sce.status = 'active'");
            $recipientStmt->bind_param("i", $selectedCourseId);
            $recipientStmt->execute();
            $recipientResult = $recipientStmt->get_result();
            if ($recipientResult) {
                while ($recipient = $recipientResult->fetch_assoc()) {
                    $recipientIds[] = (int)$recipient['student_id'];
                    if (!empty($recipient['session'])) {
                        $recipientSessions[$recipient['session']] = true;
                    }
                }
            }
            $recipientStmt->close();

            if (empty($recipientIds)) {
                $error = 'No active enrolled students found for the selected course.';
            } else {
                $insertStmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, message) VALUES (?, ?, ?)");
                $insertedCount = 0;

                foreach ($recipientIds as $recipientId) {
                    $insertStmt->bind_param("iis", $recipientId, $teacher['teacher_id'], $message);
                    if ($insertStmt->execute()) {
                        $insertedCount++;
                    }
                }

                $insertStmt->close();

                if ($insertedCount > 0) {
                    $sessionLabel = 'N/A';
                    if (!empty($recipientSessions)) {
                        $sessionLabel = implode(', ', array_keys($recipientSessions));
                    }

                    $courseCode = $selectedCourse['course_code'] ?? 'Selected Course';
                    $semesterNo = $selectedCourse['semester_no'] ?? 'N/A';
                    $success = "Notification sent for {$courseCode} (Semester {$semesterNo}, Session {$sessionLabel}) to {$insertedCount} student(s).";
                } else {
                    $error = 'Failed to send notification. Please try again.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notificationIdToDelete = (int)($_POST['notification_id'] ?? 0);

    if ($notificationIdToDelete <= 0) {
        $error = 'Please select a notification to delete.';
    } else {
        $findStmt = $conn->prepare("SELECT message, created_at
                                    FROM notifications
                                    WHERE notification_id = ? AND sender_id = ?
                                    LIMIT 1");
        $findStmt->bind_param("ii", $notificationIdToDelete, $teacher['teacher_id']);
        $findStmt->execute();
        $findResult = $findStmt->get_result();
        $batchToDelete = $findResult ? $findResult->fetch_assoc() : null;
        $findStmt->close();

        if (!$batchToDelete) {
            $error = 'Unable to delete the selected notification.';
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM notifications
                                          WHERE sender_id = ? AND message = ? AND created_at = ?");
            $deleteStmt->bind_param("iss", $teacher['teacher_id'], $batchToDelete['message'], $batchToDelete['created_at']);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows > 0) {
                $success = 'Notification deleted successfully.';
            } else {
                $error = 'Unable to delete the selected notification.';
            }

            $deleteStmt->close();
        }
    }
}

$sentNotifications = [];
$sentStmt = $conn->prepare("SELECT MIN(n.notification_id) AS notification_id, n.message, n.created_at, COUNT(*) AS recipient_count
                            FROM notifications n
                            WHERE n.sender_id = ?
                            GROUP BY n.message, n.created_at
                            ORDER BY n.created_at DESC
                            LIMIT 100");
$sentStmt->bind_param("i", $teacher['teacher_id']);
$sentStmt->execute();
$sentResult = $sentStmt->get_result();
if ($sentResult) {
    while ($row = $sentResult->fetch_assoc()) {
        $sentNotifications[] = $row;
    }
}
$sentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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

            .row.g-3 .col-md-3 {
                flex: 0 0 auto;
                width: 50%;
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
            <!-- Sidebar - Offcanvas on mobile, fixed on desktop -->
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
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h2 class="mb-0">Notifications</h2>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button class="btn btn-success" id="show-add-form">
                                <i class="fas fa-plus me-1"></i>Add New Notification
                            </button>
                            <button class="btn btn-danger" id="show-delete-modal">
                                <i class="fas fa-trash me-1"></i>Delete Notification
                            </button>
                        </div>
                    </div>

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

                    <!-- Add Notification Form -->
                    <div class="card mb-4 d-none" id="add-notification-card">
                        <div class="card-header">
                            <h5 class="mb-0">New Notification</h5>
                        </div>
                        <div class="card-body">
                            <form id="add-notification-form" method="POST" action="">
                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Assigned Course</label>
                                    <select class="form-select" id="course_id" name="course_id" required>
                                        <option value="" selected disabled>Select assigned course</option>
                                        <?php foreach ($assignedCourses as $course): ?>
                                            <option value="<?php echo (int)$course['course_id']; ?>">
                                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($assignedCourses)): ?>
                                        <div class="form-text text-danger">No assigned courses found. Ask admin to assign a course first.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="4"
                                        placeholder="Write your notification..." required></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" id="cancel-add">Cancel</button>
                                    <button type="submit" name="send_notification" class="btn btn-success" <?php echo empty($assignedCourses) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-paper-plane me-1"></i>Send Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Notification History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Notification History</h5>
                        </div>
                        <div class="card-body" id="notification-list">
                            <?php if (empty($sentNotifications)): ?>
                                <div class="alert alert-info" id="no-notifications-text">
                                    No notifications yet. Click "Add New Notification" to create one.
                                </div>
                            <?php else: ?>
                                <?php foreach ($sentNotifications as $notification): ?>
                                    <div class="card mb-3 shadow-sm" data-notification-id="<?php echo (int)$notification['notification_id']; ?>">
                                        <div class="card-body">
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i>Sent to: <?php echo (int)$notification['recipient_count']; ?> student(s)
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Delete Notification Modal -->
                    <div class="modal fade" id="deleteNotificationModal" tabindex="-1"
                        aria-labelledby="deleteNotificationModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteNotificationModalLabel">
                                        <i class="fas fa-trash-alt me-2"></i>Delete Notification
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="delete-notification-form" method="POST" action="">
                                        <div class="mb-3">
                                            <label for="selectNotificationToDelete" class="form-label fw-bold">Select a
                                                notification to delete:</label>
                                            <select class="form-select" id="selectNotificationToDelete" name="notification_id" required>
                                                <option value="" selected disabled>-- Choose a notification --</option>
                                                <?php foreach ($sentNotifications as $notification): ?>
                                                    <option value="<?php echo (int)$notification['notification_id']; ?>">
                                                        <?php
                                                        $preview = mb_substr($notification['message'], 0, 60);
                                                        $preview = mb_strlen($notification['message']) > 60 ? $preview . '...' : $preview;
                                                        echo htmlspecialchars($preview . ' | ' . date('M d, Y h:i A', strtotime($notification['created_at'])));
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" form="delete-notification-form" name="delete_notification" class="btn btn-danger" id="confirmDeleteNotification"
                                        <?php echo empty($sentNotifications) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash me-1"></i>Delete Selected
                                    </button>
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
        const addCard = document.getElementById('add-notification-card');
        const showAddFormBtn = document.getElementById('show-add-form');
        const cancelAddBtn = document.getElementById('cancel-add');
        const showDeleteModalBtn = document.getElementById('show-delete-modal');
        const selectNotificationToDelete = document.getElementById('selectNotificationToDelete');
        const confirmDeleteNotificationBtn = document.getElementById('confirmDeleteNotification');

        // Show/hide add notification form
        showAddFormBtn.addEventListener('click', () => {
            addCard.classList.remove('d-none');
            window.scrollTo({ top: addCard.offsetTop - 70, behavior: 'smooth' });
        });

        cancelAddBtn.addEventListener('click', () => {
            document.getElementById('add-notification-form').reset();
            addCard.classList.add('d-none');
        });

        showDeleteModalBtn.addEventListener('click', () => {
            if (!selectNotificationToDelete || selectNotificationToDelete.options.length <= 1) {
                alert('There are no notifications to delete.');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('deleteNotificationModal'));
            modal.show();
        });

        if (selectNotificationToDelete && confirmDeleteNotificationBtn) {
            selectNotificationToDelete.addEventListener('change', () => {
                confirmDeleteNotificationBtn.disabled = !selectNotificationToDelete.value;
            });
        }

        // Logout back to main index
        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.href = '../index.php';
        });
    </script>
</body>

</html>
