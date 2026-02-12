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
        }

        .post-card {
            transition: all 0.3s ease;
        }

        .post-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #198754;
        }

        .badge-rejected {
            background-color: #dc3545;
        }

        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }

        .nav-pills .nav-link {
            color: #0d6efd;
        }

        .post-image {
            max-height: 200px;
            object-fit: cover;
            width: 100%;
            border-radius: 8px;
        }

        .stats-card {
            border-left: 4px solid;
        }

        .stats-card.pending {
            border-left-color: #ffc107;
        }

        .stats-card.approved {
            border-left-color: #198754;
        }

        .stats-card.rejected {
            border-left-color: #dc3545;
        }

        .stats-card.total {
            border-left-color: #0d6efd;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-none d-lg-flex"
        style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Supervisor:</strong> Admin User</span>
                <span>|</span>
                <span><strong>Role:</strong> Community Supervisor</span>
            </div>
        </div>
    </nav>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Supervisor Dashboard</span>
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
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Supervisor
                            Panel</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php"><i
                                class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
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
                    <h2 class="mb-4"><i class="fas fa-clipboard-check me-2"></i>Post Management</h2>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="card stats-card pending shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                    <h3 class="mb-0" id="pendingCount">5</h3>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card stats-card approved shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h3 class="mb-0" id="approvedCount">12</h3>
                                    <small class="text-muted">Approved</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card stats-card rejected shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                    <h3 class="mb-0" id="rejectedCount">3</h3>
                                    <small class="text-muted">Rejected</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card stats-card total shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                    <h3 class="mb-0" id="totalCount">20</h3>
                                    <small class="text-muted">Total Posts</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <ul class="nav nav-pills mb-4" id="postTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-tab" data-bs-toggle="pill"
                                data-bs-target="#pending" type="button" role="tab">
                                <i class="fas fa-clock me-1"></i> Pending <span
                                    class="badge bg-warning text-dark ms-1">5</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="approved-tab" data-bs-toggle="pill" data-bs-target="#approved"
                                type="button" role="tab">
                                <i class="fas fa-check me-1"></i> Approved
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="all-tab" data-bs-toggle="pill" data-bs-target="#all"
                                type="button" role="tab">
                                <i class="fas fa-list me-1"></i> All Posts
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="postTabsContent">
                        <!-- Pending Posts Tab -->
                        <div class="tab-pane fade show active" id="pending" role="tabpanel">
                            <div class="row g-3" id="pendingPosts">
                                <!-- Pending Post 1 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>John Doe
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Computer Science | <i
                                                            class="fas fa-calendar me-1"></i>Feb 12, 2026</small>
                                                </div>
                                                <span class="badge badge-pending"><i
                                                        class="fas fa-clock me-1"></i>Pending</span>
                                            </div>
                                            <p class="card-text">Looking for study partners for the upcoming final
                                                exams. Anyone interested in forming a study group for Data Structures
                                                and Algorithms?</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-success btn-sm" onclick="approvePost(1)">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(1)">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Post 2 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Jane Smith
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Information Technology | <i
                                                            class="fas fa-calendar me-1"></i>Feb 12, 2026</small>
                                                </div>
                                                <span class="badge badge-pending"><i
                                                        class="fas fa-clock me-1"></i>Pending</span>
                                            </div>
                                            <p class="card-text">Sharing some helpful resources for web development.
                                                Check out these free online courses!</p>
                                            <img src="https://via.placeholder.com/400x200" alt="Post Image"
                                                class="post-image mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-success btn-sm" onclick="approvePost(2)">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(2)">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Post 3 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Mike Johnson
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Computer Science | <i
                                                            class="fas fa-calendar me-1"></i>Feb 11, 2026</small>
                                                </div>
                                                <span class="badge badge-pending"><i
                                                        class="fas fa-clock me-1"></i>Pending</span>
                                            </div>
                                            <p class="card-text">Has anyone taken the Machine Learning course with Prof.
                                                Wilson? Looking for tips and recommendations.</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-success btn-sm" onclick="approvePost(3)">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(3)">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Post 4 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Sarah
                                                        Williams</h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Information Technology | <i
                                                            class="fas fa-calendar me-1"></i>Feb 11, 2026</small>
                                                </div>
                                                <span class="badge badge-pending"><i
                                                        class="fas fa-clock me-1"></i>Pending</span>
                                            </div>
                                            <p class="card-text">Organizing a coding bootcamp this weekend. Free entry
                                                for all students!</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-success btn-sm" onclick="approvePost(4)">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(4)">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Post 5 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Alex Brown
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Computer Science | <i
                                                            class="fas fa-calendar me-1"></i>Feb 10, 2026</small>
                                                </div>
                                                <span class="badge badge-pending"><i
                                                        class="fas fa-clock me-1"></i>Pending</span>
                                            </div>
                                            <p class="card-text">Found a great internship opportunity at TechCorp.
                                                Sharing the application link for interested students.</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-success btn-sm" onclick="approvePost(5)">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(5)">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Approved Posts Tab -->
                        <div class="tab-pane fade" id="approved" role="tabpanel">
                            <div class="row g-3" id="approvedPosts">
                                <!-- Approved Post 1 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Emily Davis
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Computer Science | <i
                                                            class="fas fa-calendar me-1"></i>Feb 10, 2026</small>
                                                </div>
                                                <span class="badge badge-approved"><i
                                                        class="fas fa-check me-1"></i>Approved</span>
                                            </div>
                                            <p class="card-text">Great news! Our department just won the
                                                inter-university programming competition!</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(101)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Approved Post 2 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Chris Taylor
                                                    </h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Information Technology | <i
                                                            class="fas fa-calendar me-1"></i>Feb 9, 2026</small>
                                                </div>
                                                <span class="badge badge-approved"><i
                                                        class="fas fa-check me-1"></i>Approved</span>
                                            </div>
                                            <p class="card-text">Reminder: Lab assignment submission deadline is
                                                tomorrow. Make sure to submit on time!</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(102)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Approved Post 3 -->
                                <div class="col-12">
                                    <div class="card post-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><i class="fas fa-user-circle me-2"></i>Lisa
                                                        Anderson</h6>
                                                    <small class="text-muted"><i
                                                            class="fas fa-building me-1"></i>Computer Science | <i
                                                            class="fas fa-calendar me-1"></i>Feb 8, 2026</small>
                                                </div>
                                                <span class="badge badge-approved"><i
                                                        class="fas fa-check me-1"></i>Approved</span>
                                            </div>
                                            <p class="card-text">The new library resources for AI and Machine Learning
                                                are now available. Highly recommend checking them out!</p>
                                            <img src="https://via.placeholder.com/400x200" alt="Post Image"
                                                class="post-image mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(103)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- All Posts Tab -->
                        <div class="tab-pane fade" id="all" role="tabpanel">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control" placeholder="Search posts..."
                                                    id="searchPosts">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="filterStatus">
                                                <option value="all">All Status</option>
                                                <option value="pending">Pending</option>
                                                <option value="approved">Approved</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="filterDepartment">
                                                <option value="all">All Departments</option>
                                                <option value="cs">Computer Science</option>
                                                <option value="it">Information Technology</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary w-100" onclick="filterPosts()">
                                                <i class="fas fa-filter me-1"></i>Filter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover bg-white shadow-sm rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Author</th>
                                            <th>Content</th>
                                            <th>Department</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>John Doe</td>
                                            <td>Looking for study partners...</td>
                                            <td>CS</td>
                                            <td>Feb 12, 2026</td>
                                            <td><span class="badge badge-pending">Pending</span></td>
                                            <td>
                                                <button class="btn btn-success btn-sm" onclick="approvePost(1)"><i
                                                        class="fas fa-check"></i></button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(1)"><i
                                                        class="fas fa-times"></i></button>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(1)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Jane Smith</td>
                                            <td>Sharing helpful resources...</td>
                                            <td>IT</td>
                                            <td>Feb 12, 2026</td>
                                            <td><span class="badge badge-pending">Pending</span></td>
                                            <td>
                                                <button class="btn btn-success btn-sm" onclick="approvePost(2)"><i
                                                        class="fas fa-check"></i></button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(2)"><i
                                                        class="fas fa-times"></i></button>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(2)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Emily Davis</td>
                                            <td>Great news! Our department...</td>
                                            <td>CS</td>
                                            <td>Feb 10, 2026</td>
                                            <td><span class="badge badge-approved">Approved</span></td>
                                            <td>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(101)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Chris Taylor</td>
                                            <td>Reminder: Lab assignment...</td>
                                            <td>IT</td>
                                            <td>Feb 9, 2026</td>
                                            <td><span class="badge badge-approved">Approved</span></td>
                                            <td>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(102)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>David Wilson</td>
                                            <td>Inappropriate content...</td>
                                            <td>CS</td>
                                            <td>Feb 8, 2026</td>
                                            <td><span class="badge badge-rejected">Rejected</span></td>
                                            <td>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="showDeleteModal(201)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reject Post Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel"><i class="fas fa-times-circle me-2"></i>Reject Post
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rejectPostId">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label fw-bold">Reason for rejection:</label>
                        <select class="form-select mb-3" id="rejectReasonSelect">
                            <option value="" selected disabled>-- Select a reason --</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="spam">Spam or promotional content</option>
                            <option value="duplicate">Duplicate post</option>
                            <option value="offensive">Offensive language</option>
                            <option value="other">Other (specify below)</option>
                        </select>
                        <textarea class="form-control" id="rejectReason" rows="3"
                            placeholder="Additional comments (optional)"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        The author will be notified about this rejection along with the reason.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="rejectPost()">
                        <i class="fas fa-times me-1"></i>Reject Post
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Post Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt me-2"></i>Delete Post</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="deletePostId">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. The post will be permanently deleted.
                    </div>
                    <p>Are you sure you want to delete this post?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deletePost()">
                        <i class="fas fa-trash me-1"></i>Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <i class="fas fa-check-circle me-2"></i>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                Operation completed successfully.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Logout functionality
        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.href = '../index.php';
        });

        // Show reject modal
        function showRejectModal(postId) {
            document.getElementById('rejectPostId').value = postId;
            document.getElementById('rejectReasonSelect').value = '';
            document.getElementById('rejectReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }

        // Show delete modal
        function showDeleteModal(postId) {
            document.getElementById('deletePostId').value = postId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Approve post
        function approvePost(postId) {
            // In a real application, this would send a request to the server
            showToast('Post approved successfully!');
            // Update UI - remove from pending, add to approved
            console.log('Approved post:', postId);
        }

        // Reject post
        function rejectPost() {
            const postId = document.getElementById('rejectPostId').value;
            const reasonSelect = document.getElementById('rejectReasonSelect').value;
            const reasonText = document.getElementById('rejectReason').value;

            if (!reasonSelect) {
                alert('Please select a rejection reason.');
                return;
            }

            // In a real application, this would send a request to the server
            const modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
            modal.hide();
            showToast('Post rejected successfully!');
            console.log('Rejected post:', postId, 'Reason:', reasonSelect, reasonText);
        }

        // Delete post
        function deletePost() {
            const postId = document.getElementById('deletePostId').value;
            // In a real application, this would send a request to the server
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
            modal.hide();
            showToast('Post deleted successfully!');
            console.log('Deleted post:', postId);
        }

        // Filter posts
        function filterPosts() {
            const search = document.getElementById('searchPosts').value;
            const status = document.getElementById('filterStatus').value;
            const department = document.getElementById('filterDepartment').value;
            // In a real application, this would filter the posts based on criteria
            console.log('Filtering:', { search, status, department });
        }

        // Show toast notification
        function showToast(message) {
            document.getElementById('toastMessage').textContent = message;
            const toast = new bootstrap.Toast(document.getElementById('successToast'));
            toast.show();
        }
    </script>
</body>

</html>
