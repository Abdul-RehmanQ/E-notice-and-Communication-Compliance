<?php
session_start();
include '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch student data
$stmt = $conn->prepare("SELECT s.name, s.Roll_no, s.department, s.session FROM student s WHERE s.student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
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
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="selectPostToDelete" class="form-label fw-bold">Select a post to
                                            delete:</label>
                                        <select class="form-select" id="selectPostToDelete">
                                            <option value="" selected disabled>-- Choose a post --</option>
                                            <option value="1">Sample post content...</option>
                                            <option value="2">Another sample post...</option>
                                            <option value="3">Yet another post here.</option>
                                            <option value="4">More content to fill the space.</option>
                                        </select>
                                    </div>
                                    <div id="deleteConfirmSection" class="d-none">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Are you sure?</strong> This action cannot be undone.
                                        </div>
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <p class="mb-0" id="selectedPostPreview"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDeletePost" disabled>
                                        <i class="fas fa-trash me-1"></i> Delete Post
                                    </button>
                                </div>
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
                                <div class="modal-body">
                                    <form id="addPostForm">
                                        <!-- Post Type Selection -->
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">What would you like to post?</label>
                                            <div class="d-flex flex-wrap gap-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeText"
                                                        checked>
                                                    <label class="form-check-label" for="includeText">
                                                        <i class="fas fa-pen me-1"></i> Write Something
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeImage">
                                                    <label class="form-check-label" for="includeImage">
                                                        <i class="fas fa-image me-1"></i> Upload Image
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Text Content Section -->
                                        <div class="mb-3" id="textSection">
                                            <label for="postContent" class="form-label fw-bold">Your Message</label>
                                            <textarea class="form-control" id="postContent" rows="4"
                                                placeholder="What's on your mind?"></textarea>
                                        </div>

                                        <!-- Image Upload Section -->
                                        <div class="mb-3 d-none" id="imageSection">
                                            <label for="postImage" class="form-label fw-bold">Upload Image</label>
                                            <input class="form-control" type="file" id="postImage" accept="image/*">
                                            <div class="form-text">Accepted formats: JPG, PNG, GIF (Max 5MB)</div>
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
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-success" id="submitPost">
                                        <i class="fas fa-paper-plane me-1"></i> Send for Approval
                                    </button>
                                </div>
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
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary active btn-sm">View
                                            all</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm">Dept Only</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="card mb-3 shadow-sm">
                                        <div class="card-body">
                                            <p class="mb-0">Sample post content...</p>
                                        </div>
                                    </div>
                                    <div class="card mb-3 shadow-sm">
                                        <div class="card-body">
                                            <p class="mb-0">Another sample post...</p>
                                        </div>
                                    </div>
                                    <div class="card mb-3 shadow-sm">
                                        <div class="card-body">
                                            <p class="mb-0">Yet another post here.</p>
                                        </div>
                                    </div>
                                    <div class="card mb-3 shadow-sm">
                                        <div class="card-body">
                                            <p class="mb-0">More content to fill the space.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Sidebar -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h4 class="mb-0 h5">Your Activity</h4>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">You posted: "Sample post content..."</li>
                                        <li class="list-group-item">You liked: "Another sample post..."</li>
                                        <li class="list-group-item">You commented on: "Sample post content..."</li>
                                        <li class="list-group-item">New reply to your post</li>
                                        <li class="list-group-item">Post from your department</li>
                                        <li class="list-group-item">Shared a resource</li>
                                        <li class="list-group-item">Joined a discussion</li>
                                    </ul>
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

                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size must be less than 5MB.');
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

        // Submit post
        document.getElementById('submitPost').addEventListener('click', function () {
            const includeText = document.getElementById('includeText').checked;
            const includeImage = document.getElementById('includeImage').checked;
            const postContent = document.getElementById('postContent').value.trim();
            const postImage = document.getElementById('postImage').files[0];

            // Validation
            if (!includeText && !includeImage) {
                alert('Please select at least one option: Write something or Upload an image.');
                return;
            }

            if (includeText && !postContent) {
                alert('Please write something to post.');
                return;
            }

            if (includeImage && !postImage) {
                alert('Please select an image to upload.');
                return;
            }

            // Placeholder: Here you would send data to the backend
            console.log('Post submitted:', {
                text: includeText ? postContent : null,
                image: includeImage ? postImage.name : null
            });

            alert('Post created successfully!');

            // Reset form and close modal
            document.getElementById('addPostForm').reset();
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('imageSection').classList.add('d-none');
            document.getElementById('textSection').classList.remove('d-none');
            document.getElementById('includeText').checked = true;
            document.getElementById('includeImage').checked = false;

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPostModal'));
            modal.hide();
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

        // Delete Post functionality
        const selectPostToDelete = document.getElementById('selectPostToDelete');
        const deleteConfirmSection = document.getElementById('deleteConfirmSection');
        const selectedPostPreview = document.getElementById('selectedPostPreview');
        const confirmDeleteBtn = document.getElementById('confirmDeletePost');

        // Show confirmation when a post is selected
        selectPostToDelete.addEventListener('change', function () {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                selectedPostPreview.textContent = selectedOption.text;
                deleteConfirmSection.classList.remove('d-none');
                confirmDeleteBtn.disabled = false;
            } else {
                deleteConfirmSection.classList.add('d-none');
                confirmDeleteBtn.disabled = true;
            }
        });

        // Confirm delete
        confirmDeleteBtn.addEventListener('click', function () {
            const postId = selectPostToDelete.value;
            const postText = selectPostToDelete.options[selectPostToDelete.selectedIndex].text;

            // Placeholder: Here you would send delete request to the backend
            console.log('Deleting post:', { id: postId, text: postText });

            alert('Post deleted successfully!');

            // Reset and close modal
            selectPostToDelete.value = '';
            deleteConfirmSection.classList.add('d-none');
            confirmDeleteBtn.disabled = true;

            const modal = bootstrap.Modal.getInstance(document.getElementById('deletePostModal'));
            modal.hide();
        });

        // Reset delete modal when closed
        document.getElementById('deletePostModal').addEventListener('hidden.bs.modal', function () {
            selectPostToDelete.value = '';
            deleteConfirmSection.classList.add('d-none');
            confirmDeleteBtn.disabled = true;
        });
    </script>
</body>

</html>
