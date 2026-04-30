<?php
session_start();
include '../config.php';
require_once __DIR__ . '/student_guard.php';

$studentIdentity = requireStudentIdentity($conn);
$studentUserId = (int)$studentIdentity['user_id'];

$password_error = '';
$password_success = '';
$email_error = '';
$email_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $repeatNewPassword = $_POST['repeatNewPassword'];

    $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ? AND role = 'student'");
    $stmt->bind_param("i", $studentUserId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $passwordValid = verifyPasswordArgon2id($currentPassword, $user['password']);

    if (!$passwordValid) $password_error = "Current password is incorrect!";
    elseif ($newPassword !== $repeatNewPassword) $password_error = "New passwords do not match!";
    elseif (strlen($newPassword) < 6) $password_error = "Password must be at least 6 characters!";
    else {
        $hashedPassword = hashPasswordArgon2id($newPassword);
        $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ? AND role = 'student'");
        $updateStmt->bind_param("si", $hashedPassword, $studentUserId);
        if ($updateStmt->execute()) $password_success = "Password updated successfully!";
        else $password_error = "Failed to update password!";
        $updateStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Invalid email format!";
    } else {
        $checkStmt = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $checkStmt->bind_param("si", $email, $studentUserId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $email_error = "This email is already in use by another account!";
        } else {
            $updateStmt = $conn->prepare("UPDATE user SET email = ? WHERE user_id = ? AND role = 'student'");
            $updateStmt->bind_param("si", $email, $studentUserId);
            if ($updateStmt->execute()) $email_success = "Email updated successfully!";
            else $email_error = "Failed to update email!";
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

$stmt = $conn->prepare("SELECT s.name, s.Roll_no, s.department, s.session FROM student s WHERE s.student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentEmail = (string)($studentIdentity['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Settings - E-Notice</title>
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
                    'surface-container-low': '#f6f3f5', 'outline-variant': '#c6c6cd',
                    'on-surface': '#1b1b1d', 'on-surface-variant': '#45464d',
                    'error-container': '#ffdad6', 'on-error-container': '#93000a',
                    'primary-container': '#131b2e', 'on-tertiary-container': '#009668',
                    'surface-container-high': '#eae7e9', 'tertiary-fixed-dim': '#4edea3'
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
    </style>
</head>
<body class="bg-[#F8FAFC] font-body-md text-on-surface">

<!-- Sidebar -->
<aside class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl">
    <div class="p-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
            <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
        </div>
        <div class="flex flex-col gap-0.5">
            <h1 class="text-white text-xl font-bold tracking-tight font-h1">E-Notice</h1>
            <p class="text-slate-400 text-xs">Academic Administration</p>
        </div>
    </div>
    <nav class="flex-1 mt-2 px-2">
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-label-caps uppercase tracking-wide text-xs" href="dashboard.php">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-label-caps uppercase tracking-wide text-xs" href="community.php">
            <span class="material-symbols-outlined">campaign</span>Community
        </a>
        <a class="flex items-center gap-3 px-4 py-3 bg-secondary/10 text-secondary border-l-4 border-secondary transition-all font-label-caps uppercase tracking-wide text-xs" href="settings.php">
            <span class="material-symbols-outlined">settings</span>Settings
        </a>
    </nav>
    <div class="border-t border-slate-800 pt-2 pb-6 px-2">
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-label-caps uppercase tracking-wide text-xs" href="../logout.php">
            <span class="material-symbols-outlined">logout</span>Logout
        </a>
    </div>
</aside>

<!-- Top Bar -->
<header class="fixed top-0 right-0 left-[280px] h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-8 z-40 shadow-sm">
    <div class="flex items-center gap-4">
        <h2 class="text-slate-900 font-black text-lg font-h2">Account Settings</h2>
    </div>
    <div class="flex items-center gap-4">
        <button class="hover:bg-slate-100 rounded-lg p-2 transition-all relative">
            <span class="material-symbols-outlined text-slate-600">notifications</span>
        </button>
        <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
            <div class="text-right">
                <p class="font-bold text-slate-900 text-sm font-h3 leading-none"><?php echo htmlspecialchars($student['name']); ?></p>
                <span class="bg-secondary/10 text-secondary text-[10px] px-2 py-0.5 rounded font-label-caps uppercase">Student</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="ml-[280px] mt-16 p-6 min-h-screen">
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-on-primary-container font-body-sm text-sm">
            <a class="hover:text-secondary" href="dashboard.php">Dashboard</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-on-surface">Account Settings</span>
        </nav>

        <!-- Identity Header -->
        <section class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="h-28 w-full bg-gradient-to-r from-[#131b2e] to-[#0040e0] relative">
                <div class="absolute inset-0 bg-secondary/40 backdrop-blur-[2px]"></div>
            </div>
            <div class="px-8 pb-6 -mt-10 relative flex flex-col md:flex-row items-end gap-6">
                <div class="w-24 h-24 rounded-xl border-4 border-white shadow-lg bg-secondary flex items-center justify-center text-white text-3xl font-bold">
                    <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                </div>
                <div class="flex-1 pb-2">
                    <div class="flex items-center gap-3">
                        <h1 class="font-h1 text-2xl font-bold text-on-surface"><?php echo htmlspecialchars($student['name']); ?></h1>
                        <span class="bg-surface-container-high text-on-surface-variant text-[10px] px-2 py-1 rounded font-bold uppercase tracking-widest">Active</span>
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 mt-1 text-on-primary-container text-sm">
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-lg">badge</span><?php echo htmlspecialchars($student['Roll_no']); ?></span>
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-lg">domain</span><?php echo htmlspecialchars($student['department']); ?></span>
                        <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-lg">calendar_month</span><?php echo htmlspecialchars($student['session']); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="space-y-6">
            <!-- Forms -->
            <div class="space-y-6">

                <!-- Email Section -->
                <section class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
                    <header class="mb-6">
                        <h3 class="font-h2 text-xl text-on-surface"><?php echo empty($currentEmail) ? 'Add Email' : 'Communication Access'; ?></h3>
                        <p class="font-body-sm text-on-primary-container text-sm">Update your university correspondence email address.</p>
                    </header>

                    <?php if ($email_error): ?>
                    <div class="mb-4 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
                        <span class="material-symbols-outlined text-error text-lg" style="font-variation-settings: 'FILL' 1;">error</span>
                        <p class="font-body-sm text-on-error-container text-sm"><?php echo htmlspecialchars($email_error); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($email_success): ?>
                    <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
                        <span class="material-symbols-outlined text-emerald-600 text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <p class="font-body-sm text-emerald-800 text-sm"><?php echo htmlspecialchars($email_success); ?></p>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="font-label-caps text-xs text-on-surface-variant">Email Address</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">mail</span>
                                <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-on-surface font-body-sm text-sm focus:ring-2 focus:ring-secondary/20 outline-none"
                                    type="email" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>" placeholder="your@email.com" required>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_email" class="bg-secondary text-white px-6 py-2.5 rounded-lg font-label-caps text-sm hover:bg-on-secondary-fixed-variant transition-all shadow-md">
                                <?php echo empty($currentEmail) ? 'Add Email' : 'Update Email'; ?>
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Password Section -->
                <section class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
                    <header class="mb-6">
                        <h3 class="font-h2 text-xl text-on-surface">Security Credentials</h3>
                        <p class="font-body-sm text-on-primary-container text-sm">Passwords must be at least 6 characters long.</p>
                    </header>

                    <?php if ($password_error): ?>
                    <div class="mb-4 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
                        <span class="material-symbols-outlined text-error text-lg" style="font-variation-settings: 'FILL' 1;">error</span>
                        <p class="font-body-sm text-on-error-container text-sm"><?php echo htmlspecialchars($password_error); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($password_success): ?>
                    <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
                        <span class="material-symbols-outlined text-emerald-600 text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <p class="font-body-sm text-emerald-800 text-sm"><?php echo htmlspecialchars($password_success); ?></p>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-5">
                        <div class="space-y-1.5">
                            <label class="font-label-caps text-xs text-on-surface-variant">Current Password</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">lock_open</span>
                                <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-on-surface font-body-sm text-sm focus:ring-2 focus:ring-secondary/20 outline-none"
                                    type="password" name="currentPassword" id="currentPassword" placeholder="••••••••" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="font-label-caps text-xs text-on-surface-variant">New Password</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">password</span>
                                    <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-on-surface font-body-sm text-sm focus:ring-2 focus:ring-secondary/20 outline-none"
                                        type="password" name="newPassword" id="newPassword" placeholder="Min. 6 characters" required>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="font-label-caps text-xs text-on-surface-variant">Confirm New Password</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">key</span>
                                    <input class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-on-surface font-body-sm text-sm focus:ring-2 focus:ring-secondary/20 outline-none"
                                        type="password" name="repeatNewPassword" id="repeatNewPassword" placeholder="Repeat password" required>
                                </div>
                            </div>
                        </div>
                        <div class="bg-surface-container-low rounded-lg p-4 border border-outline-variant/30">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-secondary">info</span>
                                <p class="font-body-sm text-on-surface-variant text-xs">Changing your password will update your login credentials across all sessions.</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-2">
                            <button class="text-on-surface-variant font-label-caps text-sm hover:text-on-surface" type="reset">Discard Changes</button>
                            <button class="bg-secondary text-white px-8 py-3 rounded-lg font-label-caps text-sm hover:bg-on-secondary-fixed-variant transition-all shadow-md" type="submit" name="update_password">Apply Password Change</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-12 p-8 border-t border-slate-200 text-on-primary-container flex justify-between items-center">
        <p class="text-xs font-body-sm">&copy; 2026 E-Notice Institutional Portal. All rights reserved.</p>
        <div class="flex gap-6 text-xs font-label-caps">
            <a class="hover:text-secondary" href="#">System Status</a>
            <a class="hover:text-secondary" href="#">Legal Compliance</a>
            <a class="hover:text-secondary" href="#">Support Center</a>
        </div>
    </footer>
</main>
</body>
</html>
