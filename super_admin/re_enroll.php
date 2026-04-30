<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$adminId = (int)$_SESSION['user_id'];
$searchTerm = trim($_GET['q'] ?? '');
$students = [];

$adminDepartment = '';
$adminProfileStmt = $conn->prepare("SELECT department FROM super_admin WHERE super_admin_id = ?");
$adminProfileStmt->bind_param("i", $adminId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult ? $adminProfileResult->fetch_assoc() : null;
$adminProfileStmt->close();

if (!$adminProfile || empty($adminProfile['department'])) {
    $error = 'Super Admin department profile is missing. Please configure super_admin table entry first.';
} else {
    $adminDepartment = trim((string)$adminProfile['department']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reenroll_student'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($studentId <= 0 || $courseId <= 0) {
        $error = 'Please select a valid student and course.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } else {
        $scopeStmt = $conn->prepare("SELECT COUNT(*) AS matched_count
                                     FROM student s
                                     INNER JOIN courses c ON c.course_id = ?
                                     WHERE s.student_id = ?
                                       AND LOWER(TRIM(s.department)) = LOWER(TRIM(?))
                                       AND LOWER(TRIM(c.department)) = LOWER(TRIM(?))");
        $scopeStmt->bind_param("iiss", $courseId, $studentId, $adminDepartment, $adminDepartment);
        $scopeStmt->execute();
        $scopeResult = $scopeStmt->get_result();
        $scopeRow = $scopeResult ? $scopeResult->fetch_assoc() : ['matched_count' => 0];
        $scopeStmt->close();

        if ((int)($scopeRow['matched_count'] ?? 0) === 0) {
            $error = 'Re-enroll blocked: student/course is outside your department scope.';
        } else {
            $assignmentStmt = $conn->prepare("SELECT COUNT(*) AS assigned_count
                                              FROM teacher_course_assignments tca
                                              INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                                              INNER JOIN courses c ON c.course_id = tca.course_id
                                              WHERE tca.course_id = ?
                                                AND LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                                                AND LOWER(TRIM(c.department)) = LOWER(TRIM(?))");
            $assignmentStmt->bind_param("iss", $courseId, $adminDepartment, $adminDepartment);
            $assignmentStmt->execute();
            $assignmentResult = $assignmentStmt->get_result();
            $assignmentRow = $assignmentResult ? $assignmentResult->fetch_assoc() : ['assigned_count' => 0];
            $assignmentStmt->close();

            if ((int)($assignmentRow['assigned_count'] ?? 0) === 0) {
                $error = 'Re-enroll blocked: selected course has no assigned teacher in your department.';
            } else {
                $checkStmt = $conn->prepare("SELECT enrollment_id, status FROM student_course_enrollments WHERE student_id = ? AND course_id = ? LIMIT 1");
                $checkStmt->bind_param("ii", $studentId, $courseId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result();
                $existingEnrollment = $existing ? $existing->fetch_assoc() : null;
                $checkStmt->close();

                if ($existingEnrollment) {
                    $error = 'Re-enroll blocked: this student already has a record for the selected course.';
                } else {
                    $insertStmt = $conn->prepare("INSERT INTO student_course_enrollments (student_id, course_id, enrolled_by) VALUES (?, ?, ?)");
                    $insertStmt->bind_param("iii", $studentId, $courseId, $adminId);
                    if ($insertStmt->execute()) {
                        $success = 'Student re-enrolled successfully.';
                    } else {
                        $error = 'Failed to re-enroll student: ' . $conn->error;
                    }
                    $insertStmt->close();
                }
            }
        }
    }
}

$courses = [];
$courseStmt = $conn->prepare("SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.semester_no
                              FROM courses c
                              INNER JOIN teacher_course_assignments tca ON tca.course_id = c.course_id
                              INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                              WHERE LOWER(TRIM(c.department)) = LOWER(TRIM(?))
                                AND LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                              ORDER BY c.course_code ASC");
$courseStmt->bind_param("ss", $adminDepartment, $adminDepartment);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
    }
}
$courseStmt->close();

if ($searchTerm !== '') {
    $likeTerm = '%' . $searchTerm . '%';
    $stmt = $conn->prepare("SELECT s.student_id, s.name, s.Roll_no, s.department, s.session, s.semester_no, u.email
                            FROM student s
                            INNER JOIN user u ON u.user_id = s.student_id
                            WHERE LOWER(TRIM(s.department)) = LOWER(TRIM(?))
                              AND (s.Roll_no LIKE ? OR s.name LIKE ? OR u.email LIKE ?)
                            ORDER BY s.name ASC");
    $stmt->bind_param("ssss", $adminDepartment, $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-enroll Student – E-Notice</title>
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
        <a href="re_enroll.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-500 font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">manage_search</span>Re-enroll Search
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
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
        <h2 class="text-slate-900 font-black text-lg font-sora">Re-enroll Search</h2>
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
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-slate-500 text-sm">
            <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-slate-900">Re-enroll Search</span>
        </nav>

        <!-- Page intro -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex gap-4 items-start">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">manage_search</span>
            </div>
            <div>
                <h3 class="font-sora font-bold text-slate-900 text-base mb-1">Individual Re-enroll</h3>
                <p class="text-slate-500 text-sm">Use this page for special cases (e.g., failed course, repeat). Search by <strong class="text-slate-700">roll number, name, or email</strong>. Batch enrollment is available on the main dashboard.</p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($error): ?>
        <div class="p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
            <span class="material-symbols-outlined text-error" style="font-variation-settings:'FILL' 1">error</span>
            <div>
                <p class="font-bold text-on-error-container text-sm font-sora">Error</p>
                <p class="text-on-error-container text-sm"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
            <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings:'FILL' 1">check_circle</span>
            <div>
                <p class="font-bold text-emerald-800 text-sm font-sora">Success</p>
                <p class="text-emerald-800 text-sm"><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <form method="GET" action="" class="flex gap-3 items-end">
                <div class="flex-1">
                    <label for="q" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Search Student</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person_search</span>
                        <input type="text" id="q" name="q"
                            value="<?php echo htmlspecialchars($searchTerm); ?>"
                            placeholder="Enter roll number, name, or email…"
                            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50"
                            required>
                    </div>
                </div>
                <button type="submit"
                    class="flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold text-sm hover:bg-blue-700 transition-all shadow-sm shrink-0">
                    <span class="material-symbols-outlined text-sm">search</span>Search
                </button>
            </form>
        </div>

        <!-- Search Results -->
        <?php if ($searchTerm !== ''): ?>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                <span class="material-symbols-outlined text-slate-500">group</span>
                <h4 class="font-sora font-semibold text-slate-900">
                    Search Results
                    <span class="ml-2 text-xs font-bold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full"><?php echo count($students); ?> found</span>
                </h4>
                <span class="ml-auto text-xs text-slate-400">Query: "<em><?php echo htmlspecialchars($searchTerm); ?></em>"</span>
            </div>

            <?php if (empty($students)): ?>
                <div class="px-6 py-12 text-center">
                    <span class="material-symbols-outlined text-slate-300 text-5xl block mb-3">search_off</span>
                    <p class="text-slate-500 text-sm">No students found for "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>" in your department.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3 border-b border-slate-200">Student</th>
                                <th class="px-6 py-3 border-b border-slate-200">Roll No</th>
                                <th class="px-6 py-3 border-b border-slate-200">Info</th>
                                <th class="px-6 py-3 border-b border-slate-200 min-w-[320px]">Re-enroll Course</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-slate-50/80 transition-all">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
                                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($student['name']); ?></p>
                                            <p class="text-xs text-slate-400"><?php echo htmlspecialchars($student['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-bold text-slate-700 bg-slate-100 px-2 py-1 rounded"><?php echo htmlspecialchars($student['Roll_no']); ?></span>
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs">
                                    <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($student['department']); ?></p>
                                    <p><?php echo htmlspecialchars($student['session']); ?> · Sem <?php echo (int)$student['semester_no']; ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" action="" class="flex gap-2 items-center">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$student['student_id']; ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <select name="course_id" required
                                            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50"
                                            <?php echo empty($courses) ? 'disabled' : ''; ?>>
                                            <option value="">Select Course</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo (int)$course['course_id']; ?>">
                                                    <?php echo htmlspecialchars($course['course_code']); ?> – <?php echo htmlspecialchars($course['course_title']); ?>
                                                    (Sem <?php echo (int)$course['semester_no']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="reenroll_student"
                                            class="flex items-center gap-1.5 bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-emerald-700 transition-all shrink-0 <?php echo empty($courses) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                            <?php echo empty($courses) ? 'disabled' : ''; ?>>
                                            <span class="material-symbols-outlined text-sm">how_to_reg</span>Re-enroll
                                        </button>
                                    </form>
                                    <?php if (empty($courses)): ?>
                                        <p class="text-xs text-amber-600 mt-1.5 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">warning</span>No courses available. Assign a teacher first.
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
