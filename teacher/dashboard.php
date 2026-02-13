<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT t.teacher_id, t.name, t.department, u.email
                        FROM teacher t
                        INNER JOIN user u ON t.teacher_id = u.user_id
                        WHERE t.teacher_id = ? AND u.role = 'teacher'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$teacher) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['teacher_id'] = $teacher['teacher_id'];
$_SESSION['teacher_name'] = $teacher['name'];
$_SESSION['teacher_department'] = $teacher['department'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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

            main .container {
                max-width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .row.g-3 .col-md-3 {
                flex: 0 0 auto;
                width: 50%;
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
                <span><strong>Teacher:</strong> <?php echo htmlspecialchars($teacher['name']); ?></span>
                <span>|</span>
                <span><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department']); ?></span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Teacher Dashboard</span>
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
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Teacher
                            Dashboard</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php"><i
                                class="fas fa-bell me-2"></i>Notifications</a>
                        <a class="nav-link text-white mb-2" href="community.php"><i
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
                <div class="container py-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h2 class="mb-0">Notifications</h2>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button class="btn btn-success" id="show-add-form">
                                <i class="fas fa-plus me-1"></i>Add New Notification
                            </button>
                            <button class="btn btn-danger" id="show-delete-modal">
                                <i class="fas fa-trash me-1"></i>Delete Notification
                            </button>
                        </div>
                    </div>

                    <!-- Add Notification Form -->
                    <div class="card mb-4 d-none" id="add-notification-card">
                        <div class="card-header">
                            <h5 class="mb-0">New Notification</h5>
                        </div>
                        <div class="card-body">
                            <form id="add-notification-form">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label for="session" class="form-label">Session</label>
                                        <input type="text" class="form-control" id="session" placeholder="2023-2027"
                                            required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="department" class="form-label">Department</label>
                                        <select class="form-select" id="department" required>
                                            <option value="" selected disabled>Select department</option>
                                            <option value="CS">CS</option>
                                            <option value="IT">IT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" required>
                                            <option value="" selected disabled>Select semester</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="section" class="form-label">Section</label>
                                        <select class="form-select" id="section" required>
                                            <option value="" selected disabled>Select section</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="Both">Both Sections</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Content Type</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="includeText" checked>
                                            <label class="form-check-label" for="includeText">
                                                <i class="fas fa-pen me-1"></i>Write something
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="includeImage">
                                            <label class="form-check-label" for="includeImage">
                                                <i class="fas fa-image me-1"></i>Upload image
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3" id="textSection">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" rows="3"
                                        placeholder="Write your notification..."></textarea>
                                </div>

                                <div class="mb-3 d-none" id="imageSection">
                                    <label for="image" class="form-label">Image</label>
                                    <input class="form-control" type="file" id="image" accept="image/*">
                                    <div class="form-text">Accepted formats: JPG, PNG, GIF (Max 5MB)</div>
                                    <div id="imagePreview" class="mt-3 d-none">
                                        <img src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                            id="removeImage">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" id="cancel-add">Cancel</button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-paper-plane me-1"></i>Send Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Notification History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Notification History</h5>
                        </div>
                        <div class="card-body" id="notification-list">
                            <div class="alert alert-info" id="no-notifications-text">
                                No notifications yet. Click "Add New Notification" to create one.
                            </div>
                        </div>
                    </div>

                    <!-- Delete Notification Modal (prototype only) -->
                    <div class="modal fade" id="deleteNotificationModal" tabindex="-1"
                        aria-labelledby="deleteNotificationModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteNotificationModalLabel">
                                        <i class="fas fa-trash-alt me-2"></i>Delete Notification
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-3">Prototype only: in a real system this would request deletion from
                                        the server.</p>
                                    <div class="mb-3">
                                        <label for="selectNotificationToDelete" class="form-label fw-bold">Select a
                                            notification to delete:</label>
                                        <select class="form-select" id="selectNotificationToDelete">
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDeleteNotification"
                                        disabled>
                                        <i class="fas fa-trash me-1"></i>Delete Selected
                                    </button>
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
        const addCard = document.getElementById('add-notification-card');
        const showAddFormBtn = document.getElementById('show-add-form');
        const cancelAddBtn = document.getElementById('cancel-add');
        const addForm = document.getElementById('add-notification-form');
        const includeText = document.getElementById('includeText');
        const includeImage = document.getElementById('includeImage');
        const textSection = document.getElementById('textSection');
        const imageSection = document.getElementById('imageSection');
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = imagePreview ? imagePreview.querySelector('img') : null;
        const removeImageBtn = document.getElementById('removeImage');
        const notificationList = document.getElementById('notification-list');
        const noNotificationsText = document.getElementById('no-notifications-text');
        const showDeleteModalBtn = document.getElementById('show-delete-modal');
        const selectNotificationToDelete = document.getElementById('selectNotificationToDelete');
        const confirmDeleteNotificationBtn = document.getElementById('confirmDeleteNotification');

        let notificationIdCounter = 1;

        // Show/hide add notification form
        showAddFormBtn.addEventListener('click', () => {
            addCard.classList.remove('d-none');
            window.scrollTo({ top: addCard.offsetTop - 70, behavior: 'smooth' });
        });

        cancelAddBtn.addEventListener('click', () => {
            resetForm();
            addCard.classList.add('d-none');
        });

        // Toggle sections
        includeText.addEventListener('change', () => {
            textSection.classList.toggle('d-none', !includeText.checked);
        });

        includeImage.addEventListener('change', () => {
            const show = includeImage.checked;
            imageSection.classList.toggle('d-none', !show);
            if (!show) {
                imageInput.value = '';
                imagePreview.classList.add('d-none');
            }
        });

        // Image preview
        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) {
                imagePreview.classList.add('d-none');
                return;
            }

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file.');
                imageInput.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image size must be less than 5MB.');
                imageInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (ev) {
                previewImg.src = ev.target.result;
                imagePreview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });

        removeImageBtn.addEventListener('click', () => {
            imageInput.value = '';
            imagePreview.classList.add('d-none');
        });

        // Handle form submit
        addForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const session = document.getElementById('session').value.trim();
            const department = document.getElementById('department').value;
            const semester = document.getElementById('semester').value;
            const section = document.getElementById('section').value;
            const message = document.getElementById('message').value.trim();
            const imageFile = imageInput.files[0] || null;

            // Basic validation
            if (!session || !department || !semester || !section) {
                alert('Please fill in session, department, semester and section.');
                return;
            }

            if (!includeText.checked && !includeImage.checked) {
                alert('Please choose at least one content type: text or image.');
                return;
            }

            if (includeText.checked && !message) {
                alert('Please write a message for the notification.');
                return;
            }

            if (includeImage.checked && !imageFile) {
                alert('Please select an image to upload.');
                return;
            }

            const payload = {
                session,
                department,
                semester,
                section,
                text: includeText.checked ? message : null,
                imageName: includeImage.checked && imageFile ? imageFile.name : null
            };

            console.log('New notification:', payload);

            // Add to notification history
            if (noNotificationsText) {
                noNotificationsText.remove();
            }

            const card = document.createElement('div');
            card.className = 'card mb-3 shadow-sm';

            const notificationId = 'n' + notificationIdCounter++;
            card.dataset.notificationId = notificationId;

            const body = document.createElement('div');
            body.className = 'card-body';

            const target = document.createElement('p');
            target.className = 'mb-1 text-muted';
            target.textContent = `To: Session ${session}, ${department}, Semester ${semester}, Section ${section}`;

            if (payload.text) {
                const textP = document.createElement('p');
                textP.textContent = payload.text;
                body.appendChild(textP);
            }

            if (payload.imageName) {
                const imgInfo = document.createElement('p');
                imgInfo.className = 'mb-0 small text-muted';
                imgInfo.textContent = `Image: ${payload.imageName}`;
                body.appendChild(imgInfo);
            }

            body.appendChild(target);
            card.appendChild(body);
            notificationList.prepend(card);

            alert('Notification created (front-end only).');

            resetForm();
            addCard.classList.add('d-none');
        });

        function resetForm() {
            addForm.reset();
            includeText.checked = true;
            includeImage.checked = false;
            textSection.classList.remove('d-none');
            imageSection.classList.add('d-none');
            imageInput.value = '';
            imagePreview.classList.add('d-none');
        }

        // Open delete notification modal with list of notifications
        showDeleteModalBtn.addEventListener('click', () => {
            const cards = notificationList.querySelectorAll('.card');
            if (!cards.length) {
                alert('There are no notifications to delete.');
                return;
            }

            selectNotificationToDelete.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = '-- Choose a notification --';
            selectNotificationToDelete.appendChild(placeholder);

            cards.forEach((card, index) => {
                const option = document.createElement('option');
                option.value = card.dataset.notificationId;

                const textP = card.querySelector('p:not(.text-muted)');
                const text = textP ? textP.textContent.trim() : `Notification ${index + 1}`;
                option.textContent = text.length > 60 ? text.slice(0, 60) + '...' : text;

                selectNotificationToDelete.appendChild(option);
            });

            confirmDeleteNotificationBtn.disabled = true;

            const modal = new bootstrap.Modal(document.getElementById('deleteNotificationModal'));
            modal.show();
        });

        selectNotificationToDelete.addEventListener('change', () => {
            confirmDeleteNotificationBtn.disabled = !selectNotificationToDelete.value;
        });

        confirmDeleteNotificationBtn.addEventListener('click', () => {
            const selectedId = selectNotificationToDelete.value;
            if (!selectedId) return;

            const card = notificationList.querySelector(`.card[data-notification-id="${selectedId}"]`);
            if (card) {
                card.remove();
            }

            if (!notificationList.querySelector('.card')) {
                const info = document.createElement('div');
                info.className = 'alert alert-info';
                info.id = 'no-notifications-text';
                info.textContent = 'No notifications yet. Click "Add New Notification" to create one.';
                notificationList.appendChild(info);
            }

            const modalElement = document.getElementById('deleteNotificationModal');
            const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modal.hide();
        });

        // Logout back to main index
        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.href = '../index.php';
        });
    </script>
</body>

</html>
