<?php
session_start();
include '../config.php';
require_once __DIR__ . '/teacher_guard.php';

$teacher = requireTeacherIdentity($conn);
$teacherUserId = (int)$teacher['user_id'];

$error = '';
$success = '';

if (isset($_SESSION['community_flash']) && is_array($_SESSION['community_flash'])) {
    $flashType = $_SESSION['community_flash']['type'] ?? '';
    $flashMessage = $_SESSION['community_flash']['message'] ?? '';
    if ($flashType === 'success') $success = (string)$flashMessage;
    elseif ($flashType === 'error') $error = (string)$flashMessage;
    unset($_SESSION['community_flash']);
}

if (!function_exists('communityRedirectWithFlash')) {
    function communityRedirectWithFlash(string $type, string $message): void {
        $_SESSION['community_flash'] = ['type' => $type, 'message' => $message];
        header('Location: community.php');
        exit();
    }
}

$conn->query("DELETE FROM posts WHERE expires_at < NOW()");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
    $postId = (int)$_POST['post_id'];
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $postId, $teacherUserId);
    if ($stmt->execute() && $stmt->affected_rows > 0) communityRedirectWithFlash('success', 'Post deleted successfully!');
    else communityRedirectWithFlash('error', 'Failed to delete post or post not found.');
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $scope = $_POST['scope'] ?? 'department';
    $content = trim($_POST['postContent'] ?? '');
    $expiresIn = (int)($_POST['expires_in'] ?? 15);
    $includeText = isset($_POST['include_text']);
    $includeImage = isset($_POST['include_image']);

    if (!$includeText && !$includeImage) $error = "Please select at least one content type.";
    elseif ($includeText && empty($content)) $error = "Please write a message for the post.";
    elseif ($includeImage && (!isset($_FILES['postImage']) || $_FILES['postImage']['error'] == UPLOAD_ERR_NO_FILE)) $error = "Please select an image to upload.";
    else {
        $imageData = null; $imageType = null; $imageSize = null;
        if ($includeImage && isset($_FILES['postImage']) && $_FILES['postImage']['error'] == UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024;
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($_FILES['postImage']['size'] > $maxSize) $error = "Image must be under 2MB.";
            elseif (!in_array($_FILES['postImage']['type'], $allowedTypes)) $error = "Only JPG, PNG, and GIF images are allowed.";
            else {
                $imageData = file_get_contents($_FILES['postImage']['tmp_name']);
                $imageType = $_FILES['postImage']['type'];
                $imageSize = $_FILES['postImage']['size'];
            }
        }
        if (empty($error)) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresIn days"));
            $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image_data, image_type, image_size, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiss", $teacherUserId, $content, $imageData, $imageType, $imageSize, $scope, $expiresAt);
            if ($stmt->execute()) communityRedirectWithFlash('success', "Post created successfully! It will expire in $expiresIn days.");
            else $error = "Failed to create post: " . $conn->error;
            $stmt->close();
        }
    }
    if ($error !== '') communityRedirectWithFlash('error', $error);
}

$teacherDepartment = trim((string)($teacher['department'] ?? ''));
$teacherDepartmentNormalized = mb_strtolower($teacherDepartment);

