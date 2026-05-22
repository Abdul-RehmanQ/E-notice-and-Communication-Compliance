<?php
include 'config.php';
include 'audit_log.php';

echo "Testing Logging System...\n";

// Ensure a user exists to associate with logs (e.g. user 45 - Super Admin)
$testUserId = 45;

// Scenario 1: Logout
echo "1. Simulating logout activity log...\n";
$log1 = logActivity($conn, $testUserId, 'super_admin', 'logout');
if ($log1) {
    echo "  Success! Log ID: $log1\n";
} else {
    echo "  FAILED to log logout: " . $conn->error . "\n";
}

// Scenario 2: Re-enroll student
echo "2. Simulating student re-enrollment activity log...\n";
$log2 = logActivity($conn, $testUserId, 'super_admin', 're_enroll_student', 'student', 94, [
    'student_id' => 94,
    'course_id' => 1,
    'enrolled_by' => $testUserId
]);
if ($log2) {
    echo "  Success! Log ID: $log2\n";
} else {
    echo "  FAILED to log re-enrollment: " . $conn->error . "\n";
}

// Scenario 3: Update password
echo "3. Simulating password update activity log...\n";
$log3 = logActivity($conn, $testUserId, 'super_admin', 'update_password', 'user', $testUserId, [
    'status' => 'success'
]);
if ($log3) {
    echo "  Success! Log ID: $log3\n";
} else {
    echo "  FAILED to log password update: " . $conn->error . "\n";
}

// Scenario 4: Approve post
echo "4. Simulating approve post activity log...\n";
$log4 = logActivity($conn, 109, 'community_supervisor', 'approve_post', 'posts', 1, [
    'post_id' => 1,
    'supervisor_id' => 109,
    'status' => 'approved'
]);
if ($log4) {
    echo "  Success! Log ID: $log4\n";
} else {
    echo "  FAILED to log approve post: " . $conn->error . "\n";
}

// Scenario 5: Check department filtering logic
echo "5. Verifying department-scoped query...\n";
$adminDepartmentNormalized = 'computer science';

$whereClause = "LOWER(TRIM(COALESCE(s.department, t.department, cs.department, sa.department))) = ?";
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
              LIMIT 5";

$stmt = $conn->prepare($dataQuery);
if (!$stmt) {
    echo "  FAILED to prepare statement: " . $conn->error . "\n";
} else {
    $stmt->bind_param("s", $adminDepartmentNormalized);
    $stmt->execute();
    $res = $stmt->get_result();
    echo "  Found " . $res->num_rows . " logs for department '$adminDepartmentNormalized'.\n";
    while ($row = $res->fetch_assoc()) {
        echo "  - Log ID {$row['activity_id']}: {$row['actor_name']} ({$row['actor_role']}) -> {$row['action_type']} [Dept: {$row['actor_department']}]\n";
    }
    $stmt->close();
}

?>
