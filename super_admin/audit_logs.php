<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$adminId = (int)$_SESSION['user_id'];
$adminDepartment = '';
$adminProfileStmt = $conn->prepare("SELECT department FROM super_admin WHERE super_admin_id = ?");
$adminProfileStmt->bind_param("i", $adminId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult ? $adminProfileResult->fetch_assoc() : null;
$adminProfileStmt->close();

if (!$adminProfile || empty($adminProfile['department'])) {
    die("Super Admin department profile is missing. Please configure super_admin table entry first.");
}
$adminDepartment = trim((string)$adminProfile['department']);
$adminDepartmentNormalized = mb_strtolower($adminDepartment);

// Filter values
$filterRole = trim($_GET['role'] ?? '');
$filterAction = trim($_GET['action_type'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Base query parts (only see actions of users belonging to this admin's department)
$where = ["LOWER(TRIM(COALESCE(s.department, t.department, cs.department, sa.department))) = ?"];
$params = [$adminDepartmentNormalized];
$types = 's';

if ($filterRole !== '') {
    $where[] = "al.actor_role = ?";
    $params[] = $filterRole;
    $types .= 's';
}

if ($filterAction !== '') {
    $where[] = "al.action_type = ?";
    $params[] = $filterAction;
    $types .= 's';
}

if ($filterSearch !== '') {
    $where[] = "(COALESCE(s.name, t.name, cs.name, sa.name) LIKE ? OR u.email LIKE ?)";
    $likeSearch = '%' . $filterSearch . '%';
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $types .= 'ss';
}

if ($filterDateFrom !== '') {
    $where[] = "al.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
    $types .= 's';
}

if ($filterDateTo !== '') {
    $where[] = "al.created_at <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
    $types .= 's';
}

$whereClause = implode(' AND ', $where);

// Count total matching rows
$countQuery = "SELECT COUNT(*) as total
               FROM activity_logs al
               LEFT JOIN user u ON al.user_id = u.user_id
               LEFT JOIN student s ON al.user_id = s.student_id AND al.actor_role = 'student'
               LEFT JOIN teacher t ON al.user_id = t.teacher_id AND al.actor_role = 'teacher'
               LEFT JOIN community_supervisor cs ON al.user_id = cs.supervisor_id AND al.actor_role = 'community_supervisor'
               LEFT JOIN super_admin sa ON al.user_id = sa.super_admin_id AND al.actor_role = 'super_admin'
               WHERE $whereClause";

$countStmt = $conn->prepare($countQuery);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$totalRows = $totalResult['total'] ?? 0;
$countStmt->close();

$totalPages = ceil($totalRows / $limit);

// Fetch matching rows
$dataQuery = "SELECT al.*, u.email,
                COALESCE(s.name, t.name, cs.name, sa.name) as actor_name,
                COALESCE(s.department, t.department, cs.department, sa.department) as actor_department
              FROM activity_logs al
              LEFT JOIN user u ON al.user_id = u.user_id
              LEFT JOIN student s ON al.user_id = s.student_id AND al.actor_role = 'student'
              LEFT JOIN teacher t ON al.user_id = t.teacher_id AND al.actor_role = 'teacher'
              LEFT JOIN community_supervisor cs ON al.user_id = cs.supervisor_id AND al.actor_role = 'community_supervisor'
              LEFT JOIN super_admin sa ON al.user_id = sa.super_admin_id AND al.actor_role = 'super_admin'
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT ? OFFSET ?";

$dataStmt = $conn->prepare($dataQuery);
$dataTypes = $types . 'ii';
$dataParams = array_merge($params, [$limit, $offset]);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$logsResult = $dataStmt->get_result();
$logs = [];
if ($logsResult) {
    while ($row = $logsResult->fetch_assoc()) {
        $logs[] = $row;
    }
}
$dataStmt->close();

// Fetch distinct action types for the filter dropdown (belonging to this department)
$actionQuery = "SELECT DISTINCT al.action_type 
                FROM activity_logs al
                LEFT JOIN student s ON al.user_id = s.student_id AND al.actor_role = 'student'
                LEFT JOIN teacher t ON al.user_id = t.teacher_id AND al.actor_role = 'teacher'
                LEFT JOIN community_supervisor cs ON al.user_id = cs.supervisor_id AND al.actor_role = 'community_supervisor'
                LEFT JOIN super_admin sa ON al.user_id = sa.super_admin_id AND al.actor_role = 'super_admin'
                WHERE LOWER(TRIM(COALESCE(s.department, t.department, cs.department, sa.department))) = ?
                ORDER BY al.action_type ASC";
$actionStmt = $conn->prepare($actionQuery);
$actionStmt->bind_param('s', $adminDepartmentNormalized);
$actionStmt->execute();
$actionsResult = $actionStmt->get_result();
$actionTypes = [];
if ($actionsResult) {
    while ($row = $actionsResult->fetch_assoc()) {
        $actionTypes[] = $row['action_type'];
    }
}
$actionStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs – E-Notice</title>
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
        <a href="audit_logs.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-500 font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">receipt_long</span>Audit Logs
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
        <h2 class="text-slate-900 font-black text-lg font-sora">Audit Logs</h2>
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
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-slate-500 text-sm">
            <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-slate-900">Audit Logs</span>
        </nav>

        <!-- Filters Section -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Search User</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base">search</span>
                        <input type="text" id="search" name="search"
                            value="<?php echo htmlspecialchars($filterSearch); ?>"
                            placeholder="Name or Email…"
                            class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                    </div>
                </div>

                <!-- Role filter -->
                <div>
                    <label for="role" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Role</label>
                    <select id="role" name="role"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo $filterRole === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="community_supervisor" <?php echo $filterRole === 'community_supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="super_admin" <?php echo $filterRole === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>

                <!-- Action Type Filter -->
                <div>
                    <label for="action_type" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Action</label>
                    <select id="action_type" name="action_type"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterAction === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="date_from" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-2">From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                    </div>
                    <div>
                        <label for="date_to" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-2">To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>"
                            class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 shrink-0">
                    <button type="submit"
                        class="flex-1 flex items-center justify-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-bold text-sm hover:bg-blue-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>Filter
                    </button>
                    <?php if ($filterRole || $filterAction || $filterSearch || $filterDateFrom || $filterDateTo): ?>
                    <a href="audit_logs.php"
                        class="flex items-center justify-center bg-slate-100 text-slate-600 px-3 py-2.5 rounded-lg font-bold text-sm hover:bg-slate-200 transition-all border border-slate-200"
                        title="Clear Filters">
                        <span class="material-symbols-outlined text-base">filter_alt_off</span>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table Section -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                <span class="material-symbols-outlined text-slate-500">history</span>
                <h4 class="font-sora font-semibold text-slate-900">
                    Department Action Log
                    <span class="ml-2 text-xs font-bold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full"><?php echo $totalRows; ?> records</span>
                </h4>
            </div>

            <?php if (empty($logs)): ?>
                <div class="px-6 py-16 text-center">
                    <span class="material-symbols-outlined text-slate-300 text-6xl block mb-3">manage_search</span>
                    <p class="text-slate-500 text-sm font-semibold">No audit records found matching your filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4 border-b border-slate-200">Timestamp</th>
                                <th class="px-6 py-4 border-b border-slate-200">Actor</th>
                                <th class="px-6 py-4 border-b border-slate-200">Action</th>
                                <th class="px-6 py-4 border-b border-slate-200">Target Entity</th>
                                <th class="px-6 py-4 border-b border-slate-200">Device/IP</th>
                                <th class="px-6 py-4 border-b border-slate-200 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($logs as $log): ?>
                            <?php 
                                // Format Action Badges
                                $action = htmlspecialchars($log['action_type']);
                                $actionClass = "bg-slate-100 text-slate-700 border-slate-200";
                                if (strpos($action, 'login') !== false) $actionClass = "bg-blue-50 text-blue-700 border-blue-100";
                                elseif (strpos($action, 'logout') !== false) $actionClass = "bg-slate-100 text-slate-600 border-slate-200";
                                elseif (strpos($action, 'approve') !== false) $actionClass = "bg-emerald-50 text-emerald-700 border-emerald-100";
                                elseif (strpos($action, 'reject') !== false || strpos($action, 'delete') !== false) $actionClass = "bg-red-50 text-red-700 border-red-100";
                                elseif (strpos($action, 'enroll') !== false) $actionClass = "bg-indigo-50 text-indigo-700 border-indigo-100";
                                elseif (strpos($action, 'create') !== false) $actionClass = "bg-teal-50 text-teal-700 border-teal-100";

                                // Format Role Badges
                                $role = htmlspecialchars($log['actor_role']);
                                $roleClass = "bg-slate-50 text-slate-600 border-slate-200";
                                if ($role === 'super_admin') $roleClass = "bg-blue-50 text-blue-700 border-blue-100";
                                elseif ($role === 'teacher') $roleClass = "bg-amber-50 text-amber-700 border-amber-100";
                                elseif ($role === 'community_supervisor') $roleClass = "bg-emerald-50 text-emerald-700 border-emerald-100";

                                // Format Entity
                                $entityStr = "N/A";
                                if (!empty($log['entity_type'])) {
                                    $entityStr = htmlspecialchars($log['entity_type']);
                                    if ($log['entity_id'] !== null) {
                                        $entityStr .= " (ID: " . (int)$log['entity_id'] . ")";
                                    }
                                }
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-all align-top">
                                <td class="px-6 py-4 text-xs font-data-tabular text-slate-500 whitespace-nowrap">
                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                    <span class="text-[10px] text-slate-400 font-normal"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 font-bold text-xs shrink-0 mt-0.5 border border-slate-200">
                                            <?php echo strtoupper(substr($log['actor_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900 text-sm leading-tight"><?php echo htmlspecialchars($log['actor_name'] ?? 'Unknown'); ?></p>
                                            <p class="text-[10px] text-slate-400 leading-normal mb-1"><?php echo htmlspecialchars($log['email'] ?? 'No Email'); ?></p>
                                            <span class="inline-block text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?php echo $roleClass; ?>"><?php echo $role; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded border <?php echo $actionClass; ?>">
                                        <?php echo $action; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-700 font-mono text-xs whitespace-nowrap">
                                    <?php echo $entityStr; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs">
                                    <p class="font-mono text-[10px] text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded inline-block mb-1 border border-slate-200"><?php echo htmlspecialchars($log['ip_address'] ?? '0.0.0.0'); ?></p>
                                    <p class="text-[10px] text-slate-400 max-w-[200px] truncate" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                        <?php echo htmlspecialchars($log['user_agent'] ?? 'No Agent'); ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if (!empty($log['action_details'])): ?>
                                        <button type="button" onclick="toggleDetails(<?php echo (int)$log['activity_id']; ?>)"
                                            class="inline-flex items-center gap-1 text-xs text-blue-600 font-bold hover:text-blue-800 transition-colors">
                                            <span id="btnText<?php echo (int)$log['activity_id']; ?>">View</span>
                                            <span class="material-symbols-outlined text-sm" id="btnIcon<?php echo (int)$log['activity_id']; ?>">expand_more</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-300 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($log['action_details'])): ?>
                            <tr id="detailsRow<?php echo (int)$log['activity_id']; ?>" class="hidden bg-slate-50/50">
                                <td colspan="6" class="px-6 py-4 border-t border-b border-slate-100">
                                    <div class="p-4 bg-[#0F172A] rounded-lg border border-slate-800 text-left font-mono text-xs text-blue-300 overflow-x-auto shadow-inner">
                                        <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-2">
                                            <span class="text-slate-500 font-bold text-[10px] uppercase tracking-wider">Payload Metadata</span>
                                            <span class="text-[10px] text-slate-400 bg-slate-800 px-2 py-0.5 rounded">Activity ID: <?php echo (int)$log['activity_id']; ?></span>
                                        </div>
                                        <pre class="whitespace-pre-wrap leading-relaxed"><?php 
                                            $jsonObj = json_decode($log['action_details']);
                                            echo htmlspecialchars(json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                        ?></pre>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <p class="text-xs text-slate-500 font-medium">
                        Showing page <span class="font-bold text-slate-900"><?php echo $page; ?></span> of <span class="font-bold text-slate-900"><?php echo $totalPages; ?></span>
                    </p>
                    <div class="flex items-center gap-1.5">
                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="flex items-center justify-center w-8 h-8 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </a>
                        <?php else: ?>
                            <span class="flex items-center justify-center w-8 h-8 rounded border border-slate-100 bg-slate-50 text-slate-300 cursor-not-allowed">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                class="flex items-center justify-center w-8 h-8 rounded border text-xs font-bold transition-all <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="flex items-center justify-center w-8 h-8 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition-all">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </a>
                        <?php else: ?>
                            <span class="flex items-center justify-center w-8 h-8 rounded border border-slate-100 bg-slate-50 text-slate-300 cursor-not-allowed">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
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

    function toggleDetails(activityId) {
        const row = document.getElementById('detailsRow' + activityId);
        const text = document.getElementById('btnText' + activityId);
        const icon = document.getElementById('btnIcon' + activityId);

        if (row.classList.contains('hidden')) {
            row.classList.remove('hidden');
            text.textContent = 'Hide';
            icon.textContent = 'expand_less';
        } else {
            row.classList.add('hidden');
            text.textContent = 'View';
            icon.textContent = 'expand_more';
        }
    }
</script>
</body>
</html>
