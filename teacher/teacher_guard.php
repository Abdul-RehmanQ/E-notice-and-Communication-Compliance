<?php

if (!function_exists('redirectToTeacherLogin')) {
    function redirectToTeacherLogin(): void
    {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

if (!function_exists('requireTeacherIdentity')) {
    function requireTeacherIdentity(mysqli $conn): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit();
        }

        $teacherIdentity = null;

        $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        if ($teacherId > 0) {
            $teacherStmt = $conn->prepare("SELECT t.teacher_id, t.name, t.department, u.user_id, u.email
                                           FROM teacher t
                                           INNER JOIN user u ON u.user_id = t.teacher_id
                                           WHERE t.teacher_id = ? AND u.role = 'teacher'
                                           LIMIT 1");
            $teacherStmt->bind_param('i', $teacherId);
            $teacherStmt->execute();
            $teacherResult = $teacherStmt->get_result();
            $teacherIdentity = $teacherResult ? $teacherResult->fetch_assoc() : null;
            $teacherStmt->close();
        }

        if (!$teacherIdentity) {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $teacherByUserStmt = $conn->prepare("SELECT t.teacher_id, t.name, t.department, u.user_id, u.email
                                                     FROM teacher t
                                                     INNER JOIN user u ON u.user_id = t.teacher_id
                                                     WHERE u.user_id = ? AND u.role = 'teacher'
                                                     LIMIT 1");
                $teacherByUserStmt->bind_param('i', $userId);
                $teacherByUserStmt->execute();
                $teacherByUserResult = $teacherByUserStmt->get_result();
                $teacherIdentity = $teacherByUserResult ? $teacherByUserResult->fetch_assoc() : null;
                $teacherByUserStmt->close();
            }
        }

        if (!$teacherIdentity) {
            redirectToTeacherLogin();
        }

        $_SESSION['user_id'] = (int)$teacherIdentity['user_id'];
        $_SESSION['teacher_id'] = (int)$teacherIdentity['teacher_id'];
        $_SESSION['teacher_name'] = $teacherIdentity['name'];
        $_SESSION['teacher_department'] = $teacherIdentity['department'];
        $_SESSION['role'] = 'teacher';

        return $teacherIdentity;
    }
}
