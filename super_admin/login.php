<?php
session_start();
include '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, email, password, role FROM user WHERE email = ? AND role = 'super_admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        $passwordValid = verifyPasswordArgon2id($password, $admin['password']);

        if ($passwordValid) {
            $updateStmt = $conn->prepare("UPDATE user SET login_time = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $admin['user_id']);
            $updateStmt->execute();
            $updateStmt->close();

            $_SESSION['user_id'] = $admin['user_id'];
            $_SESSION['role'] = 'super_admin';
            $_SESSION['super_admin_email'] = $admin['email'];
            $_SESSION['super_admin_name'] = explode('@', $admin['email'])[0];

            header("Location: dashboard.php");
            exit();
        }

        $error = 'Invalid password!';
    } else {
        $error = 'Super admin not found!';
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Super Admin Login</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="Enter your email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">Show</button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                            <hr class="my-3">
                            <p class="text-center text-muted mb-2">Other Login Options</p>
                            <div class="d-grid gap-2">
                                <a href="../index.php" class="btn btn-outline-secondary">Student Login</a>
                                <a href="../teacher/login.php" class="btn btn-outline-secondary">Teacher Login</a>
                                <a href="../community_supervisor/login.php" class="btn btn-outline-secondary">Community Supervisor Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordBtn = document.getElementById('togglePassword');

        togglePasswordBtn.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePasswordBtn.textContent = isPassword ? 'Hide' : 'Show';
            togglePasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    </script>
</body>

</html>
