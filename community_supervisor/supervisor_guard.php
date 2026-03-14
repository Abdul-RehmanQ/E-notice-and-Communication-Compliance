<?php

if (!function_exists('redirectToSupervisorLogin')) {
    function redirectToSupervisorLogin(): void
    {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

if (!function_exists('requireSupervisorIdentity')) {
    function requireSupervisorIdentity(mysqli $conn): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit();
        }

        $supervisorIdentity = null;

        $supervisorId = (int)($_SESSION['supervisor_id'] ?? 0);
        if ($supervisorId > 0) {
            $supervisorStmt = $conn->prepare("SELECT cs.supervisor_id, cs.name, cs.department, u.user_id, u.email
                                              FROM community_supervisor cs
                                              INNER JOIN user u ON u.user_id = cs.supervisor_id
                                              WHERE cs.supervisor_id = ? AND u.role = 'community_supervisor'
                                              LIMIT 1");
            $supervisorStmt->bind_param('i', $supervisorId);
            $supervisorStmt->execute();
            $supervisorResult = $supervisorStmt->get_result();
            $supervisorIdentity = $supervisorResult ? $supervisorResult->fetch_assoc() : null;
            $supervisorStmt->close();
        }

        if (!$supervisorIdentity) {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $supervisorByUserStmt = $conn->prepare("SELECT cs.supervisor_id, cs.name, cs.department, u.user_id, u.email
                                                        FROM community_supervisor cs
                                                        INNER JOIN user u ON u.user_id = cs.supervisor_id
                                                        WHERE u.user_id = ? AND u.role = 'community_supervisor'
                                                        LIMIT 1");
                $supervisorByUserStmt->bind_param('i', $userId);
                $supervisorByUserStmt->execute();
                $supervisorByUserResult = $supervisorByUserStmt->get_result();
                $supervisorIdentity = $supervisorByUserResult ? $supervisorByUserResult->fetch_assoc() : null;
                $supervisorByUserStmt->close();
            }
        }

        if (!$supervisorIdentity) {
            redirectToSupervisorLogin();
        }

        $_SESSION['user_id'] = (int)$supervisorIdentity['user_id'];
        $_SESSION['supervisor_id'] = (int)$supervisorIdentity['supervisor_id'];
        $_SESSION['supervisor_name'] = $supervisorIdentity['name'];
        $_SESSION['supervisor_department'] = $supervisorIdentity['department'];
        $_SESSION['role'] = 'community_supervisor';

        return $supervisorIdentity;
    }
}
