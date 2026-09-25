<?php
session_start();
include '../config.php';
require_once __DIR__ . '/supervisor_guard.php';

$supervisor = requireSupervisorIdentity($conn);
$supervisorUserId = (int)$supervisor['user_id'];

$password_error = '';
$password_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $repeatNewPassword = $_POST['repeatNewPassword'];

    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ? AND role = 'community_supervisor'");
    $stmt->bind_param("i", $supervisorUserId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $passwordValid = verifyPasswordArgon2id($currentPassword, $user['password']);

    if (!$passwordValid) $password_error = "Current password is incorrect!";
    elseif ($newPassword !== $repeatNewPassword) $password_error = "New passwords do not match!";
    elseif (strlen($newPassword) < 6) $password_error = "Password must be at least 6 characters!";
    else {
        $hashedPassword = hashPasswordArgon2id($newPassword);
        $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ? AND role = 'community_supervisor'");
        $updateStmt->bind_param("si", $hashedPassword, $supervisorUserId);
        if ($updateStmt->execute()) $password_success = "Password updated successfully!";
        else $password_error = "Failed to update password!";
        $updateStmt->close();
    }
}

$statsResult = $conn->query("SELECT COUNT(*) as pending_count FROM posts WHERE status = 'pending' AND expires_at > NOW()");
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Settings - E-Notice</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    'error': '#ba1a1a', 'secondary': '#0040e0',
                    'error-container': '#ffdad6', 'on-error-container': '#93000a',
                    'on-primary-container': '#7c839b', 'on-surface-variant': '#45464d',
                    'on-secondary-fixed-variant': '#0035be'
                },
                fontFamily: {
                    h1: ['Sora','sans-serif'], h2: ['Sora','sans-serif'], h3: ['Sora','sans-serif'],
                    'label-caps': ['Sora','sans-serif'], 'body-md': ['Source Sans 3','sans-serif'],
                    'body-sm': ['Source Sans 3','sans-serif']
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
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm">
            <span class="material-symbols-outlined">fact_check</span>
            Moderation Queue
            <?php if ($stats['pending_count'] > 0): ?>
            <span class="ml-auto bg-error text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $stats['pending_count']; ?></span>
            <?php endif; ?>
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-600 font-h3 text-sm">
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
        <h2 class="text-slate-900 font-black text-lg font-h2">Account Settings</h2>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="font-bold text-slate-900 text-sm font-h3 leading-none"><?php echo htmlspecialchars($supervisor['name']); ?></p>
                <p class="text-[10px] text-secondary font-bold uppercase tracking-[0.18em]">Community Moderator</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                <?php echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main id="mainContent" class="mt-16 p-4 sm:p-6 lg:ml-[280px] lg:mt-16 lg:p-6 min-h-screen transition-all duration-300 ease-in-out">
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-on-primary-container text-sm">
            <a class="hover:text-emerald-600" href="dashboard.php">Dashboard</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-slate-900">Account Settings</span>
        </nav>

        <!-- Identity Header -->
        <section class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="h-24 w-full bg-gradient-to-r from-[#131b2e] to-[#0040e0] relative">
                <div class="absolute inset-0 bg-secondary/40"></div>
            </div>
            <div class="px-4 pb-6 -mt-10 relative flex flex-col items-center gap-4 text-center md:px-8 md:flex-row md:items-end md:gap-6 md:text-left">
                <div class="w-20 h-20 rounded-xl border-4 border-white shadow-lg bg-secondary flex items-center justify-center text-white text-3xl font-bold">
                    <?php echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
                </div>
                <div class="pb-2">
                    <h1 class="font-h1 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($supervisor['name']); ?></h1>
                    <div class="flex flex-wrap justify-center gap-x-4 mt-1 text-slate-500 text-sm md:justify-start">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-lg">domain</span><?php echo htmlspecialchars($supervisor['department'] ?? 'N/A'); ?></span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-lg">verified_user</span>Community Moderator</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Password Section -->
        <section class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
            <header class="mb-6">
                <h3 class="font-h2 text-xl text-slate-900">Security Credentials</h3>
                <p class="text-on-primary-container text-sm font-body-sm">Passwords must be at least 6 characters long.</p>
            </header>

            <?php if ($password_error): ?>
            <div class="mb-4 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
                <span class="material-symbols-outlined text-error text-lg" style="font-variation-settings:'FILL' 1">error</span>
                <p class="text-on-error-container text-sm font-body-sm"><?php echo htmlspecialchars($password_error); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($password_success): ?>
            <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
                <span class="material-symbols-outlined text-emerald-600 text-lg" style="font-variation-settings:'FILL' 1">check_circle</span>
                <p class="text-emerald-800 text-sm font-body-sm"><?php echo htmlspecialchars($password_success); ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div class="space-y-1.5">
                    <label class="font-label-caps text-xs text-on-surface-variant">Current Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">lock_open</span>
                        <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none"
                            type="password" name="currentPassword" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="font-label-caps text-xs text-on-surface-variant">New Password</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">password</span>
                            <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none"
                                type="password" name="newPassword" placeholder="Min. 6 characters" required>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-label-caps text-xs text-on-surface-variant">Confirm New Password</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">key</span>
                            <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none"
                                type="password" name="repeatNewPassword" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200 flex items-start gap-3">
                    <span class="material-symbols-outlined text-secondary">info</span>
                    <p class="text-on-surface-variant text-xs font-body-sm">This updates your login credentials for the community moderation portal.</p>
                </div>
                <div class="flex items-center justify-between pt-2">
                    <button class="text-on-surface-variant font-label-caps text-sm hover:text-slate-900" type="reset">Discard Changes</button>
                    <button class="bg-secondary text-white px-8 py-3 rounded-lg font-label-caps text-sm hover:bg-on-secondary-fixed-variant transition-all shadow-md" type="submit" name="update_password">
                        Apply Password Change
                    </button>
                </div>
            </form>
        </section>
    </div>

    <footer class="mt-12 p-8 border-t border-slate-200 text-on-primary-container flex justify-between items-center">
        <p class="text-xs font-body-sm">&copy; 2026 E-Notice Institutional Portal. All rights reserved.</p>
        <div class="flex gap-6 text-xs font-label-caps">
            <a class="hover:text-secondary" href="#">System Status</a>
            <a class="hover:text-secondary" href="#">Support Center</a>
        </div>
    </footer>
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
