<?php
session_start();
include '../config.php';
require_once __DIR__ . '/teacher_guard.php';

$teacher = requireTeacherIdentity($conn);

$success = '';
$error = '';

if (isset($_SESSION['teacher_flash']) && is_array($_SESSION['teacher_flash'])) {
    $flashType = $_SESSION['teacher_flash']['type'] ?? '';
    $flashMessage = $_SESSION['teacher_flash']['message'] ?? '';
    if ($flashType === 'success') $success = (string)$flashMessage;
    elseif ($flashType === 'error') $error = (string)$flashMessage;
    unset($_SESSION['teacher_flash']);
}

if (!function_exists('teacherRedirectWithFlash')) {
    function teacherRedirectWithFlash(string $type, string $message): void {
        $_SESSION['teacher_flash'] = ['type' => $type, 'message' => $message];
        header('Location: dashboard.php');
        exit();
    }
}

$assignedCourses = [];
$assignedCourseStmt = $conn->prepare("SELECT DISTINCT co.offering_id, co.session, co.section, co.semester_no, c.course_id, c.course_code, c.course_title, c.department
                                      FROM teacher_course_assignments tca
                                      INNER JOIN course_offerings co ON co.offering_id = tca.offering_id
                                      INNER JOIN courses c ON c.course_id = tca.course_id
                                      WHERE tca.teacher_id = ?
                                      ORDER BY c.course_code ASC, co.session ASC, co.section ASC");
$assignedCourseStmt->bind_param("i", $teacher['teacher_id']);
$assignedCourseStmt->execute();
$assignedCourseResult = $assignedCourseStmt->get_result();
if ($assignedCourseResult) while ($row = $assignedCourseResult->fetch_assoc()) $assignedCourses[] = $row;
$assignedCourseStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $selectedOfferingId = (int)($_POST['offering_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($selectedOfferingId <= 0) $error = 'Please select a class offering.';
    elseif ($message === '') $error = 'Please enter a notification message.';
    elseif (mb_strlen($message) > 2000) $error = 'Message is too long. Maximum 2000 characters allowed.';
    else {
        $selectedCourse = null;
        foreach ($assignedCourses as $course) {
            if ((int)$course['offering_id'] === $selectedOfferingId) { $selectedCourse = $course; break; }
        }

        $assignmentCheckStmt = $conn->prepare("SELECT assignment_id FROM teacher_course_assignments WHERE teacher_id = ? AND offering_id = ? LIMIT 1");
        $assignmentCheckStmt->bind_param("ii", $teacher['teacher_id'], $selectedOfferingId);
        $assignmentCheckStmt->execute();
        $isAssignedToOffering = $assignmentCheckStmt->get_result()->num_rows > 0;
        $assignmentCheckStmt->close();

        if (!$isAssignedToOffering) {
            $error = 'You can only send notifications for class offerings assigned to you.';
        } else {
            $recipientIds = [];
            $recipientStmt = $conn->prepare("SELECT DISTINCT sce.student_id FROM student_course_enrollments sce WHERE sce.status = 'active' AND sce.offering_id = ?");
            $recipientStmt->bind_param("i", $selectedOfferingId);
            $recipientStmt->execute();
            $recipientResult = $recipientStmt->get_result();
            if ($recipientResult) while ($recipient = $recipientResult->fetch_assoc()) $recipientIds[] = (int)$recipient['student_id'];
            $recipientStmt->close();

            if (empty($recipientIds)) {
                $error = 'No active enrolled students found for the selected course.';
            } else {
                $insertStmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, message) VALUES (?, ?, ?)");
                $insertedCount = 0;
                foreach ($recipientIds as $recipientId) {
                    $insertStmt->bind_param("iis", $recipientId, $teacher['teacher_id'], $message);
                    if ($insertStmt->execute()) $insertedCount++;
                }
                $insertStmt->close();

                if ($insertedCount > 0) {
                    $courseCode = $selectedCourse['course_code'] ?? 'Selected Course';
                    $semesterNo = (int)($selectedCourse['semester_no'] ?? 0);
                    $sessionLabel = $selectedCourse['session'] ?? 'N/A';
                    $sectionLabel = $selectedCourse['section'] ?? 'N/A';
                    $success = "Notification sent for {$courseCode} (Session {$sessionLabel}, Semester {$semesterNo}, Section {$sectionLabel}) to {$insertedCount} student(s).";
                } else {
                    $error = 'Failed to send notification. Please try again.';
                }
            }
        }
    }

    if ($error !== '') teacherRedirectWithFlash('error', $error);
    if ($success !== '') teacherRedirectWithFlash('success', $success);
    teacherRedirectWithFlash('error', 'Unable to process notification request.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notificationIdToDelete = (int)($_POST['notification_id'] ?? 0);

    if ($notificationIdToDelete <= 0) {
        $error = 'Please select a notification to delete.';
    } else {
        $findStmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE notification_id = ? AND sender_id = ? LIMIT 1");
        $findStmt->bind_param("ii", $notificationIdToDelete, $teacher['teacher_id']);
        $findStmt->execute();
        $batchToDelete = $findStmt->get_result()->fetch_assoc();
        $findStmt->close();

        if (!$batchToDelete) {
            $error = 'Unable to delete the selected notification.';
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM notifications WHERE sender_id = ? AND message = ? AND created_at = ?");
            $deleteStmt->bind_param("iss", $teacher['teacher_id'], $batchToDelete['message'], $batchToDelete['created_at']);
            $deleteStmt->execute();
            if ($deleteStmt->affected_rows > 0) $success = 'Notification deleted successfully.';
            else $error = 'Unable to delete the selected notification.';
            $deleteStmt->close();
        }
    }

    if ($error !== '') teacherRedirectWithFlash('error', $error);
    if ($success !== '') teacherRedirectWithFlash('success', $success);
    teacherRedirectWithFlash('error', 'Unable to process delete request.');
}

$sentNotifications = [];
$sentStmt = $conn->prepare("SELECT MIN(n.notification_id) AS notification_id, n.message, n.created_at, COUNT(*) AS recipient_count
                            FROM notifications n WHERE n.sender_id = ?
                            GROUP BY n.message, n.created_at ORDER BY n.created_at DESC LIMIT 100");
$sentStmt->bind_param("i", $teacher['teacher_id']);
$sentStmt->execute();
$sentResult = $sentStmt->get_result();
if ($sentResult) while ($row = $sentResult->fetch_assoc()) $sentNotifications[] = $row;
$sentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - E-Notice</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                colors: {
                    'error': '#ba1a1a', 'secondary': '#0040e0', 'secondary-container': '#2e5bff',
                    'on-secondary-fixed-variant': '#0035be', 'on-primary-container': '#7c839b',
                    'on-surface': '#1b1b1d', 'on-surface-variant': '#45464d',
                    'error-container': '#ffdad6', 'on-error-container': '#93000a',
                    'tertiary-fixed': '#6ffbbe', 'on-tertiary-fixed': '#002113',
                    'on-tertiary-container': '#009668', 'surface-container-low': '#f6f3f5'
                },
                fontFamily: {
                    h3: ['Sora','sans-serif'], h2: ['Sora','sans-serif'], h1: ['Sora','sans-serif'],
                    'label-caps': ['Sora','sans-serif'], 'body-md': ['Source Sans 3','sans-serif'],
                    'body-sm': ['Source Sans 3','sans-serif'], 'data-tabular': ['Source Sans 3','sans-serif']
                }
            }}
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { background-color: #F8FAFC; }
    </style>
</head>
<body class="font-body-md text-on-surface">

<!-- Sidebar -->
<aside class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl">
    <div class="p-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
            <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
        </div>
        <div>
            <h1 class="text-white text-xl font-bold tracking-tight font-h1">E-Notice</h1>
            <p class="text-slate-400 text-xs font-label-caps">Academic Administration</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-4 space-y-1">
        <a class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-600 transition-all font-h3 text-sm" href="dashboard.php">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="community.php">
            <span class="material-symbols-outlined">campaign</span>Community
        </a>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="settings.php">
            <span class="material-symbols-outlined">settings</span>Settings
        </a>
    </nav>
    <div class="px-4 py-4 border-t border-slate-800">
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm">
            <span class="material-symbols-outlined">logout</span>Logout
        </a>
    </div>
</aside>

<!-- Top Bar -->
<header class="fixed top-0 right-0 left-[280px] h-16 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between px-8 z-40 shadow-sm">
    <div class="flex items-center gap-4 w-1/3">
        <div class="relative w-full">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input class="w-full bg-white border border-slate-200 rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none font-body-sm" placeholder="Search records, notices, or students..." type="text">
        </div>
    </div>
    <div class="flex items-center gap-6">
        <div class="flex items-center gap-2">
            <button class="hover:bg-slate-100 rounded-lg p-2 transition-all relative">
                <span class="material-symbols-outlined text-slate-600">notifications</span>
                <?php if (!empty($sentNotifications)): ?>
                <span class="absolute top-2 right-2 w-2 h-2 bg-error rounded-full"></span>
                <?php endif; ?>
            </button>
            <button class="hover:bg-slate-100 rounded-lg p-2 transition-all">
                <span class="material-symbols-outlined text-slate-600">help_center</span>
            </button>
        </div>
        <div class="h-8 w-[1px] bg-slate-200"></div>
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-slate-900 font-bold text-sm font-body-sm leading-tight"><?php echo htmlspecialchars($teacher['name']); ?></p>
                <span class="text-[10px] font-label-caps bg-secondary/10 text-secondary px-2 py-0.5 rounded border border-secondary/20">FACULTY</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                <?php echo strtoupper(substr($teacher['name'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="ml-[280px] mt-16 p-8 min-h-screen">
    <!-- Header -->
    <div class="mb-8">
        <h2 class="font-h2 text-2xl font-bold text-slate-900 mb-1">E-Notice Management</h2>
        <p class="text-slate-500 font-body-sm">Broadcast critical academic updates and compliance notices to your assigned classes.</p>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
        <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1;">error</span>
        <div>
            <p class="font-data-tabular text-on-error-container font-semibold text-sm">Error</p>
            <p class="font-body-sm text-sm text-on-error-container/90"><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
        <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        <p class="text-emerald-800 font-body-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-8">
        <!-- Left: Send Form -->
        <div class="col-span-12 lg:col-span-5 space-y-6">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden border-t-4 border-t-secondary">
                <div class="p-6 border-b border-slate-100">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-secondary">send</span>
                        <h3 class="font-h3 text-lg text-slate-900">Compose New Notice</h3>
                    </div>
                    <p class="text-xs text-slate-400 font-body-sm">Target specific enrolled classes with compliance updates.</p>
                </div>
                <form class="p-6 space-y-5" method="POST" action="">
                    <div>
                        <label class="block text-xs font-label-caps text-slate-500 mb-2">TARGET CLASS OFFERING</label>
                        <select name="offering_id" class="w-full bg-white border border-slate-200 rounded-lg py-2.5 px-4 text-sm focus:ring-2 focus:ring-secondary/20 outline-none font-body-sm" required>
                            <option value="" selected disabled>Select assigned class offering</option>
                            <?php foreach ($assignedCourses as $course): ?>
                            <option value="<?php echo (int)$course['offering_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                | <?php echo htmlspecialchars($course['session']); ?>
                                | Sem <?php echo (int)$course['semester_no']; ?>
                                | Sec <?php echo htmlspecialchars($course['section']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($assignedCourses)): ?>
                        <p class="text-xs text-error mt-1 font-body-sm">No assigned courses found. Ask admin to assign a course first.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-label-caps text-slate-500 mb-2">MESSAGE CONTENT</label>
                        <textarea name="message" class="w-full bg-white border border-slate-200 rounded-lg py-2.5 px-4 text-sm focus:ring-2 focus:ring-secondary/20 outline-none font-body-sm resize-none" placeholder="Enter notice details here..." rows="5" required></textarea>
                    </div>
                    <button class="w-full bg-secondary text-white py-3 rounded-lg font-h3 text-sm font-semibold hover:bg-on-secondary-fixed-variant transition-all flex items-center justify-center gap-2 shadow-md shadow-blue-600/20 <?php echo empty($assignedCourses) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                        type="submit" name="send_notification" <?php echo empty($assignedCourses) ? 'disabled' : ''; ?>>
                        <span class="material-symbols-outlined text-lg">send</span>BROADCAST NOTICE
                    </button>
                </form>
            </section>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-label-caps text-slate-400 mb-1">TOTAL SENT</p>
                    <p class="text-2xl font-h1 font-bold text-slate-900"><?php echo count($sentNotifications); ?></p>
                    <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2">
                        <div class="bg-secondary h-1.5 rounded-full" style="width: <?php echo min(100, count($sentNotifications) * 5); ?>%"></div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-label-caps text-slate-400 mb-1">COURSES ASSIGNED</p>
                    <p class="text-2xl font-h1 font-bold text-slate-900"><?php echo count($assignedCourses); ?></p>
                    <p class="text-[10px] text-on-tertiary-container mt-2 flex items-center gap-1 font-body-sm">
                        <span class="material-symbols-outlined text-[12px]">school</span>Active assignments
                    </p>
                </div>
            </div>
        </div>

        <!-- Right: History Table -->
        <div class="col-span-12 lg:col-span-7">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-h3 text-lg text-slate-900">Broadcast History</h3>
                        <p class="text-xs text-slate-400 font-body-sm">Audit trail of all dispatched notifications.</p>
                    </div>
                    <!-- Delete form trigger -->
                    <?php if (!empty($sentNotifications)): ?>
                    <button onclick="document.getElementById('deletePanel').classList.toggle('hidden')"
                        class="bg-error/10 text-error px-3 py-2 rounded-lg text-xs font-label-caps hover:bg-error hover:text-white transition-all flex items-center gap-2 border border-error/20">
                        <span class="material-symbols-outlined text-[16px]">delete</span>DELETE
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Delete Panel -->
                <?php if (!empty($sentNotifications)): ?>
                <div id="deletePanel" class="hidden p-4 bg-slate-50 border-b border-slate-200">
                    <form method="POST" class="flex items-center gap-3" onsubmit="return confirm('Delete this entire notification batch? This cannot be undone.');">
                        <select name="notification_id" class="flex-1 text-sm border border-slate-200 rounded-lg py-2 px-3 focus:ring-2 focus:ring-secondary/20 outline-none" required>
                            <option value="" disabled selected>-- Select notification to delete --</option>
                            <?php foreach ($sentNotifications as $n): ?>
                            <option value="<?php echo (int)$n['notification_id']; ?>">
                                <?php
                                $preview = mb_substr($n['message'], 0, 60);
                                $preview = mb_strlen($n['message']) > 60 ? $preview . '...' : $preview;
                                echo htmlspecialchars($preview . ' | ' . date('M d, Y', strtotime($n['created_at'])));
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="delete_notification" class="px-4 py-2 bg-error text-white rounded-lg text-xs font-label-caps hover:opacity-90 transition-all">
                            Confirm Delete
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <?php if (empty($sentNotifications)): ?>
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <span class="material-symbols-outlined text-slate-300 text-5xl mb-3">notifications_none</span>
                        <p class="font-body-sm text-slate-500">No notifications sent yet.</p>
                    </div>
                    <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 font-label-caps text-[11px] text-slate-500 tracking-wider">NOTICE MESSAGE</th>
                                <th class="px-6 py-4 font-label-caps text-[11px] text-slate-500 tracking-wider text-center">RECIPIENTS</th>
                                <th class="px-6 py-4 font-label-caps text-[11px] text-slate-500 tracking-wider">TIMESTAMP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($sentNotifications as $notification): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-800 line-clamp-1"><?php echo htmlspecialchars(mb_substr($notification['message'], 0, 60)) . (mb_strlen($notification['message']) > 60 ? '...' : ''); ?></p>
                                    <p class="text-xs text-slate-400 font-body-sm italic mt-0.5"><?php echo htmlspecialchars(mb_substr($notification['message'], 0, 80)); ?></p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-label-caps bg-tertiary-fixed text-on-tertiary-fixed px-2 py-1 rounded-full">
                                        <span class="material-symbols-outlined text-[12px]">group</span>
                                        <?php echo (int)$notification['recipient_count']; ?> STUDENTS
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[11px] font-data-tabular text-slate-600"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></p>
                                    <p class="text-[10px] text-slate-400"><?php echo date('h:i A', strtotime($notification['created_at'])); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between mt-auto">
                    <p class="text-xs text-slate-500 font-body-sm">Showing <?php echo count($sentNotifications); ?> records</p>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400 font-label-caps">
                        <span class="material-symbols-outlined text-[14px]">security</span>Encrypted Delivery
                        <span class="ml-2 material-symbols-outlined text-[14px]">verified_user</span>FERPA Compliant
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
