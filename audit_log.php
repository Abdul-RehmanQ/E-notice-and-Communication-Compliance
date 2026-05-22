<?php
// audit_log.php
// Helper for writing and reading activity logs (JSON details supported)

if (!function_exists('logActivity')) {
    function logActivity($conn, $user_id, $actor_role, $action_type, $entity_type = null, $entity_id = null, $action_details = null)
    {
        // Normalize inputs
        $user_id = $user_id ? (int)$user_id : null;
        $actor_role = $actor_role ?? 'student';
        $action_type = (string)$action_type;
        $entity_type = $entity_type ? (string)$entity_type : null;
        $entity_id = $entity_id ? (int)$entity_id : null;

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $details_json = null;
        if ($action_details !== null) {
            // ensure JSON-serializable
            if (!is_string($action_details)) {
                $details_json = json_encode($action_details, JSON_UNESCAPED_UNICODE);
            } else {
                $details_json = $action_details;
            }
        }

        $sql = "INSERT INTO activity_logs (user_id, actor_role, action_type, entity_type, entity_id, action_details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('isssisss', $user_id, $actor_role, $action_type, $entity_type, $entity_id, $details_json, $ip, $ua);
        $ok = $stmt->execute();
        if ($ok) {
            $insertId = $stmt->insert_id;
            $stmt->close();
            return $insertId;
        }
        $stmt->close();
        return false;
    }
}

if (!function_exists('logFieldChange')) {
    function logFieldChange(array $old_state, array $new_state)
    {
        $changed = [];
        foreach ($new_state as $k => $v) {
            $old = array_key_exists($k, $old_state) ? $old_state[$k] : null;
            if ($old !== $v) {
                $changed[$k] = ['old' => $old, 'new' => $v];
            }
        }
        return ['before' => $old_state, 'after' => $new_state, 'changed_fields' => $changed];
    }
}

if (!function_exists('getActivityLogs')) {
    function getActivityLogs($conn, $filters = [], $limit = 50, $offset = 0)
    {
        $where = [];
        $types = '';
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['actor_role'])) {
            $where[] = 'actor_role = ?';
            $types .= 's';
            $params[] = $filters['actor_role'];
        }
        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = ?';
            $types .= 's';
            $params[] = $filters['action_type'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $types .= 's';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $types .= 's';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $types .= 's';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = 'SELECT * FROM activity_logs';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';

        $stmt = $conn->prepare($sql);
        if (!$stmt) return ['rows' => [], 'total' => 0];

        // bind dynamic params
        $bind_params = [];
        if ($types !== '') {
            $bind_params[] = &$types;
            foreach ($params as $i => $p) {
                $bind_params[] = &$params[$i];
            }
        }
        $limit_i = (int)$limit;
        $offset_i = (int)$offset;
        $types_final = $types . 'ii';
        $bind_params_final = [];
        $bind_params_final[] = &$types_final;
        foreach ($params as $i => $p) {
            $bind_params_final[] = &$params[$i];
        }
        $bind_params_final[] = &$limit_i;
        $bind_params_final[] = &$offset_i;

        // Use call_user_func_array to bind
        call_user_func_array([$stmt, 'bind_param'], $bind_params_final);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        return ['rows' => $rows];
    }
}
