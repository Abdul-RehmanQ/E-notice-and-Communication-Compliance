<?php
session_start();
include '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

// Delete expired posts
$conn->query("DELETE FROM posts WHERE expires_at < NOW()");

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
    $postId = (int)$_POST['post_id'];
    // Only allow deleting own posts
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $postId, $_SESSION['user_id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "Post deleted successfully!";
    } else {
        $error = "Failed to delete post or post not found.";
    }
    $stmt->close();
}

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $scope = $_POST['scope'] ?? 'department';
    $content = trim($_POST['postContent'] ?? '');
    $expiresIn = (int)($_POST['expires_in'] ?? 15);
    $includeText = isset($_POST['include_text']);
    $includeImage = isset($_POST['include_image']);
    
    // Validate
    if (!$includeText && !$includeImage) {
        $error = "Please select at least one content type.";
    } elseif ($includeText && empty($content)) {
        $error = "Please write a message for the post.";
    } elseif ($includeImage && (!isset($_FILES['postImage']) || $_FILES['postImage']['error'] == UPLOAD_ERR_NO_FILE)) {
        $error = "Please select an image to upload.";
    } else {
        $imageData = null;
        $imageType = null;
        $imageSize = null;
        
        // Handle image upload
        if ($includeImage && isset($_FILES['postImage']) && $_FILES['postImage']['error'] == UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024; // 2MB
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            
            if ($_FILES['postImage']['size'] > $maxSize) {
                $error = "Image must be under 2MB.";
            } elseif (!in_array($_FILES['postImage']['type'], $allowedTypes)) {
                $error = "Only JPG, PNG, and GIF images are allowed.";
            } else {
                $imageData = file_get_contents($_FILES['postImage']['tmp_name']);
                $imageType = $_FILES['postImage']['type'];
                $imageSize = $_FILES['postImage']['size'];
            }
        }
        
        if (empty($error)) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresIn days"));
            $userId = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image_data, image_type, image_size, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiss", $userId, $content, $imageData, $imageType, $imageSize, $scope, $expiresAt);
            
            if ($stmt->execute()) {
                $success = "Post created successfully! It will expire in $expiresIn days.";
            } else {
                $error = "Failed to create post: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch student data
$stmt = $conn->prepare("SELECT s.name, s.Roll_no, s.department, s.session FROM student s WHERE s.student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$studentDepartment = $student['department'];

// Fetch posts: Only APPROVED posts - All university posts OR department-only posts from same department
$posts = [];
$stmt = $conn->prepare("SELECT p.*, u.email, 
                        COALESCE(s.name, t.name) as poster_name,
                        s.Roll_no as poster_roll,
                        s.department as poster_department
                        FROM posts p 
                        LEFT JOIN user u ON p.user_id = u.user_id 
                        LEFT JOIN student s ON p.user_id = s.student_id
                        LEFT JOIN teacher t ON p.user_id = t.teacher_id
                        WHERE p.expires_at > NOW() 
                        AND p.status = 'approved'
                        AND (p.scope = 'all' OR (p.scope = 'department' AND s.department = ?))
                        ORDER BY p.created_at DESC");
$stmt->bind_param("s", $studentDepartment);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
}
$stmt->close();

// Fetch user's own posts for delete dropdown
$userPosts = [];
$stmt = $conn->prepare("SELECT post_id, content, created_at FROM posts WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$userPostsResult = $stmt->get_result();
while ($row = $userPostsResult->fetch_assoc()) {
    $userPosts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Student Dashboard</title>
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

        /* 1366x768 and similar laptop screens */
        @media (min-width: 992px) and (max-width: 1399px) {
            .navbar .d-flex.text-white {
                font-size: 0.85rem;
                gap: 0.5rem !important;
            }

            main .container-fluid {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .col-lg-8 {
                flex: 0 0 auto;
                width: 60%;
            }

            .col-lg-4 {
                flex: 0 0 auto;
                width: 40%;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-none d-lg-flex"
        style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></span>
                <span>|</span>
                <span><strong>Roll No:</strong> <?php echo htmlspecialchars($student['Roll_no']); ?></span>
                <span>|</span>
                <span><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></span>
                <span>|</span>
                <span><strong>Session:</strong> <?php echo htmlspecialchars($student['session']); ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none" style="left: 0; width: 100%;">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Student Dashboard</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Offcanvas on mobile, fixed on desktop -->
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar"
                style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Student
                            Dashboard</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white mb-2" href="dashboard.php"><i
                                class="fas fa-bell me-2"></i>Notifications</a>
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="community.php"><i
                                class="fas fa-users me-2"></i>Community</a>
                        <a class="nav-link text-white mb-2" href="settings.php"><i
                                class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i
                                class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container-fluid py-4">
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

                    <!-- Action Bar -->
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPostModal">
                            <i class="fas fa-plus"></i> Add Post
                        </button>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deletePostModal">
                            <i class="fas fa-trash"></i> Delete Post
                        </button>
                    </div>

                    <!-- Delete Post Modal -->
                    <div class="modal fade" id="deletePostModal" tabindex="-1" aria-labelledby="deletePostModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deletePostModalLabel"><i
                                            class="fas fa-trash-alt me-2"></i>Delete Post</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="selectPostToDelete" class="form-label fw-bold">Select a post to
                                                delete:</label>
                                            <select class="form-select" id="selectPostToDelete" name="post_id" required>
                                                <option value="" selected disabled>-- Choose a post --</option>
                                                <?php foreach ($userPosts as $userPost): ?>
                                                    <option value="<?php echo $userPost['post_id']; ?>">
                                                        <?php 
                                                        $preview = !empty($userPost['content']) ? substr($userPost['content'], 0, 50) : '[Image Post]';
                                                        echo htmlspecialchars($preview) . (strlen($userPost['content']) > 50 ? '...' : '');
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if (empty($userPosts)): ?>
                                                    <option value="" disabled>No posts to delete</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div id="deleteConfirmSection" class="d-none">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Are you sure?</strong> This action cannot be undone.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="delete_post" class="btn btn-danger" id="confirmDeletePost" <?php echo empty($userPosts) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash me-1"></i> Delete Post
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Add Post Modal -->
                    <div class="modal fade" id="addPostModal" tabindex="-1" aria-labelledby="addPostModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="addPostModalLabel"><i
                                            class="fas fa-plus-circle me-2"></i>Create New Post</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <form id="addPostForm" method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <!-- Post Visibility -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Post Visibility</label>
                                            <div class="d-flex flex-wrap gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="scope" id="scopeDept" value="department" checked>
                                                    <label class="form-check-label" for="scopeDept">Department only</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all">
                                                    <label class="form-check-label" for="scopeAll">All University</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Post Expiration -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Post Expiration</label>
                                            <select name="expires_in" class="form-select" style="max-width: 250px;">
                                                <option value="7">Delete after 7 days</option>
                                                <option value="15" selected>Delete after 15 days</option>
                                                <option value="30">Delete after 30 days</option>
                                            </select>
                                            <div class="form-text">Post will be automatically deleted after this period.</div>
                                        </div>

                                        <!-- Post Type Selection -->
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">What would you like to post?</label>
                                            <div class="d-flex flex-wrap gap-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeText" name="include_text" checked>
                                                    <label class="form-check-label" for="includeText">
                                                        <i class="fas fa-pen me-1"></i> Write Something
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeImage" name="include_image">
                                                    <label class="form-check-label" for="includeImage">
                                                        <i class="fas fa-image me-1"></i> Upload Image
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Text Content Section -->
                                        <div class="mb-3" id="textSection">
                                            <label for="postContent" class="form-label fw-bold">Your Message</label>
                                            <textarea class="form-control" id="postContent" name="postContent" rows="4"
                                                placeholder="What's on your mind?"></textarea>
                                        </div>

                                        <!-- Image Upload Section -->
                                        <div class="mb-3 d-none" id="imageSection">
                                            <label for="postImage" class="form-label fw-bold">Upload Image</label>
                                            <input class="form-control" type="file" id="postImage" name="postImage" accept="image/*">
                                            <div class="form-text">Accepted formats: JPG, PNG, GIF (Max 2MB)</div>
                                            <!-- Image Preview -->
                                            <div id="imagePreview" class="mt-3 d-none">
                                                <img src="" alt="Preview" class="img-fluid rounded"
                                                    style="max-height: 200px;">
                                                <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                                    id="removeImage">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="create_post" class="btn btn-success" id="submitPost">
                                            <i class="fas fa-paper-plane me-1"></i> Send for Approval
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="row">
                        <!-- Posts Section -->
                        <div class="col-lg-8 mb-4">
                            <div class="card shadow-sm h-100">
                                <div
                                    class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <h3 class="mb-0 h5">Recent Posts</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($posts)): ?>
                                        <div class="alert alert-info">
                                            No posts yet. Click "Add Post" to create one.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($posts as $post): ?>
                                            <div class="card mb-3 shadow-sm">
                                                <div class="card-body">
                                                    <?php if (!empty($post['content'])): ?>
                                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($post['image_data'])): ?>
                                                        <div class="mb-2">
                                                            <img src="data:<?php echo $post['image_type']; ?>;base64,<?php echo base64_encode($post['image_data']); ?>" 
                                                                 class="img-fluid rounded" style="max-height: 300px;">
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($post['poster_name'] ?? 'Unknown'); ?>
                                                            <?php if (!empty($post['poster_roll'])): ?>
                                                                (<?php echo htmlspecialchars($post['poster_roll']); ?>)
                                                            <?php endif; ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-globe me-1"></i>
                                                            <?php echo $post['scope'] == 'all' ? 'All University' : 'Department Only'; ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-1">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?>
                                                        </small>
                                                        <small>
                                                            <?php 
                                                            $daysLeft = ceil((strtotime($post['expires_at']) - time()) / 86400);
                                                            $badgeClass = $daysLeft <= 3 ? 'bg-warning text-dark' : 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?php echo $badgeClass; ?>">
                                                                <i class="fas fa-clock me-1"></i>Expires in <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Sidebar -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h4 class="mb-0 h5">Your Posts</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($userPosts)): ?>
                                        <p class="text-muted">You haven't posted anything yet.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($userPosts as $userPost): ?>
                                                <li class="list-group-item">
                                                    <small class="text-muted"><?php echo date('M d', strtotime($userPost['created_at'])); ?>:</small>
                                                    <?php 
                                                    $preview = !empty($userPost['content']) ? substr($userPost['content'], 0, 40) : '[Image Post]';
                                                    echo htmlspecialchars($preview) . (strlen($userPost['content']) > 40 ? '...' : '');
                                                    ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
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

        // Toggle text section visibility
        document.getElementById('includeText').addEventListener('change', function () {
            const textSection = document.getElementById('textSection');
            if (this.checked) {
                textSection.classList.remove('d-none');
            } else {
                textSection.classList.add('d-none');
            }
        });

        // Toggle image section visibility
        document.getElementById('includeImage').addEventListener('change', function () {
            const imageSection = document.getElementById('imageSection');
            if (this.checked) {
                imageSection.classList.remove('d-none');
            } else {
                imageSection.classList.add('d-none');
                // Clear image input and preview when hidden
                document.getElementById('postImage').value = '';
                document.getElementById('imagePreview').classList.add('d-none');
            }
        });

        // Image preview functionality
        document.getElementById('postImage').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = imagePreview.querySelector('img');

            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    this.value = '';
                    return;
                }

                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image size must be less than 2MB.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.classList.add('d-none');
            }
        });

        // Remove image button
        document.getElementById('removeImage').addEventListener('click', function () {
            document.getElementById('postImage').value = '';
            document.getElementById('imagePreview').classList.add('d-none');
        });

        // Form validation before submit
        document.getElementById('addPostForm').addEventListener('submit', function(e) {
            const includeText = document.getElementById('includeText').checked;
            const includeImage = document.getElementById('includeImage').checked;
            const postContent = document.getElementById('postContent').value.trim();
            const postImage = document.getElementById('postImage').files[0];

            if (!includeText && !includeImage) {
                e.preventDefault();
                alert('Please select at least one option: Write something or Upload an image.');
                return;
            }

            if (includeText && !postContent) {
                e.preventDefault();
                alert('Please write something to post.');
                return;
            }

            if (includeImage && !postImage) {
                e.preventDefault();
                alert('Please select an image to upload.');
                return;
            }
        });

        // Reset form when modal is closed
        document.getElementById('addPostModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addPostForm').reset();
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('imageSection').classList.add('d-none');
            document.getElementById('textSection').classList.remove('d-none');
            document.getElementById('includeText').checked = true;
            document.getElementById('includeImage').checked = false;
        });

        // Delete Post - show warning when post is selected
        document.getElementById('selectPostToDelete').addEventListener('change', function() {
            const deleteConfirmSection = document.getElementById('deleteConfirmSection');
            if (this.value) {
                deleteConfirmSection.classList.remove('d-none');
            } else {
                deleteConfirmSection.classList.add('d-none');
            }
        });

        // Reset delete modal when closed
        document.getElementById('deletePostModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('selectPostToDelete').value = '';
            document.getElementById('deleteConfirmSection').classList.add('d-none');
        });
    </script>
</body>

</html>
