<?php
session_start();
include '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT t.teacher_id, t.name, t.department, u.user_id, u.password
                            FROM teacher t
                            INNER JOIN user u ON t.teacher_id = u.user_id
                            WHERE u.email = ? AND u.role = 'teacher'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $teacher = $result->fetch_assoc();
        $passwordValid = ($password === $teacher['password']) || password_verify($password, $teacher['password']);

        if ($passwordValid) {
            $updateStmt = $conn->prepare("UPDATE user SET login_time = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $teacher['user_id']);
            $updateStmt->execute();
            $updateStmt->close();

            $_SESSION['user_id'] = $teacher['user_id'];
            $_SESSION['teacher_id'] = $teacher['teacher_id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_department'] = $teacher['department'];
            $_SESSION['role'] = 'teacher';

            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid password!';
        }
    } else {
        $error = 'Teacher not found!';
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Teacher Login</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="teacherEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="teacherEmail" name="email"
                                    placeholder="Enter your email" required>
                            </div>
                            <div class="mb-3">
                                <label for="teacherPassword" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="teacherPassword" name="password"
                                        placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleTeacherPassword" aria-label="Show password">Show</button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                            <hr class="my-3">
                            <p class="text-center text-muted mb-2">Other Login Options</p>
                            <div class="d-grid gap-2">
                                <a href="../index.php" class="btn btn-outline-secondary">Student Login</a>
                                <a href="../community_supervisor/login.php" class="btn btn-outline-secondary">Community Supervisor Login</a>
                                <a href="../super_admin/login.php" class="btn btn-outline-secondary">Super Admin Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const teacherPasswordInput = document.getElementById('teacherPassword');
        const toggleTeacherPasswordBtn = document.getElementById('toggleTeacherPassword');

        toggleTeacherPasswordBtn.addEventListener('click', function () {
            const isPassword = teacherPasswordInput.type === 'password';
            teacherPasswordInput.type = isPassword ? 'text' : 'password';
            toggleTeacherPasswordBtn.textContent = isPassword ? 'Hide' : 'Show';
            toggleTeacherPasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    </script>
</body>

</html>
