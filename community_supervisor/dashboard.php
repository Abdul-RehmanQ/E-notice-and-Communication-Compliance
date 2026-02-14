<?php
session_start();
include '../config.php';
require_once __DIR__ . '/supervisor_guard.php';

$supervisor = requireSupervisorIdentity($conn);
$supervisorId = (int)$supervisor['supervisor_id'];

$success = '';
$error = '';

// Delete expired posts
$conn->query("DELETE FROM posts WHERE expires_at < NOW()");

// Handle post approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_post'])) {
    $postId = (int)$_POST['post_id'];
    
    $stmt = $conn->prepare("UPDATE posts SET status = 'approved' WHERE post_id = ?");
    $stmt->bind_param("i", $postId);
    if ($stmt->execute()) {
        // Log the review
        $logStmt = $conn->prepare("INSERT INTO post_reviews (post_id, supervisor_id, action) VALUES (?, ?, 'approved')");
        $logStmt->bind_param("ii", $postId, $supervisorId);
        $logStmt->execute();
        $logStmt->close();
        
        $success = "Post approved successfully!";
    } else {
        $error = "Failed to approve post.";
    }
    $stmt->close();
}

// Handle post rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_post'])) {
    $postId = (int)$_POST['post_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');
    
    // Log the review first
    $logStmt = $conn->prepare("INSERT INTO post_reviews (post_id, supervisor_id, action, rejection_reason) VALUES (?, ?, 'rejected', ?)");
    $logStmt->bind_param("iis", $postId, $supervisorId, $reason);
    $logStmt->execute();
    $logStmt->close();
    
    // Delete the rejected post
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
    $stmt->bind_param("i", $postId);
    if ($stmt->execute()) {
        $success = "Post rejected and deleted.";
    } else {
        $error = "Failed to reject post.";
    }
    $stmt->close();
}

// Fetch pending posts
$pendingPosts = [];
$result = $conn->query("SELECT p.*, u.email, 
                        COALESCE(s.name, t.name) as poster_name,
                        s.Roll_no as poster_roll,
                        s.department as poster_department
                        FROM posts p 
                        LEFT JOIN user u ON p.user_id = u.user_id 
                        LEFT JOIN student s ON p.user_id = s.student_id
                        LEFT JOIN teacher t ON p.user_id = t.teacher_id
                        WHERE p.status = 'pending' AND p.expires_at > NOW()
                        ORDER BY p.created_at ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingPosts[] = $row;
    }
}

// Fetch recent reviews (last 20)
$recentReviews = [];
$result = $conn->query("SELECT pr.*, p.content as post_content, 
                        COALESCE(s.name, t.name) as poster_name
                        FROM post_reviews pr 
                        LEFT JOIN posts p ON pr.post_id = p.post_id
                        LEFT JOIN student s ON p.user_id = s.student_id
                        LEFT JOIN teacher t ON p.user_id = t.teacher_id
                        ORDER BY pr.reviewed_at DESC LIMIT 20");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentReviews[] = $row;
    }
}

// Count stats
$statsResult = $conn->query("SELECT 
    (SELECT COUNT(*) FROM posts WHERE status = 'pending' AND expires_at > NOW()) as pending_count,
    (SELECT COUNT(*) FROM post_reviews WHERE action = 'approved' AND DATE(reviewed_at) = CURDATE()) as approved_today,
    (SELECT COUNT(*) FROM post_reviews WHERE action = 'rejected' AND DATE(reviewed_at) = CURDATE()) as rejected_today");
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-top: 56px;
        }

        @media (min-width: 992px) {
            body {
                padding-top: 70px;
            }

            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1040;
            }

            main {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-success fixed-top d-none d-lg-flex"
        style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Supervisor:</strong> <?php echo htmlspecialchars($_SESSION['supervisor_name']); ?></span>
                <span>|</span>
                <span><strong>Pending Posts:</strong> <?php echo $stats['pending_count']; ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-success fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Supervisor Dashboard</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar"
                style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Supervisor Panel</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php">
                            <i class="fas fa-tasks me-2"></i>Pending Posts
                            <?php if ($stats['pending_count'] > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $stats['pending_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link text-white mb-2" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn">
                            <i class="fas fa-sign-out-alt me-2"></i>Log out
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2 class="mb-4">Pending Posts for Review</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['pending_count']; ?></h3>
                                    <p class="mb-0">Pending Posts</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['approved_today']; ?></h3>
                                    <p class="mb-0">Approved Today</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['rejected_today']; ?></h3>
                                    <p class="mb-0">Rejected Today</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Posts -->
                    <?php if (empty($pendingPosts)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-check-circle me-2"></i>No pending posts to review. All caught up!
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingPosts as $post): ?>
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-warning">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <strong>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($post['poster_name'] ?? 'Unknown'); ?>
                                            <?php if (!empty($post['poster_roll'])): ?>
                                                (<?php echo htmlspecialchars($post['poster_roll']); ?>)
                                            <?php endif; ?>
                                        </strong>
                                        <span class="badge bg-dark">
                                            <?php echo $post['scope'] == 'all' ? 'All University' : 'Department: ' . htmlspecialchars($post['poster_department'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($post['content'])): ?>
                                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($post['image_data'])): ?>
                                        <div class="mb-3">
                                            <img src="data:<?php echo $post['image_type']; ?>;base64,<?php echo base64_encode($post['image_data']); ?>" 
                                                 class="img-fluid rounded" style="max-height: 300px;">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap gap-2 text-muted small mb-3">
                                        <span><i class="fas fa-calendar me-1"></i>Submitted: <?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></span>
                                        <span>|</span>
                                        <span>
                                            <?php 
                                            $daysLeft = ceil((strtotime($post['expires_at']) - time()) / 86400);
                                            ?>
                                            <i class="fas fa-clock me-1"></i>Expires in <?php echo $daysLeft; ?> days
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                            <button type="submit" name="approve_post" class="btn btn-success">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $post['post_id']; ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?php echo $post['post_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Post</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                                <p>Are you sure you want to reject this post? It will be permanently deleted.</p>
                                                <div class="mb-3">
                                                    <label for="reason<?php echo $post['post_id']; ?>" class="form-label">Reason (optional)</label>
                                                    <textarea class="form-control" id="reason<?php echo $post['post_id']; ?>" 
                                                              name="rejection_reason" rows="3" 
                                                              placeholder="Enter reason for rejection..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reject_post" class="btn btn-danger">
                                                    <i class="fas fa-trash me-1"></i>Reject & Delete
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Recent Activity -->
                    <h4 class="mt-5 mb-3">Recent Reviews</h4>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if (empty($recentReviews)): ?>
                                <p class="text-muted mb-0">No reviews yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Action</th>
                                                <th>Post</th>
                                                <th>Poster</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentReviews as $review): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($review['action'] == 'approved'): ?>
                                                            <span class="badge bg-success">Approved</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $preview = !empty($review['post_content']) ? substr($review['post_content'], 0, 40) : '[Image Post]';
                                                        echo htmlspecialchars($preview) . (strlen($review['post_content'] ?? '') > 40 ? '...' : '');
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($review['poster_name'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo date('M d, h:i A', strtotime($review['reviewed_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    </script>
</body>

</html>
