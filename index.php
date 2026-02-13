<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rollNumber = trim($_POST['rollNumber']);
    $password = $_POST['password'];
    
    // Join student and user tables to get credentials by roll number
    $stmt = $conn->prepare("SELECT s.student_id, s.Roll_no, s.name, u.user_id, u.password 
                            FROM student s 
                            INNER JOIN user u ON s.student_id = u.user_id 
                            WHERE s.Roll_no = ?");
    $stmt->bind_param("s", $rollNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $student = $result->fetch_assoc();
        // Verify password (supports both plain text and hashed passwords)
        $passwordValid = ($password === $student['password']) || password_verify($password, $student['password']);
        
        if ($passwordValid) {
            // Update login_time
            $updateStmt = $conn->prepare("UPDATE user SET login_time = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $student['user_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $_SESSION['user_id'] = $student['user_id'];
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['roll_number'] = $student['Roll_no'];
            $_SESSION['student_name'] = $student['name'];
            header("Location: student/dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Student not found!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>

<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Student Login</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="rollNumber" class="form-label">Student Roll Number</label>
                                <input type="text" class="form-control" id="rollNumber" name="rollNumber"
                                    placeholder="Enter your roll number" required>
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
                                <a href="teacher/login.php" class="btn btn-outline-secondary">Teacher Login</a>
                                <a href="community_supervisor/login.php" class="btn btn-outline-secondary">Community Supervisor Login</a>
                                <a href="super_admin/login.php" class="btn btn-outline-secondary">Super Admin Login</a>
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
