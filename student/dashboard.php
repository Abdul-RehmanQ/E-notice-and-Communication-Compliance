<?php
session_start();
include '../config.php';
include '../send_email.php';
include '../audit_log.php';
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
                // Audit: student created/sent a reply message
                logActivity($conn, $studentUserId, 'student', 'create', 'message', $result['message_id'] ?? null, [
                    'recipient_id' => $recipientId,
                    'recipient_email' => $recipientEmail,
                    'subject' => $subject
                ]);
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

// Fetch notifications for this student (from teachers) with optional filters
$notifications = [];
$where_clauses = ['n.recipient_id = ?'];
$types = 'i';
$params = [$_SESSION['student_id']];

// Fetch student's enrolled offerings for the subject filter dropdown
$enrolledOfferings = [];
$enrollStmt = $conn->prepare("SELECT co.offering_id, c.course_code, c.course_title, co.session, co.section
                              FROM student_course_enrollments sce
                              INNER JOIN course_offerings co ON sce.offering_id = co.offering_id
                              INNER JOIN courses c ON co.course_id = c.course_id
                              WHERE sce.student_id = ? AND sce.status = 'active'");
$enrollStmt->bind_param('i', $_SESSION['student_id']);
$enrollStmt->execute();
$enrollRes = $enrollStmt->get_result();
if ($enrollRes) while ($r = $enrollRes->fetch_assoc()) $enrolledOfferings[] = $r;
$enrollStmt->close();

// Optional offering filter (only show subjects student is enrolled in)
if (!empty($_GET['offering_filter'])) {
    $of = (int)$_GET['offering_filter'];
    if ($of > 0) {
        $where_clauses[] = 'n.offering_id = ?';
        $types .= 'i';
        $params[] = $of;
    }
}

// Optional filters from GET
if (!empty($_GET['from_date'])) {
    $from = preg_replace('/[^0-9\-]/', '', $_GET['from_date']);
    if ($from) {
        $where_clauses[] = 'n.created_at >= ?';
        $types .= 's';
        $params[] = $from . ' 00:00:00';
    }
}
if (!empty($_GET['to_date'])) {
    $to = preg_replace('/[^0-9\-]/', '', $_GET['to_date']);
    if ($to) {
        $where_clauses[] = 'n.created_at <= ?';
        $types .= 's';
        $params[] = $to . ' 23:59:59';
    }
}
if (!empty($_GET['message_search'])) {
    $msg = trim($_GET['message_search']);
    if ($msg !== '') {
        $where_clauses[] = 'n.message LIKE ?';
        $types .= 's';
        $params[] = '%' . $msg . '%';
    }
}

$sql = "SELECT n.*, t.name as teacher_name, u.email as teacher_email, t.teacher_id
                        FROM notifications n
                        INNER JOIN teacher t ON n.sender_id = t.teacher_id
                        INNER JOIN user u ON t.teacher_id = u.user_id
                        WHERE " . implode(' AND ', $where_clauses) . "
                        ORDER BY n.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // bind params dynamically
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

// Log that the student viewed the notifications list (includes active filters)
$filter_summary = [
    'from_date' => $_GET['from_date'] ?? null,
    'to_date' => $_GET['to_date'] ?? null,
    'message_search' => $_GET['message_search'] ?? null,
    'offering_filter' => $_GET['offering_filter'] ?? null
];
logActivity($conn, $studentUserId, 'student', 'view', 'notification_list', null, ['count' => count($notifications), 'filters' => $filter_summary]);

$newNoticeCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Notice - Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'surface-variant': '#e4e2e4',
                        'error': '#ba1a1a',
                        'secondary': '#0040e0',
                        'on-background': '#1b1b1d',
                        'on-secondary-container': '#efefff',
                        'secondary-fixed': '#dde1ff',
                        'on-primary-container': '#7c839b',
                        'on-secondary': '#ffffff',
                        'outline': '#76777d',
                        'on-error': '#ffffff',
                        'surface-dim': '#dcd9db',
                        'tertiary': '#000000',
                        'primary': '#000000',
                        'primary-fixed-dim': '#bec6e0',
                        'surface-container': '#f0edef',
                        'primary-fixed': '#dae2fd',
                        'surface-container-lowest': '#ffffff',
                        'tertiary-fixed': '#6ffbbe',
                        'surface-tint': '#565e74',
                        'on-secondary-fixed-variant': '#0035be',
                        'surface-container-high': '#eae7e9',
                        'tertiary-container': '#002113',
                        'on-primary': '#ffffff',
                        'surface-container-low': '#f6f3f5',
                        'primary-container': '#131b2e',
                        'on-tertiary-container': '#009668',
                        'on-primary-fixed': '#131b2e',
                        'tertiary-fixed-dim': '#4edea3',
                        'surface-container-highest': '#e4e2e4',
                        'surface-bright': '#fcf8fa',
                        'on-tertiary': '#ffffff',
                        'inverse-primary': '#bec6e0',
                        'secondary-container': '#2e5bff',
                        'surface': '#fcf8fa',
                        'on-surface-variant': '#45464d',
                        'background': '#fcf8fa',
                        'on-error-container': '#93000a',
                        'secondary-fixed-dim': '#b8c3ff',
                        'inverse-surface': '#303032',
                        'on-primary-fixed-variant': '#3f465c',
                        'on-surface': '#1b1b1d',
                        'inverse-on-surface': '#f3f0f2',
                        'outline-variant': '#c6c6cd',
                        'error-container': '#ffdad6',
                        'on-secondary-fixed': '#001356'
                    },
                    fontFamily: {
                        h3: ['Sora', 'sans-serif'],
                        h1: ['Sora', 'sans-serif'],
                        'label-caps': ['Sora', 'sans-serif'],
                        'body-md': ['Source Sans 3', 'sans-serif'],
                        'body-sm': ['Source Sans 3', 'sans-serif'],
                        h2: ['Sora', 'sans-serif'],
                        'body-lg': ['Source Sans 3', 'sans-serif'],
                        'data-tabular': ['Source Sans 3', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            background-color: #F8FAFC;
        }
    </style>
</head>

<body class="font-body-md text-on-surface">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden"></div>

    <!-- Fixed Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 shadow-xl shadow-black/20 flex flex-col z-50 transition-transform duration-300 ease-in-out overflow-hidden -translate-x-full lg:translate-x-0 lg:-translate-x-0">
        <div class="p-6 flex items-center gap-3 w-[280px] shrink-0">
            <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
                <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="text-white text-xl font-h1 tracking-tight">E-Notice</span>
                <span class="text-slate-400 font-body-sm text-xs">Academic Administration</span>
            </div>
        </div>
        <nav class="flex-1 px-4 mt-2 flex flex-col gap-1">
            <a class="flex items-center gap-3 px-4 py-3 bg-secondary/10 text-secondary border-l-4 border-secondary transition-all font-h3 text-sm" href="dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>Dashboard
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="community.php">
                <span class="material-symbols-outlined">campaign</span>Community
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="settings.php">
                <span class="material-symbols-outlined">settings</span>Settings
            </a>
        </nav>
        <div class="p-4 mt-auto border-t border-slate-800">
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm">
                <span class="material-symbols-outlined">logout</span>Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main id="mainContent" class="min-h-screen transition-all duration-300 ease-in-out lg:ml-[280px]">
        <!-- Top Bar -->
        <header id="topHeader" class="fixed top-0 right-0 left-0 h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 lg:left-[280px] lg:px-8 z-40 transition-all duration-300 ease-in-out">
            <div class="flex items-center gap-3 sm:gap-4 flex-1">
                <button id="sidebarToggle" type="button" class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-100 hover:text-slate-900 lg:hidden" aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="font-h3 text-sm text-slate-900 leading-none"><?php echo htmlspecialchars($student['name']); ?></p>
                        <p class="font-label-caps text-[10px] tracking-[0.18em] text-secondary mt-1 uppercase">Roll No. <?php echo htmlspecialchars($student['Roll_no']); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Canvas -->
        <div class="pt-20 px-4 pb-10 sm:pt-24 sm:px-6 lg:px-8 lg:pb-12">

            <!-- Flash Messages -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg" role="alert">
                    <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1;">error</span>
                    <div>
                        <p class="font-data-tabular text-on-error-container font-semibold">Error</p>
                        <p class="font-body-sm text-sm text-on-error-container/90"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg" role="alert">
                    <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    <p class="text-emerald-800 font-body-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Bento Dashboard Grid -->
            <div class="grid grid-cols-12 gap-6 mb-8">
                <!-- Profile Summary Card -->
                <div class="col-span-12 lg:col-span-4 bg-white p-4 sm:p-6 rounded-xl border border-slate-200 shadow-sm border-t-4 border-t-secondary">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="font-h2 text-xl text-slate-900">Student Profile</h2>
                        <span class="bg-secondary/10 text-secondary px-2 py-1 rounded text-[10px] font-bold tracking-wider">ACTIVE</span>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[11px] font-label-caps text-slate-400 uppercase">Roll No</p>
                                <p class="font-data-tabular text-slate-700"><?php echo htmlspecialchars($student['Roll_no']); ?></p>
                            </div>
                            <div>
                                <p class="text-[11px] font-label-caps text-slate-400 uppercase">Department</p>
                                <p class="font-data-tabular text-slate-700"><?php echo htmlspecialchars($student['department']); ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[11px] font-label-caps text-slate-400 uppercase">Session</p>
                                <p class="font-data-tabular text-slate-700"><?php echo htmlspecialchars($student['session']); ?></p>
                            </div>
                            <div>
                                <p class="text-[11px] font-label-caps text-slate-400 uppercase">Name</p>
                                <p class="font-data-tabular text-slate-700"><?php echo htmlspecialchars($student['name']); ?></p>
                            </div>
                        </div>
                        <?php if ($studentEmail): ?>
                            <div class="pt-2 border-t border-slate-100">
                                <p class="text-[11px] font-label-caps text-slate-400 uppercase">Registered Email</p>
                                <p class="font-body-sm text-slate-700 truncate"><?php echo htmlspecialchars($studentEmail); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications / E-Notices -->
                <div class="col-span-12 lg:col-span-8 bg-white p-4 sm:p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <h2 class="font-h2 text-xl text-slate-900">Recent Notices</h2>
                            <?php if ($newNoticeCount > 0): ?>
                                <span class="bg-error text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo $newNoticeCount; ?> NEW</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Filters: date range + subject search -->
                    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5 xl:items-end w-full">
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] text-slate-500">From</label>
                            <input type="date" name="from_date" value="<?php echo htmlspecialchars($_GET['from_date'] ?? ''); ?>" class="px-2 py-2 border rounded text-sm w-full h-10">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] text-slate-500">To</label>
                            <input type="date" name="to_date" value="<?php echo htmlspecialchars($_GET['to_date'] ?? ''); ?>" class="px-2 py-2 border rounded text-sm w-full h-10">
                        </div>
                        <div class="flex flex-col gap-2 xl:col-span-2">
                            <label class="text-[11px] text-slate-500">Subject</label>
                            <select name="offering_filter" class="px-3 py-2 border rounded text-sm w-full h-10">
                                <option value="">All Subjects</option>
                                <?php foreach ($enrolledOfferings as $eo): ?>
                                    <option value="<?php echo (int)$eo['offering_id']; ?>" <?php echo (isset($_GET['offering_filter']) && (int)$_GET['offering_filter'] === (int)$eo['offering_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($eo['course_code'] . ' — ' . $eo['course_title'] . ' | ' . $eo['session'] . ' | Sec ' . $eo['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2 xl:col-span-2">
                            <label class="text-[11px] text-slate-500">Text</label>
                            <input type="text" name="message_search" placeholder="Filter by subject or text..." value="<?php echo htmlspecialchars($_GET['message_search'] ?? ''); ?>" class="w-full px-3 py-2 border rounded text-sm min-w-0 h-10">
                        </div>
                        <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                            <button type="submit" class="px-3 py-2 bg-secondary text-white rounded text-sm h-10 w-full">Filter</button>
                            <a href="dashboard.php" class="px-3 py-2 border rounded text-sm h-10 flex items-center justify-center w-full">Clear</a>
                        </div>
                    </form>

                    <?php if (empty($notifications)): ?>
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <span class="material-symbols-outlined text-slate-300 text-5xl mb-3">notifications_none</span>
                            <p class="font-body-sm text-slate-500">No notices yet. Check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="group p-4 bg-slate-50 border border-slate-200 rounded-lg hover:border-secondary/30 hover:bg-white transition-all">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center text-secondary font-bold text-sm">
                                                <?php echo strtoupper(substr($notification['teacher_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-h3 text-sm text-slate-900"><?php echo htmlspecialchars($notification['teacher_name']); ?></p>
                                                <p class="text-[11px] font-label-caps text-slate-500">TEACHER</p>
                                            </div>
                                        </div>
                                        <span class="text-[11px] font-data-tabular text-slate-400"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                    <p class="font-body-sm text-slate-600 mb-4 line-clamp-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    <div class="flex items-center gap-2">
                                        <button class="flex items-center gap-1.5 px-3 py-1.5 bg-secondary text-white rounded text-xs font-h3 hover:bg-on-secondary-fixed-variant transition-colors open-reply-modal"
                                            data-recipient-id="<?php echo $notification['teacher_id']; ?>"
                                            data-recipient-name="<?php echo htmlspecialchars($notification['teacher_name']); ?>"
                                            data-notification-id="<?php echo $notification['notification_id']; ?>"
                                            data-notification-preview="<?php echo htmlspecialchars(substr($notification['message'], 0, 50)); ?>">
                                            <span class="material-symbols-outlined text-[16px]">reply</span>Reply
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Reply Modal -->
    <div class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4" id="replyModal">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-[#F8FAFC] px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <div>
                    <h3 class="font-h3 text-slate-900">Reply to Notice</h3>
                    <p class="text-[11px] font-label-caps text-slate-500" id="modalSubtitle">RE: NOTICE</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600" id="closeModal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="recipient_id" id="replyRecipientId">
                    <input type="hidden" name="notification_id" id="replyNotificationId">
                    <div>
                        <label class="block text-[11px] font-label-caps text-slate-500 mb-1">To</label>
                        <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600" id="replyRecipientDisplay">-</div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-label-caps text-slate-500 mb-1">Subject</label>
                        <input class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-secondary/20 outline-none" type="text" name="reply_subject" id="replySubject" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-label-caps text-slate-500 mb-1">Message</label>
                        <textarea class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-secondary/20 outline-none resize-none" name="reply_message" placeholder="Type your message here..." rows="4" required></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button class="px-4 py-2 text-slate-600 text-sm font-h3 hover:bg-slate-50 rounded-lg transition-colors" type="button" id="cancelModal">Cancel</button>
                        <button class="px-6 py-2 bg-secondary text-white text-sm font-h3 rounded-lg hover:bg-on-secondary-fixed-variant shadow-lg shadow-secondary/20 transition-all" type="submit" name="send_reply">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');
        const topHeader = document.getElementById('topHeader');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const replyModal = document.getElementById('replyModal');
        const closeModal = document.getElementById('closeModal');
        const cancelModal = document.getElementById('cancelModal');

        let mobileSidebarOpen = false;

        function syncSidebarState() {
            const isDesktop = window.innerWidth >= 1024;

            if (isDesktop) {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                sidebarOverlay.classList.add('hidden');
                mainContent.classList.remove('ml-0');
                topHeader.classList.remove('left-0');
                topHeader.classList.add('lg:left-[280px]');
                return;
            }

            sidebar.classList.toggle('-translate-x-full', !mobileSidebarOpen);
            sidebar.classList.toggle('translate-x-0', mobileSidebarOpen);
            sidebarOverlay.classList.toggle('hidden', !mobileSidebarOpen);
            mainContent.classList.add('ml-0');
            topHeader.classList.add('left-0');
            topHeader.classList.remove('lg:left-[280px]');
        }

        sidebarToggle.addEventListener('click', () => {
            mobileSidebarOpen = !mobileSidebarOpen;
            syncSidebarState();
        });

        sidebarOverlay.addEventListener('click', () => {
            mobileSidebarOpen = false;
            syncSidebarState();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                mobileSidebarOpen = false;
            }
            syncSidebarState();
        });

        syncSidebarState();

        document.querySelectorAll('.open-reply-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('replyRecipientId').value = this.dataset.recipientId;
                document.getElementById('replyNotificationId').value = this.dataset.notificationId;
                document.getElementById('replyRecipientDisplay').textContent = this.dataset.recipientName;
                document.getElementById('replySubject').value = 'Re: ' + this.dataset.notificationPreview;
                document.getElementById('modalSubtitle').textContent = 'RE: ' + this.dataset.notificationPreview.toUpperCase();
                replyModal.classList.remove('hidden');
            });
        });

        [closeModal, cancelModal].forEach(el => {
            el.addEventListener('click', () => replyModal.classList.add('hidden'));
        });

        replyModal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    </script>
</body>

</html>