$posts = [];
$stmt = $conn->prepare("SELECT p.*, u.email, COALESCE(s.name, t.name) as poster_name, s.Roll_no as poster_roll, COALESCE(s.department, t.department) as poster_department
    FROM posts p LEFT JOIN user u ON p.user_id = u.user_id LEFT JOIN student s ON p.user_id = s.student_id LEFT JOIN teacher t ON p.user_id = t.teacher_id
    WHERE p.expires_at > NOW() AND p.status = 'approved'
    AND (p.scope = 'all' OR (p.scope = 'department' AND (LOWER(TRIM(s.department)) = ? OR LOWER(TRIM(t.department)) = ?)))
    ORDER BY p.created_at DESC");
$stmt->bind_param("ss", $teacherDepartmentNormalized, $teacherDepartmentNormalized);
$stmt->execute();
$result = $stmt->get_result();
if ($result) while ($row = $result->fetch_assoc()) $posts[] = $row;
$stmt->close();

$userPosts = [];
$stmt = $conn->prepare("SELECT post_id, content, created_at FROM posts WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC");
$stmt->bind_param("i", $teacherUserId);
$stmt->execute();
$userPostsResult = $stmt->get_result();
while ($row = $userPostsResult->fetch_assoc()) $userPosts[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduCompliance - Teacher Community</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                colors: {
                    'error': '#ba1a1a', 'secondary': '#0040e0', 'secondary-container': '#2e5bff',
                    'on-secondary-fixed-variant': '#0035be', 'on-primary-container': '#7c839b',
                    'on-surface': '#1b1b1d', 'error-container': '#ffdad6', 'on-error-container': '#93000a'
                },
                fontFamily: {
                    h3: ['Sora','sans-serif'], h2: ['Sora','sans-serif'], h1: ['Sora','sans-serif'],
                    'label-caps': ['Sora','sans-serif'], 'body-md': ['Source Sans 3','sans-serif'],
                    'body-sm': ['Source Sans 3','sans-serif'], 'data-tabular': ['Source Sans 3','sans-serif']
                }
            }}
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { background-color: #F8FAFC; }
    </style>
</head>
<body class="font-body-md text-on-surface">

<!-- Sidebar -->
<aside class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl">
    <div class="p-6 flex items-center gap-3">
        <div class="w-10 h-10 bg-secondary-container rounded flex items-center justify-center">
            <span class="material-symbols-outlined text-white">school</span>
        </div>
        <div>
            <h1 class="text-white text-xl font-bold tracking-tight font-h1">EduCompliance</h1>
            <p class="text-slate-400 text-xs font-label-caps">Academic Administration</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-4 space-y-1">
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="dashboard.php">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-600 transition-all font-h3 text-sm" href="community.php">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">campaign</span>Community
        </a>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm" href="settings.php">
            <span class="material-symbols-outlined">settings</span>Settings
        </a>
    </nav>
    <div class="px-4 py-4 border-t border-slate-800">
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-h3 text-sm">
            <span class="material-symbols-outlined">logout</span>Logout
        </a>
    </div>
</aside>

<!-- Top Bar -->
<header class="fixed top-0 right-0 left-[280px] h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-8 z-40 shadow-sm">
    <div class="flex items-center gap-4 flex-1">
        <div class="relative w-full max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
            <input class="w-full bg-white border border-slate-200 rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 ring-blue-500/20 outline-none" placeholder="Search community posts..." type="text">
        </div>
    </div>
    <div class="flex items-center gap-4">
        <button class="hover:bg-slate-100 rounded-lg p-2 transition-all">
            <span class="material-symbols-outlined text-slate-600">notifications</span>
        </button>
        <div class="h-8 w-[1px] bg-slate-200"></div>
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-sm font-bold text-slate-900 leading-none"><?php echo htmlspecialchars($teacher['name']); ?></p>
                <p class="text-[10px] font-label-caps text-secondary mt-1">FACULTY · <?php echo htmlspecialchars($teacher['department']); ?></p>
            </div>
            <div class="w-9 h-9 rounded-full bg-secondary flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                <?php echo strtoupper(substr($teacher['name'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="ml-[280px] mt-16 p-6">
    <div class="max-w-5xl mx-auto space-y-6">

        <?php if ($error): ?>
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
            <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1;">error</span>
            <p class="text-red-800 font-body-sm"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-start gap-3">
            <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <p class="text-emerald-800 font-body-sm"><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex items-end justify-between">
            <div>
                <h2 class="font-h1 text-3xl font-bold text-slate-900">Community Feed</h2>
                <p class="font-body-md text-slate-500 mt-1">Institutional updates and faculty-led community discussions.</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold border border-blue-100">
                <span class="material-symbols-outlined text-xs mr-1" style="font-variation-settings: 'FILL' 1;">verified</span>Faculty Member
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Create Post & Stats -->
            <div class="lg:col-span-4 space-y-6">
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="font-h3 text-lg text-slate-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">edit_square</span>New Post
                    </h3>
                    <form method="POST" enctype="multipart/form-data" id="addPostForm" class="space-y-4">
                        <textarea name="postContent" id="postContent" class="w-full min-h-[100px] rounded-lg border border-slate-200 font-body-sm text-sm resize-none p-3 placeholder:text-slate-400 focus:ring-2 ring-blue-500/20 outline-none" placeholder="What's on your mind?"></textarea>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-label-caps text-slate-500 mb-1">Post Scope</label>
                                <select name="scope" class="w-full text-sm rounded-lg border border-slate-200 py-1.5 focus:ring-2 ring-blue-500/20 outline-none">
                                    <option value="department">Department Only</option>
                                    <option value="all">All University</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-label-caps text-slate-500 mb-1">Expiry</label>
                                <select name="expires_in" class="w-full text-sm rounded-lg border border-slate-200 py-1.5 focus:ring-2 ring-blue-500/20 outline-none">
                                    <option value="7">7 Days</option>
                                    <option value="15" selected>15 Days</option>
                                    <option value="30">30 Days</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="include_text" id="includeText" checked class="rounded"> Text
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="include_image" id="includeImage" class="rounded"> Image
                            </label>
                        </div>
                        <div id="imageSection" class="hidden">
                            <input type="file" name="postImage" id="postImage" accept="image/*" class="w-full text-sm border border-slate-200 rounded-lg p-2">
                            <div id="imagePreview" class="mt-2 hidden">
                                <img src="" alt="Preview" class="rounded max-h-32 object-cover">
                                <button type="button" id="removeImage" class="text-xs text-red-600 mt-1">Remove</button>
                            </div>
                        </div>
                        <button type="submit" name="create_post" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-h3 text-sm px-4 py-2.5 rounded-lg transition-all shadow-md shadow-blue-600/20">
                            Post Update
                        </button>
                    </form>
                </section>

                <!-- Stats -->
                <div class="bg-[#0F172A] rounded-xl p-6 text-white overflow-hidden relative">
                    <div class="relative z-10">
                        <h4 class="font-h3 text-sm opacity-80 mb-4">Feed Overview</h4>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center border-b border-slate-700 pb-3">
                                <span class="text-xs">Active Posts</span>
                                <span class="font-bold text-blue-400"><?php echo count($posts); ?></span>
                            </div>
                            <div class="flex justify-between items-center border-b border-slate-700 pb-3">
                                <span class="text-xs">Your Posts</span>
                                <span class="font-bold text-emerald-400"><?php echo count($userPosts); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs">Department</span>
                                <span class="font-bold text-slate-300 text-xs"><?php echo htmlspecialchars($teacher['department']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <span class="material-symbols-outlined text-[100px]">analytics</span>
                    </div>
                </div>

                <!-- Your Posts (for deletion) -->
                <?php if (!empty($userPosts)): ?>
                <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="font-h3 text-sm text-slate-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-500 text-lg">person</span>Your Posts
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($userPosts as $userPost): ?>
                        <form method="POST" onsubmit="return confirm('Delete this post? This cannot be undone.');">
                            <input type="hidden" name="post_id" value="<?php echo $userPost['post_id']; ?>">
                            <div class="flex items-center justify-between p-2 rounded-lg border border-slate-100 hover:bg-slate-50 gap-2">
                                <p class="text-xs text-slate-600 truncate flex-1">
                                    <?php
                                    $preview = !empty($userPost['content']) ? substr($userPost['content'], 0, 40) : '[Image Post]';
                                    echo htmlspecialchars($preview) . (strlen($userPost['content'] ?? '') > 40 ? '...' : '');
                                    ?>
                                </p>
                                <button type="submit" name="delete_post" class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </div>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <!-- Right: Feed -->
            <div class="lg:col-span-8 space-y-4">
                <?php if (empty($posts)): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
                    <span class="material-symbols-outlined text-slate-300 text-5xl mb-3 block">campaign</span>
                    <p class="font-body-sm text-slate-500">No posts yet. Be the first to post!</p>
                </div>
                <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <article class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center text-secondary font-bold text-sm">
                                    <?php echo strtoupper(substr($post['poster_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <h4 class="font-h3 text-sm text-slate-900">
                                        <?php echo htmlspecialchars($post['poster_name'] ?? 'Unknown'); ?>
                                        <span class="ml-2 px-2 py-0.5 rounded bg-slate-100 text-[10px] text-slate-500 font-label-caps"><?php echo htmlspecialchars($post['poster_department'] ?? ''); ?></span>
                                    </h4>
                                    <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
                                        <span><?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></span>
                                        <span>•</span>
                                        <span><?php echo $post['scope'] == 'all' ? 'All University' : 'Department Only'; ?></span>
                                        <?php $daysLeft = ceil((strtotime($post['expires_at']) - time()) / 86400); ?>
                                        <span class="<?php echo $daysLeft <= 3 ? 'text-red-500' : 'text-slate-400'; ?>">· Expires in <?php echo $daysLeft; ?>d</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($post['content'])): ?>
                        <p class="font-body-md text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($post['image_data'])): ?>
                        <div class="mt-4">
                            <img src="data:<?php echo $post['image_type']; ?>;base64,<?php echo base64_encode($post['image_data']); ?>" class="w-full rounded-lg border border-slate-100 max-h-64 object-cover">
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    document.getElementById('includeImage').addEventListener('change', function() {
        document.getElementById('imageSection').classList.toggle('hidden', !this.checked);
        if (!this.checked) {
            document.getElementById('postImage').value = '';
            document.getElementById('imagePreview').classList.add('hidden');
        }
    });

    document.getElementById('postImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) { alert('Image must be under 2MB.'); this.value = ''; return; }
            const reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('#imagePreview img').src = e.target.result;
                document.getElementById('imagePreview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('removeImage').addEventListener('click', function() {
        document.getElementById('postImage').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
    });
</script>
</body>
</html>