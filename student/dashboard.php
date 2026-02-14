<?php
session_start();
include '../config.php';
include '../send_email.php';
require_once __DIR__ . '/student_guard.php';

$studentIdentity = requireStudentIdentity($conn);
$studentUserId = (int)$studentIdentity['user_id'];

$error = '';
$success = '';

$studentEmail = (string)($studentIdentity['email'] ?? '');

// Handle email reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $recipientId = (int)$_POST['recipient_id'];
    $notificationId = (int)$_POST['notification_id'];
    $subject = trim($_POST['reply_subject']);
    $message = trim($_POST['reply_message']);

    $recipientEmail = '';
    $recipientLookupStmt = $conn->prepare("SELECT u.email
                                           FROM teacher t
                                           INNER JOIN user u ON u.user_id = t.teacher_id
                                           WHERE t.teacher_id = ? AND u.role = 'teacher'
                                           LIMIT 1");
    $recipientLookupStmt->bind_param("i", $recipientId);
    $recipientLookupStmt->execute();
    $recipientLookupResult = $recipientLookupStmt->get_result();
    if ($recipientLookupResult && $recipientLookupRow = $recipientLookupResult->fetch_assoc()) {
        $recipientEmail = trim((string)$recipientLookupRow['email']);
    }
    $recipientLookupStmt->close();
    
    if (empty($recipientEmail) || empty($subject) || empty($message)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Teacher email is invalid. Please contact admin.";
    } else {
        // Format email body with HTML
        $emailBody = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h3>" . htmlspecialchars($subject) . "</h3>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>This message was sent via University Portal by " . htmlspecialchars($_SESSION['student_name']) . "</p>
            </div>
        ";
        
        if (empty($studentEmail)) {
            $error = "Your student email was not found. Please contact admin.";
        } else {
            $result = sendReplyEmail(
                $conn,
                $studentUserId,
                $recipientId,
                $recipientEmail,
                $studentEmail,
                ($_SESSION['student_name'] ?? 'Student'),
                $subject,
                $emailBody,
                null
            );
        
            if ($result['success']) {
                $success = "Email sent successfully to " . htmlspecialchars($recipientEmail) . "!";
            } else {
                $error = "Failed to send email: " . $result['error'];
            }
        }
    }
}

// Fetch student data
$stmt = $conn->prepare("SELECT s.name, s.Roll_no, s.department, s.session FROM student s WHERE s.student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Fetch notifications for this student (from teachers)
$notifications = [];
$stmt = $conn->prepare("SELECT n.*, t.name as teacher_name, u.email as teacher_email, t.teacher_id
                        FROM notifications n 
                        INNER JOIN teacher t ON n.sender_id = t.teacher_id 
                        INNER JOIN user u ON t.teacher_id = u.user_id
                        WHERE n.recipient_id = ? 
                        ORDER BY n.created_at DESC");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
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

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No notifications yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body">
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>From: <?php echo htmlspecialchars($notification['teacher_name']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm mt-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#replyModal"
                                            data-recipient-email="<?php echo htmlspecialchars($notification['teacher_email']); ?>"
                                            data-recipient-id="<?php echo $notification['teacher_id']; ?>"
                                            data-recipient-name="<?php echo htmlspecialchars($notification['teacher_name']); ?>"
                                            data-notification-id="<?php echo $notification['notification_id']; ?>"
                                            data-notification-preview="<?php echo htmlspecialchars(substr($notification['message'], 0, 50)); ?>">
                                        <i class="fas fa-reply me-1"></i>Reply
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => {
                    window.location.href = '../logout.php';
                });
            }

            const replyModal = document.getElementById('replyModal');
            if (!replyModal) {
                return;
            }

            replyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) {
                    return;
                }

                const recipientEmail = button.getAttribute('data-recipient-email') || '';
                const recipientId = button.getAttribute('data-recipient-id') || '';
                const recipientName = button.getAttribute('data-recipient-name') || 'Teacher';
                const notificationId = button.getAttribute('data-notification-id') || '';
                const notificationPreview = button.getAttribute('data-notification-preview') || 'Notification';

                const replyRecipientEmail = document.getElementById('replyRecipientEmail');
                const replyRecipientId = document.getElementById('replyRecipientId');
                const replyNotificationId = document.getElementById('replyNotificationId');
                const replyRecipientDisplay = document.getElementById('replyRecipientDisplay');
                const replySubject = document.getElementById('replySubject');

                if (replyRecipientEmail) replyRecipientEmail.value = recipientEmail;
                if (replyRecipientId) replyRecipientId.value = recipientId;
                if (replyNotificationId) replyNotificationId.value = notificationId;
                if (replyRecipientDisplay) replyRecipientDisplay.textContent = recipientName + ' (' + recipientEmail + ')';
                if (replySubject) replySubject.value = 'Re: ' + notificationPreview;
            });

            replyModal.addEventListener('hidden.bs.modal', function () {
                const replyForm = document.getElementById('replyForm');
                if (replyForm) {
                    replyForm.reset();
                }
            });
        });
    </script>

    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="replyForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="replyModalLabel">
                            <i class="fas fa-envelope me-2"></i>Reply via Email
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="recipient_email" id="replyRecipientEmail">
                        <input type="hidden" name="recipient_id" id="replyRecipientId">
                        <input type="hidden" name="notification_id" id="replyNotificationId">
                        
                        <div class="mb-3">
                            <label class="form-label">To:</label>
                            <div class="form-control bg-light" id="replyRecipientDisplay"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">From:</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($studentEmail ?: 'No email configured'); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="replySubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="replySubject" name="reply_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="replyMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="replyMessage" name="reply_message" rows="5" required
                                placeholder="Write your reply message here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
