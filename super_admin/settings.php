<?php
session_start();
include '../config.php';
include '../audit_log.php';

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
        $passwordValid = verifyPasswordArgon2id($currentPassword, $user['password']);

        if (!$passwordValid) {
            $password_error = 'Current password is incorrect!';
        } elseif ($newPassword !== $repeatNewPassword) {
            $password_error = 'New passwords do not match!';
        } elseif (strlen($newPassword) < 6) {
            $password_error = 'Password must be at least 6 characters!';
        } else {
            $hashedPassword = hashPasswordArgon2id($newPassword);
            $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
            if ($updateStmt->execute()) {
                $password_success = 'Password updated successfully!';
                logActivity($conn, $adminId, 'super_admin', 'update_password', 'user', $adminId, [
                    'status' => 'success'
                ]);
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
    <title>Settings – E-Notice Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sora: ['Sora','sans-serif'], sans: ['Source Sans 3','sans-serif'] },
                colors: {
                    navy: '#0F172A',
                    'error': '#ba1a1a', 'error-container': '#ffdad6', 'on-error-container': '#93000a'
                }
            }}
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { background-color: #F8FAFC; font-family: 'Source Sans 3', sans-serif; }
    </style>
</head>
<body class="text-slate-800">

<!-- ── Sidebar ── -->
<aside class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl">
    <div class="p-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
            <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
        </div>
        <div>
            <h1 class="text-white text-xl font-bold font-sora leading-none">E-Notice</h1>
            <p class="text-slate-400 text-xs uppercase tracking-widest mt-0.5">Academic Admin</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-2 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a href="re_enroll.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">manage_search</span>Re-enroll Search
        </a>
        <a href="audit_logs.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">receipt_long</span>Audit Logs
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-500 font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">settings</span>Settings
        </a>
    </nav>
    <div class="px-4 py-4 border-t border-slate-800">
        <button id="logout-btn" class="w-full flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold text-left">
            <span class="material-symbols-outlined">logout</span>Log out
        </button>
    </div>
</aside>

<!-- ── Top Bar ── -->
<header class="fixed top-0 right-0 left-[280px] h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-8 z-40 shadow-sm">
    <div class="flex items-center gap-3">
        <h2 class="text-slate-900 font-black text-lg font-sora">Settings</h2>
        <span class="flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full border border-blue-100 text-[11px] font-bold uppercase tracking-wider">
            <span class="w-2 h-2 bg-blue-500 rounded-full"></span>Super Admin
        </span>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-right">
            <p class="font-bold text-slate-900 text-sm font-sora leading-none"><?php echo htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin'); ?></p>
            <p class="text-[10px] text-blue-600 font-bold uppercase"><?php echo htmlspecialchars($adminDepartment ?: 'Department N/A'); ?></p>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
            <?php echo strtoupper(substr($_SESSION['super_admin_name'] ?? 'A', 0, 1)); ?>
        </div>
    </div>
</header>

<!-- ── Main Content ── -->
<main class="ml-[280px] mt-16 p-6 min-h-screen">
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-slate-500 text-sm">
            <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-slate-900">Settings</span>
        </nav>

        <!-- Stats Summary -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-indigo-500 text-center">
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['courses']; ?></p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-1">Active Courses</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-amber-500 text-center">
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['assignments']; ?></p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-1">Teacher Assignments</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-slate-600 text-center">
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['enrollments']; ?></p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-1">Active Enrollments</p>
            </div>
        </div>

        <!-- Account Info Card -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex items-center gap-5">
            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-2xl shrink-0 border-4 border-blue-100 shadow">
                <?php echo strtoupper(substr($_SESSION['super_admin_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
                <p class="font-sora font-bold text-slate-900 text-lg"><?php echo htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin'); ?></p>
                <p class="text-sm text-slate-500"><?php echo htmlspecialchars($_SESSION['super_admin_email'] ?? ''); ?></p>
                <span class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-[11px] font-bold uppercase tracking-wider border border-blue-100">
                    <span class="material-symbols-outlined text-xs">verified</span><?php echo htmlspecialchars($adminDepartment ?: 'No Dept'); ?> · Super Admin
                </span>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                <span class="material-symbols-outlined text-slate-500" style="font-variation-settings:'FILL' 1">lock</span>
                <h3 class="font-sora font-semibold text-slate-900">Change Password</h3>
            </div>
            <div class="p-6">

                <!-- Flash Messages -->
                <?php if ($password_error): ?>
                <div class="mb-5 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
                    <span class="material-symbols-outlined text-error" style="font-variation-settings:'FILL' 1">error</span>
                    <div>
                        <p class="font-bold text-on-error-container text-sm font-sora">Error</p>
                        <p class="text-on-error-container text-sm"><?php echo htmlspecialchars($password_error); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($password_success): ?>
                <div class="mb-5 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
                    <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings:'FILL' 1">check_circle</span>
                    <div>
                        <p class="font-bold text-emerald-800 text-sm font-sora">Success</p>
                        <p class="text-emerald-800 text-sm"><?php echo htmlspecialchars($password_success); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4 max-w-md">
                    <div>
                        <label for="currentPassword" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Current Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">lock_open</span>
                            <input type="password" id="currentPassword" name="currentPassword" required
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                        </div>
                    </div>
                    <div>
                        <label for="newPassword" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">New Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">key</span>
                            <input type="password" id="newPassword" name="newPassword" required
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                        </div>
                    </div>
                    <div>
                        <label for="repeatNewPassword" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Confirm New Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">key</span>
                            <input type="password" id="repeatNewPassword" name="repeatNewPassword" required
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                        </div>
                        <p class="text-xs text-slate-400 mt-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">info</span>Minimum 6 characters. Stored with Argon2id hashing.
                        </p>
                    </div>
                    <button type="submit" name="update_password"
                        class="flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold text-sm hover:bg-blue-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">save</span>Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <footer class="pt-6 pb-4 border-t border-slate-200 flex justify-between items-center text-xs text-slate-400">
            <p>&copy; <?php echo date('Y'); ?> E-Notice Institutional Portal. All rights reserved.</p>
            <a href="dashboard.php" class="flex items-center gap-1.5 text-blue-600 font-bold hover:underline">
                <span class="material-symbols-outlined text-sm">arrow_back</span>Back to Dashboard
            </a>
        </footer>

    </div>
</main>

<script>
    document.getElementById('logout-btn').addEventListener('click', () => {
        window.location.href = '../logout.php';
    });
</script>
</body>
</html>
