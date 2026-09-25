<?php
session_start();
include '../config.php';
include '../audit_log.php';
require_once __DIR__ . '/supervisor_guard.php';

$supervisor = requireSupervisorIdentity($conn);
$supervisorId = (int)$supervisor['supervisor_id'];
$supervisorDepartment = trim((string)($supervisor['department'] ?? ''));
$supervisorDepartmentNormalized = mb_strtolower($supervisorDepartment);

$success = '';
$error = '';

if (isset($_SESSION['supervisor_flash']) && is_array($_SESSION['supervisor_flash'])) {
    $flashType = $_SESSION['supervisor_flash']['type'] ?? '';
    $flashMessage = $_SESSION['supervisor_flash']['message'] ?? '';
    if ($flashType === 'success') $success = (string)$flashMessage;
    elseif ($flashType === 'error') $error = (string)$flashMessage;
    unset($_SESSION['supervisor_flash']);
}

if (!function_exists('supervisorRedirectWithFlash')) {
    function supervisorRedirectWithFlash(string $type, string $message): void {
        $_SESSION['supervisor_flash'] = ['type' => $type, 'message' => $message];
        header('Location: dashboard.php');
        exit();
    }
}

$conn->query("DELETE FROM posts WHERE expires_at < NOW()");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_post'])) {
    if ($supervisorDepartment === '') supervisorRedirectWithFlash('error', 'Your department profile is missing.');
    $postId = (int)$_POST['post_id'];
    $checkStmt = $conn->prepare("SELECT p.post_id FROM posts p LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE p.post_id = ? AND p.status = 'pending' AND p.expires_at > NOW() AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ? LIMIT 1");
    $checkStmt->bind_param("is", $postId, $supervisorDepartmentNormalized);
    $checkStmt->execute();
    $postExists = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();
    if (!$postExists) supervisorRedirectWithFlash('error', 'Post not found, expired, or outside your department.');
    $stmt = $conn->prepare("UPDATE posts p LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id SET p.status = 'approved' WHERE p.post_id = ? AND p.status = 'pending' AND p.expires_at > NOW() AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ?");
    $stmt->bind_param("is", $postId, $supervisorDepartmentNormalized);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $logStmt = $conn->prepare("INSERT INTO post_reviews (post_id, supervisor_id, action) VALUES (?, ?, 'approved')");
        $logStmt->bind_param("ii", $postId, $supervisorId);
        $logStmt->execute();
        $logStmt->close();
        logActivity($conn, $supervisorId, 'community_supervisor', 'approve_post', 'posts', $postId, [
            'post_id' => $postId,
            'supervisor_id' => $supervisorId,
            'status' => 'approved'
        ]);
        supervisorRedirectWithFlash('success', 'Post approved successfully!');
    } else supervisorRedirectWithFlash('error', 'Failed to approve post.');
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_post'])) {
    if ($supervisorDepartment === '') supervisorRedirectWithFlash('error', 'Your department profile is missing.');
    $postId = (int)$_POST['post_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');
    $checkStmt = $conn->prepare("SELECT p.post_id FROM posts p LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE p.post_id = ? AND p.status = 'pending' AND p.expires_at > NOW() AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ? LIMIT 1");
    $checkStmt->bind_param("is", $postId, $supervisorDepartmentNormalized);
    $checkStmt->execute();
    $postExists = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();
    if (!$postExists) supervisorRedirectWithFlash('error', 'Post not found, expired, or outside your department.');
    $logStmt = $conn->prepare("INSERT INTO post_reviews (post_id, supervisor_id, action, rejection_reason) VALUES (?, ?, 'rejected', ?)");
    $logStmt->bind_param("iis", $postId, $supervisorId, $reason);
    $logStmt->execute();
    logActivity($conn, $supervisorId, 'community_supervisor', 'reject_post', 'posts', $postId, [
        'post_id' => $postId,
        'supervisor_id' => $supervisorId,
        'status' => 'rejected',
        'rejection_reason' => $reason
    ]);
    $logStmt->close();
    $stmt = $conn->prepare("DELETE p FROM posts p LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE p.post_id = ? AND p.status = 'pending' AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ?");
    $stmt->bind_param("is", $postId, $supervisorDepartmentNormalized);
    if ($stmt->execute()) supervisorRedirectWithFlash('success', 'Post rejected and deleted.');
    else supervisorRedirectWithFlash('error', 'Failed to reject post.');
    $stmt->close();
}

