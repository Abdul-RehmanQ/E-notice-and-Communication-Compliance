<?php

if (!function_exists('redirectToStudentLogin')) {
    function redirectToStudentLogin(): void
    {
        session_unset();
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
}

if (!function_exists('requireStudentIdentity')) {
    function requireStudentIdentity(mysqli $conn): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ../index.php');
            exit();
        }

        if (!isset($_SESSION['student_id'])) {
            $resolveStudentStmt = $conn->prepare("SELECT s.student_id, s.name, s.Roll_no
                                                 FROM student s
                                                 INNER JOIN user u ON u.user_id = s.student_id
                                                 WHERE u.user_id = ? AND u.role = 'student'
                                                 LIMIT 1");
            $resolveStudentStmt->bind_param('i', $_SESSION['user_id']);
            $resolveStudentStmt->execute();
            $resolveStudentResult = $resolveStudentStmt->get_result();
            $resolvedStudent = $resolveStudentResult ? $resolveStudentResult->fetch_assoc() : null;
            $resolveStudentStmt->close();

            if ($resolvedStudent) {
                $_SESSION['student_id'] = (int)$resolvedStudent['student_id'];
                $_SESSION['student_name'] = $resolvedStudent['name'];
                $_SESSION['roll_number'] = $resolvedStudent['Roll_no'];
            } else {
                redirectToStudentLogin();
            }
        }

        $studentIdentity = null;

        $studentId = (int)($_SESSION['student_id'] ?? 0);
        if ($studentId > 0) {
            $studentIdentityStmt = $conn->prepare("SELECT s.student_id, s.name, s.Roll_no, s.department, s.session, u.user_id, u.email
                                                   FROM student s
                                                   INNER JOIN user u ON u.user_id = s.student_id
                                                   WHERE s.student_id = ? AND u.role = 'student'
                                                   LIMIT 1");
            $studentIdentityStmt->bind_param('i', $studentId);
            $studentIdentityStmt->execute();
            $studentIdentityResult = $studentIdentityStmt->get_result();
            $studentIdentity = $studentIdentityResult ? $studentIdentityResult->fetch_assoc() : null;
            $studentIdentityStmt->close();
        }

        if (!$studentIdentity) {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $studentIdentityByUserStmt = $conn->prepare("SELECT s.student_id, s.name, s.Roll_no, s.department, s.session, u.user_id, u.email
                                                             FROM student s
                                                             INNER JOIN user u ON u.user_id = s.student_id
                                                             WHERE u.user_id = ? AND u.role = 'student'
                                                             LIMIT 1");
                $studentIdentityByUserStmt->bind_param('i', $userId);
                $studentIdentityByUserStmt->execute();
                $studentIdentityByUserResult = $studentIdentityByUserStmt->get_result();
                $studentIdentity = $studentIdentityByUserResult ? $studentIdentityByUserResult->fetch_assoc() : null;
                $studentIdentityByUserStmt->close();
            }
        }

        if (!$studentIdentity) {
            redirectToStudentLogin();
        }

        $_SESSION['user_id'] = (int)$studentIdentity['user_id'];
        $_SESSION['student_id'] = (int)$studentIdentity['student_id'];
        $_SESSION['student_name'] = $studentIdentity['name'];
        $_SESSION['roll_number'] = $studentIdentity['Roll_no'];
        $_SESSION['role'] = 'student';

        return $studentIdentity;
    }
}
