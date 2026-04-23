<?php
session_start();
include '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT cs.supervisor_id, cs.name, cs.department, u.user_id, u.password
                            FROM community_supervisor cs
                            INNER JOIN user u ON cs.supervisor_id = u.user_id
                            WHERE u.email = ? AND u.role = 'community_supervisor'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $supervisor = $result->fetch_assoc();
        $passwordValid = verifyPasswordArgon2id($password, $supervisor['password']);

        if ($passwordValid) {
            $updateStmt = $conn->prepare("UPDATE user SET login_time = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $supervisor['user_id']);
            $updateStmt->execute();
            $updateStmt->close();

            $_SESSION['user_id'] = $supervisor['user_id'];
            $_SESSION['supervisor_id'] = $supervisor['supervisor_id'];
            $_SESSION['supervisor_name'] = $supervisor['name'];
            $_SESSION['supervisor_department'] = $supervisor['department'];
            $_SESSION['role'] = 'community_supervisor';
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Supervisor not found!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduCompliance Hub - Supervisor Login</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'surface-variant': '#e4e2e4', 'error': '#ba1a1a', 'secondary': '#0040e0',
                        'on-background': '#1b1b1d', 'on-secondary-container': '#efefff',
                        'secondary-fixed': '#dde1ff', 'on-primary-container': '#cfd5e8',
                        'on-secondary': '#ffffff', 'outline': '#76777d', 'on-error': '#ffffff',
                        'surface-dim': '#dcd9db', 'tertiary': '#000000', 'primary': '#000000',
                        'primary-fixed-dim': '#bec6e0', 'surface-container': '#f0edef',
                        'primary-fixed': '#dae2fd', 'surface-container-lowest': '#ffffff',
                        'tertiary-fixed': '#6ffbbe', 'surface-tint': '#565e74',
                        'surface-container-high': '#eae7e9', 'tertiary-container': '#002113',
                        'on-primary': '#ffffff', 'surface-container-low': '#f6f3f5',
                        'primary-container': '#131b2e', 'on-tertiary-container': '#009668',
                        'on-primary-fixed': '#131b2e', 'tertiary-fixed-dim': '#4edea3',
                        'surface-container-highest': '#e4e2e4', 'surface-bright': '#fcf8fa',
                        'on-tertiary': '#ffffff', 'inverse-primary': '#bec6e0',
                        'secondary-container': '#2e5bff', 'surface': '#fcf8fa',
                        'on-surface-variant': '#45464d', 'background': '#fcf8fa',
                        'on-error-container': '#93000a', 'secondary-fixed-dim': '#b8c3ff',
                        'inverse-surface': '#303032', 'on-primary-fixed-variant': '#3f465c',
                        'on-surface': '#1b1b1d', 'inverse-on-surface': '#f3f0f2',
                        'outline-variant': '#c6c6cd', 'error-container': '#ffdad6',
                        'on-secondary-fixed': '#001356'
                    },
                    borderRadius: { DEFAULT: '0.125rem', lg: '0.25rem', xl: '0.5rem', full: '0.75rem' },
                    fontFamily: {
                        h3: ['Sora', 'sans-serif'], h1: ['Sora', 'sans-serif'],
                        'label-caps': ['Sora', 'sans-serif'], 'body-md': ['Source Sans 3', 'sans-serif'],
                        'body-sm': ['Source Sans 3', 'sans-serif'], h2: ['Sora', 'sans-serif'],
                        'body-lg': ['Source Sans 3', 'sans-serif'], 'data-tabular': ['Source Sans 3', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .academic-mesh { background-color: #fcf8fa; background-image: radial-gradient(#e4e2e4 0.5px, transparent 0.5px); background-size: 24px 24px; }
    </style>
</head>
<body class="bg-background font-body-md text-on-background academic-mesh min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-[1100px] grid grid-cols-1 md:grid-cols-12 bg-white rounded-xl shadow-xl shadow-slate-200/50 overflow-hidden border border-outline-variant">

        <!-- Left Side: Branding -->
        <section class="md:col-span-5 bg-primary-container p-12 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <img class="w-full h-full object-cover grayscale" alt="abstract academic architecture" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB2HYQTuUPO6_kxo1HORvgt4QdPuLGrW6s4bEZUe846ozSz5hJ-w1PpL4PANyc062RwC4re2iPfy7rlwKkShS2hBnGb4suyMNJ9CPetwvpZV_Jxvs8yKN-nWRfdVvQkzi7F_hqt7k87ArankMg_ZejBx7owg42j9p8Uls0ienXT_W9zyEvDrUvfNx33vVLiFWa9ZkHPZsIpjpq5G4Dy9PVPlOhNr-B4WcW-4W2CsIqdM-yVVtq-zZBejjT6Nq555oFXHhEPauvVEQ">
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-12">
                    <div class="w-10 h-10 bg-secondary flex items-center justify-center rounded-lg">
                        <span class="material-symbols-outlined text-white" style="font-variation-settings: 'FILL' 1;">school</span>
                    </div>
                    <h1 class="font-h2 text-2xl tracking-tight text-white">EduCompliance Hub</h1>
                </div>
                <div class="space-y-8">
                    <div>
                        <span class="font-label-caps text-xs tracking-wider text-on-primary-container block mb-2">SYSTEM STATUS</span>
                        <div class="flex items-center gap-2 text-tertiary-fixed-dim">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            <span class="font-body-sm text-sm">All academic services operational</span>
                        </div>
                    </div>
                    <div class="bg-white/5 border border-white/10 p-6 rounded-xl backdrop-blur-sm">
                        <h3 class="font-h3 text-xl text-white mb-3">Notice Central</h3>
                        <p class="font-body-sm text-sm text-on-primary-container">
                            Access unified academic records, compliance documents, and institutional circulars. Your credentials are protected by the EduShield framework.
                        </p>
                    </div>
                </div>
            </div>
            <div class="relative z-10 pt-12">
                <p class="font-body-sm text-sm text-on-primary-container/70">
                    &copy; 2026 University Administration System.<br>
                    Precision in Compliance, Excellence in Education.
                </p>
            </div>
        </section>

        <!-- Right Side: Login Form -->
        <section class="md:col-span-7 p-12 bg-white flex flex-col justify-center">
            <div class="mb-10">
                <h2 class="font-h2 text-3xl text-primary mb-2">Supervisor Login</h2>
                <p class="font-body-md text-base text-on-surface-variant">Enter your institutional credentials to access your dashboard.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg" role="alert">
                    <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1;">error</span>
                    <div>
                        <p class="font-data-tabular text-on-error-container font-semibold">Login failed</p>
                        <p class="font-body-sm text-sm text-on-error-container/90"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <div class="relative">
                        <label class="font-label-caps text-xs tracking-wider text-on-surface-variant block mb-2" for="email">EMAIL ADDRESS</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-secondary">mail</span>
                            <input class="w-full pl-12 pr-4 py-3.5 bg-surface-container-low border border-outline-variant rounded-lg font-body-md focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none transition-all placeholder:text-outline-variant"
                                id="email" name="email" type="email" placeholder="e.g. supervisor@university.edu"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="relative">
                        <label class="font-label-caps text-xs tracking-wider text-on-surface-variant block mb-2" for="password">PASSWORD</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-secondary">lock</span>
                            <input class="w-full pl-12 pr-12 py-3.5 bg-surface-container-low border border-outline-variant rounded-lg font-body-md focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none transition-all placeholder:text-outline-variant"
                                id="password" name="password" type="password" placeholder="Enter your password" required>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors" type="button" id="togglePassword" aria-label="Show password">
                                <span class="material-symbols-outlined" id="togglePasswordIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                <button class="w-full py-4 bg-secondary text-white font-h3 text-lg rounded-lg shadow-lg shadow-secondary/20 hover:bg-secondary/90 transition-all transform active:scale-[0.98]" type="submit">
                    Sign In to Dashboard
                </button>
            </form>

            <div class="mt-12 pt-8 border-t border-outline-variant">
                <span class="font-label-caps text-xs tracking-wider text-on-surface-variant block mb-4 text-center">SWITCH ACCESS ROLE</span>
                <div class="grid grid-cols-3 gap-4">
                    <a href="../index.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-outline-variant hover:border-secondary hover:bg-secondary/5 transition-all group" aria-label="Student login">
                        <span class="material-symbols-outlined text-outline group-hover:text-secondary">school</span>
                        <span class="font-label-caps text-[10px] tracking-wider">STUDENT</span>
                    </a>
                    <a href="../teacher/login.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-outline-variant hover:border-secondary hover:bg-secondary/5 transition-all group" aria-label="Teacher login">
                        <span class="material-symbols-outlined text-outline group-hover:text-secondary">person_edit</span>
                        <span class="font-label-caps text-[10px] tracking-wider">TEACHER</span>
                    </a>
                    <a href="../super_admin/login.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-outline-variant hover:border-secondary hover:bg-secondary/5 transition-all group" aria-label="Admin login">
                        <span class="material-symbols-outlined text-outline group-hover:text-secondary">admin_panel_settings</span>
                        <span class="font-label-caps text-[10px] tracking-wider">ADMIN</span>
                    </a>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="font-body-sm text-sm text-on-surface-variant/70">Difficulty logging in? Contact the IT service desk.</p>
            </div>
        </section>
    </main>

    <button class="fixed bottom-8 right-8 w-14 h-14 bg-white text-on-surface rounded-full shadow-2xl flex items-center justify-center border border-outline-variant hover:bg-surface-container-high transition-all" type="button" aria-label="Help">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">help</span>
    </button>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                if (togglePasswordIcon) togglePasswordIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
                togglePasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }
    </script>
</body>
</html>