$pendingPosts = [];
$pendingStmt = $conn->prepare("SELECT p.*, u.email, COALESCE(s.name, t.name) as poster_name, s.Roll_no as poster_roll, COALESCE(NULLIF(TRIM(s.department), ''), NULLIF(TRIM(t.department), '')) as poster_department FROM posts p LEFT JOIN user u ON p.user_id = u.user_id LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE p.status = 'pending' AND p.expires_at > NOW() AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ? ORDER BY p.created_at ASC");
$pendingStmt->bind_param("s", $supervisorDepartmentNormalized);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
if ($pendingResult) while ($row = $pendingResult->fetch_assoc()) $pendingPosts[] = $row;
$pendingStmt->close();

$recentReviews = [];
$reviewStmt = $conn->prepare("SELECT pr.*, p.content as post_content, COALESCE(s.name, t.name) as poster_name FROM post_reviews pr LEFT JOIN posts p ON pr.post_id = p.post_id LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE pr.supervisor_id = ? ORDER BY pr.reviewed_at DESC LIMIT 20");
$reviewStmt->bind_param("i", $supervisorId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();
if ($reviewResult) while ($row = $reviewResult->fetch_assoc()) $recentReviews[] = $row;
$reviewStmt->close();

$statsStmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM posts p LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id WHERE p.status = 'pending' AND p.expires_at > NOW() AND COALESCE(NULLIF(LOWER(TRIM(s.department)), ''), NULLIF(LOWER(TRIM(t.department)), '')) = ?) as pending_count, (SELECT COUNT(*) FROM post_reviews WHERE supervisor_id = ? AND action = 'approved' AND DATE(reviewed_at) = CURDATE()) as approved_today, (SELECT COUNT(*) FROM post_reviews WHERE supervisor_id = ? AND action = 'rejected' AND DATE(reviewed_at) = CURDATE()) as rejected_today");
$statsStmt->bind_param("sii", $supervisorDepartmentNormalized, $supervisorId, $supervisorId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard - E-Notice</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    'error': '#ba1a1a', 'secondary': '#0040e0',
                    'error-container': '#ffdad6', 'on-error-container': '#93000a',
                    'on-primary-container': '#7c839b', 'on-surface-variant': '#45464d'
                },
                fontFamily: {
                    h1: ['Sora','sans-serif'], h2: ['Sora','sans-serif'], h3: ['Sora','sans-serif'],
                    'label-caps': ['Sora','sans-serif'], 'body-md': ['Source Sans 3','sans-serif'],
                    'body-sm': ['Source Sans 3','sans-serif'], 'data-tabular': ['Source Sans 3','sans-serif']
                }
            }}
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { background-color: #F8FAFC; font-family: 'Source Sans 3', sans-serif; }
    </style>
</head>
<body class="text-slate-800">

<div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0 lg:-translate-x-0">
    <div class="p-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
            <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
        </div>
        <div>
            <h1 class="text-white text-xl font-bold font-h1">E-Notice</h1>
            <p class="text-slate-400 text-xs uppercase tracking-widest">Supervisor Panel</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-4 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-600 font-h3 text-sm">
            <span class="material-symbols-outlined">fact_check</span>
            Moderation Queue
            <?php if ($stats['pending_count'] > 0): ?>
            <span class="ml-auto bg-error text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $stats['pending_count']; ?></span>
            <?php endif; ?>
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm">
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
<header id="topHeader" class="fixed top-0 right-0 left-0 h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 z-40 shadow-sm transition-all duration-300 ease-in-out lg:left-[280px] lg:px-8">
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" type="button" class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-100 hover:text-slate-900 lg:hidden" aria-label="Toggle sidebar">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <h2 class="text-slate-900 font-black text-lg font-h2">Moderation Center</h2>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="font-bold text-slate-900 text-sm font-h3 leading-none"><?php echo htmlspecialchars($supervisor['name']); ?></p>
                <p class="text-[10px] text-secondary font-bold uppercase tracking-[0.18em]"><?php echo htmlspecialchars($supervisorDepartment ?: 'N/A'); ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                <?php echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main id="mainContent" class="mt-16 p-4 sm:p-6 lg:ml-[280px] lg:mt-16 lg:p-8 min-h-screen transition-all duration-300 ease-in-out">

    <!-- Flash Messages -->
    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg flex gap-3 items-start">
        <span class="material-symbols-outlined text-red-500" style="font-variation-settings:'FILL' 1">error</span>
        <p class="text-red-800 text-sm font-body-sm"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg flex gap-3 items-start">
        <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings:'FILL' 1">check_circle</span>
        <p class="text-emerald-800 text-sm font-body-sm"><?php echo htmlspecialchars($success); ?></p>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200 border-t-2 border-t-amber-400 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-xs font-label-caps uppercase mb-1">Pending Posts</p>
                <h3 class="text-3xl font-h1 font-bold text-slate-900"><?php echo $stats['pending_count']; ?></h3>
                <p class="text-amber-600 text-xs font-semibold mt-1">Awaiting review</p>
            </div>
            <div class="w-14 h-14 bg-amber-50 rounded-full flex items-center justify-center text-amber-500">
                <span class="material-symbols-outlined text-3xl">pending_actions</span>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 border-t-2 border-t-emerald-500 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-xs font-label-caps uppercase mb-1">Approved Today</p>
                <h3 class="text-3xl font-h1 font-bold text-slate-900"><?php echo $stats['approved_today']; ?></h3>
                <p class="text-emerald-600 text-xs font-semibold mt-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>Compliant content
                </p>
            </div>
            <div class="w-14 h-14 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600">
                <span class="material-symbols-outlined text-3xl">verified</span>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 border-t-2 border-t-red-400 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-xs font-label-caps uppercase mb-1">Rejected Today</p>
                <h3 class="text-3xl font-h1 font-bold text-slate-900"><?php echo $stats['rejected_today']; ?></h3>
                <p class="text-slate-400 text-xs font-medium mt-1">Policy violations</p>
            </div>
            <div class="w-14 h-14 bg-red-50 rounded-full flex items-center justify-center text-error">
                <span class="material-symbols-outlined text-3xl">block</span>
            </div>
        </div>
    </div>

    <!-- Pending Posts Queue -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/50">
            <span class="material-symbols-outlined text-emerald-600">fact_check</span>
            <h3 class="font-h3 text-lg text-slate-900">Pending Moderation Queue</h3>
            <span class="ml-auto text-xs text-slate-400 font-body-sm"><?php echo count($pendingPosts); ?> post(s) awaiting review</span>
        </div>

        <?php if (empty($pendingPosts)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <span class="material-symbols-outlined text-emerald-300 text-5xl mb-3">task_alt</span>
            <p class="text-slate-500 font-body-sm">All clear! No pending posts to review.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($pendingPosts as $post): ?>
            <div class="p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-sm">
                            <?php echo strtoupper(substr($post['poster_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($post['poster_name'] ?? 'Unknown'); ?>
                                <?php if (!empty($post['poster_roll'])): ?>
                                <span class="text-slate-400 font-normal">(<?php echo htmlspecialchars($post['poster_roll']); ?>)</span>
                                <?php endif; ?>
                            </p>
                            <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
                                <span><?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></span>
                                <span>•</span>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold uppercase"><?php echo $post['scope'] == 'all' ? 'All University' : htmlspecialchars($post['poster_department'] ?? 'Dept'); ?></span>
                                <?php $daysLeft = ceil((strtotime($post['expires_at']) - time()) / 86400); ?>
                                <span class="<?php echo $daysLeft <= 3 ? 'text-red-500' : 'text-slate-400'; ?>">Expires in <?php echo $daysLeft; ?>d</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($post['content'])): ?>
                <div class="bg-slate-50 rounded-lg p-4 mb-4 border border-slate-100">
                    <p class="text-slate-700 text-sm font-body-sm"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($post['image_data'])): ?>
                <div class="mb-4">
                    <img src="data:<?php echo $post['image_type']; ?>;base64,<?php echo base64_encode($post['image_data']); ?>" class="rounded-lg border border-slate-100 max-h-48 object-cover">
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-3">
                    <form method="POST" class="inline">
                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                        <button type="submit" name="approve_post" class="flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>Approve
                        </button>
                    </form>
                    <button type="button" onclick="document.getElementById('rejectPanel<?php echo $post['post_id']; ?>').classList.toggle('hidden')"
                        class="flex items-center gap-2 px-5 py-2 bg-error/10 text-error border border-error/20 rounded-lg text-sm font-bold hover:bg-error hover:text-white transition-all">
                        <span class="material-symbols-outlined text-[18px]">cancel</span>Reject
                    </button>
                </div>

                <!-- Inline Reject Panel -->
                <div id="rejectPanel<?php echo $post['post_id']; ?>" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <form method="POST">
                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Rejection Reason (optional)</label>
                        <textarea name="rejection_reason" rows="3" class="w-full border border-slate-200 rounded-lg text-sm p-3 focus:ring-2 focus:ring-red-500/20 outline-none mb-3 font-body-sm" placeholder="Describe why this content violates department standards..."></textarea>
                        <div class="flex gap-3">
                            <button type="submit" name="reject_post" class="px-5 py-2 bg-error text-white rounded-lg text-sm font-bold hover:opacity-90 transition-all">
                                Confirm Rejection
                            </button>
                            <button type="button" onclick="document.getElementById('rejectPanel<?php echo $post['post_id']; ?>').classList.add('hidden')"
                                class="px-5 py-2 text-slate-500 text-sm font-bold hover:text-slate-800 transition-all">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Recent Reviews -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-h3 text-lg text-slate-900">Recent Decision History</h3>
        </div>
        <?php if (empty($recentReviews)): ?>
        <div class="py-10 text-center text-slate-400 font-body-sm">No reviews yet.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="px-6 py-3 font-label-caps text-[11px] text-slate-500 uppercase tracking-wider">Decision</th>
                        <th class="px-6 py-3 font-label-caps text-[11px] text-slate-500 uppercase tracking-wider">Post Preview</th>
                        <th class="px-6 py-3 font-label-caps text-[11px] text-slate-500 uppercase tracking-wider">Author</th>
                        <th class="px-6 py-3 font-label-caps text-[11px] text-slate-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recentReviews as $review): ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-6 py-4">
                            <?php if ($review['action'] == 'approved'): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-label-caps bg-emerald-50 text-emerald-700 px-2 py-1 rounded-full border border-emerald-100">
                                <span class="material-symbols-outlined text-[12px]">check_circle</span>APPROVED
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-label-caps bg-red-50 text-error px-2 py-1 rounded-full border border-red-100">
                                <span class="material-symbols-outlined text-[12px]">cancel</span>REJECTED
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 font-body-sm max-w-xs">
                            <?php
                            $preview = !empty($review['post_content']) ? substr($review['post_content'], 0, 50) : '[Image Post]';
                            echo htmlspecialchars($preview) . (strlen($review['post_content'] ?? '') > 50 ? '...' : '');
                            ?>
                        </td>
                        <td class="px-6 py-4 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($review['poster_name'] ?? 'Unknown'); ?></td>
                        <td class="px-6 py-4 text-[11px] text-slate-500 font-data-tabular"><?php echo date('M d, h:i A', strtotime($review['reviewed_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const topHeader = document.getElementById('topHeader');
    const mainContent = document.getElementById('mainContent');
    let mobileSidebarOpen = false;

    function syncSidebarState() {
        const isDesktop = window.innerWidth >= 1024;

        if (isDesktop) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            sidebarOverlay.classList.add('hidden');
            topHeader.classList.remove('left-0');
            topHeader.classList.add('lg:left-[280px]');
            mainContent.classList.remove('ml-0');
            return;
        }

        sidebar.classList.toggle('-translate-x-full', !mobileSidebarOpen);
        sidebar.classList.toggle('translate-x-0', mobileSidebarOpen);
        sidebarOverlay.classList.toggle('hidden', !mobileSidebarOpen);
        topHeader.classList.add('left-0');
        topHeader.classList.remove('lg:left-[280px]');
        mainContent.classList.add('ml-0');
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
</script>
</body>
</html>