<?php
session_start();
include '../config.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$importErrors = [];
$adminId = (int)$_SESSION['user_id'];

function finalizeImportRequest(string $success, string $error, array $importErrors): void
{
    $_SESSION['import_flash'] = [
        'success' => $success,
        'error' => $error,
        'import_errors' => $importErrors,
    ];

    header('Location: dashboard.php');
    exit();
}

if (isset($_SESSION['import_flash']) && is_array($_SESSION['import_flash'])) {
    $flash = $_SESSION['import_flash'];
    unset($_SESSION['import_flash']);

    $success = (string)($flash['success'] ?? '');
    $error = (string)($flash['error'] ?? '');
    $importErrors = is_array($flash['import_errors'] ?? null) ? $flash['import_errors'] : [];
}

$adminDepartment = '';
$adminProfileStmt = $conn->prepare("SELECT department FROM super_admin WHERE super_admin_id = ?");
$adminProfileStmt->bind_param("i", $adminId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult ? $adminProfileResult->fetch_assoc() : null;
$adminProfileStmt->close();

if (!$adminProfile || empty($adminProfile['department'])) {
    $error = 'Super Admin department profile is missing. Please configure super_admin table entry first.';
} else {
    $adminDepartment = trim((string)$adminProfile['department']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_students'])) {
    if (!isset($_FILES['student_excel']) || $_FILES['student_excel']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid Excel/CSV file to upload.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } else {
        $fileTmpPath = $_FILES['student_excel']['tmp_name'];
        $fileName = $_FILES['student_excel']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'csv', 'ods'];

        if (!in_array($fileExt, $allowedExtensions, true)) {
            $error = 'Invalid file type. Allowed: .xlsx, .csv, .ods';
        } else {
            try {
                $reader = match ($fileExt) {
                    'xlsx' => new \OpenSpout\Reader\XLSX\Reader(),
                    'csv' => new \OpenSpout\Reader\CSV\Reader(),
                    'ods' => new \OpenSpout\Reader\ODS\Reader(),
                    default => throw new Exception('Unsupported file type.'),
                };
                $reader->open($fileTmpPath);

                $headerMap = [];
                $processedRows = 0;
                $createdUsers = 0;
                $updatedUsers = 0;
                $upsertedStudents = 0;

                $conn->begin_transaction();

                $selectUserStmt = $conn->prepare("SELECT user_id, role FROM user WHERE email = ?");
                $insertUserStmt = $conn->prepare("INSERT INTO user (email, password, role) VALUES (?, ?, 'student')");
                $updateUserPasswordStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
                $rollConflictStmt = $conn->prepare("SELECT student_id FROM student WHERE Roll_no = ? AND student_id <> ? LIMIT 1");

                $studentHasEmailColumn = false;
                $studentEmailColResult = $conn->query("SHOW COLUMNS FROM student LIKE 'email'");
                if ($studentEmailColResult && $studentEmailColResult->num_rows > 0) {
                    $studentHasEmailColumn = true;
                }

                if ($studentHasEmailColumn) {
                    $upsertStudentStmt = $conn->prepare("INSERT INTO student (student_id, name, Roll_no, department, semester_no, section, session, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), Roll_no = VALUES(Roll_no), department = VALUES(department), semester_no = VALUES(semester_no), section = VALUES(section), session = VALUES(session), email = VALUES(email)");
                } else {
                    $upsertStudentStmt = $conn->prepare("INSERT INTO student (student_id, name, Roll_no, department, semester_no, section, session) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), Roll_no = VALUES(Roll_no), department = VALUES(department), semester_no = VALUES(semester_no), section = VALUES(section), session = VALUES(session)");
                }

                $requiredColumns = [
                    'email' => ['email', 'student_email'],
                    'name' => ['name', 'student_name'],
                    'password' => ['password', 'pass', 'default_password'],
                    'roll_no' => ['roll_no', 'rollno', 'roll_number'],
                    'session' => ['session'],
                    'department' => ['department', 'dept'],
                    'semester_no' => ['semester_no', 'semester', 'sem', 'semester_number']
                ];

                $optionalColumns = [
                    'section' => ['section', 'sec']
                ];

                $getColumnValue = function (array $rowCells, array $map, array $aliases): string {
                    foreach ($aliases as $alias) {
                        if (isset($map[$alias])) {
                            $index = $map[$alias];
                            return trim((string)($rowCells[$index] ?? ''));
                        }
                    }
                    return '';
                };

                $seenEmails = [];
                $seenRollNumbers = [];

                $sheetHandled = false;
                foreach ($reader->getSheetIterator() as $sheet) {
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        $cells = array_map(static fn($cell) => trim((string)$cell), $row->toArray());

                        if ($rowNumber === 1) {
                            foreach ($cells as $index => $header) {
                                $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', $header));
                                $normalized = trim($normalized, '_');
                                if ($normalized !== '') {
                                    $headerMap[$normalized] = $index;
                                }
                            }

                            foreach ($requiredColumns as $field => $aliases) {
                                $exists = false;
                                foreach ($aliases as $alias) {
                                    if (isset($headerMap[$alias])) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                if (!$exists) {
                                    $importErrors[] = "Missing required column for '{$field}'.";
                                }
                            }
                            continue;
                        }

                        if (count(array_filter($cells, static fn($value) => $value !== '')) === 0) {
                            continue;
                        }

                        $processedRows++;

                        $email = strtolower($getColumnValue($cells, $headerMap, $requiredColumns['email']));
                        $name = $getColumnValue($cells, $headerMap, $requiredColumns['name']);
                        $password = $getColumnValue($cells, $headerMap, $requiredColumns['password']);
                        $rollNo = $getColumnValue($cells, $headerMap, $requiredColumns['roll_no']);
                        $sessionValue = $getColumnValue($cells, $headerMap, $requiredColumns['session']);
                        $department = $getColumnValue($cells, $headerMap, $requiredColumns['department']);
                        $semesterRaw = $getColumnValue($cells, $headerMap, $requiredColumns['semester_no']);
                        $semesterNo = (int)$semesterRaw;
                        $section = strtoupper($getColumnValue($cells, $headerMap, $optionalColumns['section']));
                        if ($section === '') {
                            $section = 'A';
                        }

                        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $importErrors[] = "Row {$rowNumber}: Invalid or missing email.";
                            continue;
                        }
                        if (isset($seenEmails[$email])) {
                            $importErrors[] = "Row {$rowNumber}: Duplicate email '{$email}' found in uploaded file.";
                            continue;
                        }
                        if ($name === '') {
                            $importErrors[] = "Row {$rowNumber}: Name is required.";
                            continue;
                        }
                        if ($password === '') {
                            $importErrors[] = "Row {$rowNumber}: Password is required.";
                            continue;
                        }
                        if ($rollNo === '') {
                            $importErrors[] = "Row {$rowNumber}: Roll number is required.";
                            continue;
                        }
                        if (isset($seenRollNumbers[$rollNo])) {
                            $importErrors[] = "Row {$rowNumber}: Duplicate roll number '{$rollNo}' found in uploaded file.";
                            continue;
                        }
                        if ($sessionValue === '') {
                            $importErrors[] = "Row {$rowNumber}: Session is required.";
                            continue;
                        }
                        if ($department === '') {
                            $importErrors[] = "Row {$rowNumber}: Department is required.";
                            continue;
                        }
                        if ($adminDepartment !== '' && strcasecmp(trim($department), trim($adminDepartment)) !== 0) {
                            $importErrors[] = "Row {$rowNumber}: Department '{$department}' is outside your scope ({$adminDepartment}).";
                            continue;
                        }
                        if ($semesterNo < 1 || $semesterNo > 12) {
                            $importErrors[] = "Row {$rowNumber}: Semester must be between 1 and 12.";
                            continue;
                        }
                        if (!preg_match('/^[A-Z0-9_-]{1,10}$/', $section)) {
                            $importErrors[] = "Row {$rowNumber}: Section is invalid. Use alphanumeric values like A or B.";
                            continue;
                        }

                        $seenEmails[$email] = true;
                        $seenRollNumbers[$rollNo] = true;

                        $hashedPassword = hashPasswordArgon2id($password);

                        $selectUserStmt->bind_param("s", $email);
                        $selectUserStmt->execute();
                        $userResult = $selectUserStmt->get_result();

                        $userId = 0;
                        if ($userResult && $userResult->num_rows > 0) {
                            $existingUser = $userResult->fetch_assoc();
                            if (($existingUser['role'] ?? '') !== 'student') {
                                $importErrors[] = "Row {$rowNumber}: Email already belongs to a non-student role.";
                                continue;
                            }

                            $userId = (int)$existingUser['user_id'];
                            $updateUserPasswordStmt->bind_param("si", $hashedPassword, $userId);
                            if (!$updateUserPasswordStmt->execute()) {
                                $importErrors[] = "Row {$rowNumber}: Failed to update student password.";
                                continue;
                            }
                            $updatedUsers++;
                        } else {
                            $insertUserStmt->bind_param("ss", $email, $hashedPassword);
                            if (!$insertUserStmt->execute()) {
                                $importErrors[] = "Row {$rowNumber}: Failed to create user ({$conn->error}).";
                                continue;
                            }

                            $userId = (int)$conn->insert_id;
                            $createdUsers++;
                        }

                        $rollConflictStmt->bind_param("si", $rollNo, $userId);
                        $rollConflictStmt->execute();
                        $rollConflictResult = $rollConflictStmt->get_result();
                        if ($rollConflictResult && $rollConflictResult->num_rows > 0) {
                            $importErrors[] = "Row {$rowNumber}: Roll number '{$rollNo}' is already assigned to another student.";
                            continue;
                        }

                        if ($studentHasEmailColumn) {
                            $upsertStudentStmt->bind_param("isssisss", $userId, $name, $rollNo, $department, $semesterNo, $section, $sessionValue, $email);
                        } else {
                            $upsertStudentStmt->bind_param("isssiss", $userId, $name, $rollNo, $department, $semesterNo, $section, $sessionValue);
                        }
                        if (!$upsertStudentStmt->execute()) {
                            $importErrors[] = "Row {$rowNumber}: Failed to upsert student profile ({$conn->error}).";
                            continue;
                        }
                        $upsertedStudents++;
                    }

                    $sheetHandled = true;
                    break;
                }

                $reader->close();

                $selectUserStmt->close();
                $insertUserStmt->close();
                $updateUserPasswordStmt->close();
                $rollConflictStmt->close();
                $upsertStudentStmt->close();

                if (!$sheetHandled) {
                    $importErrors[] = 'Uploaded file did not contain readable sheets.';
                }

                if (!empty($importErrors)) {
                    $conn->rollback();
                    $error = 'Import failed. Found ' . count($importErrors) . ' issue(s).';
                } else {
                    $conn->commit();
                    $success = "Import completed: {$processedRows} row(s) processed, {$createdUsers} new user(s), {$updatedUsers} existing user(s) updated, {$upsertedStudents} student profile(s) synced.";
                }
            } catch (Throwable $exception) {
                if ($conn->errno) {
                    $conn->rollback();
                }
                $error = 'Import failed: ' . $exception->getMessage();
            }
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_teachers'])) {
    if (!isset($_FILES['teacher_excel']) || $_FILES['teacher_excel']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid teacher Excel/CSV file to upload.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } else {
        $fileTmpPath = $_FILES['teacher_excel']['tmp_name'];
        $fileName = $_FILES['teacher_excel']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'csv', 'ods'];

        if (!in_array($fileExt, $allowedExtensions, true)) {
            $error = 'Invalid teacher file type. Allowed: .xlsx, .csv, .ods';
        } else {
            try {
                $reader = match ($fileExt) {
                    'xlsx' => new \OpenSpout\Reader\XLSX\Reader(),
                    'csv' => new \OpenSpout\Reader\CSV\Reader(),
                    'ods' => new \OpenSpout\Reader\ODS\Reader(),
                    default => throw new Exception('Unsupported file type.'),
                };
                $reader->open($fileTmpPath);

                $headerMap = [];
                $processedRows = 0;
                $createdUsers = 0;
                $updatedUsers = 0;
                $upsertedTeachers = 0;

                $conn->begin_transaction();

                $selectUserStmt = $conn->prepare("SELECT user_id, role FROM user WHERE email = ?");
                $insertUserStmt = $conn->prepare("INSERT INTO user (email, password, role) VALUES (?, ?, 'teacher')");
                $updateUserPasswordStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
                $upsertTeacherStmt = $conn->prepare("INSERT INTO teacher (teacher_id, name, department) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), department = VALUES(department)");

                $requiredColumns = [
                    'email' => ['email', 'teacher_email'],
                    'name' => ['name', 'teacher_name'],
                    'password' => ['password', 'pass', 'default_password'],
                    'department' => ['department', 'dept']
                ];

                $getColumnValue = function (array $rowCells, array $map, array $aliases): string {
                    foreach ($aliases as $alias) {
                        if (isset($map[$alias])) {
                            $index = $map[$alias];
                            return trim((string)($rowCells[$index] ?? ''));
                        }
                    }
                    return '';
                };

                $seenEmails = [];
                $sheetHandled = false;

                foreach ($reader->getSheetIterator() as $sheet) {
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        $cells = array_map(static fn($cell) => trim((string)$cell), $row->toArray());

                        if ($rowNumber === 1) {
                            foreach ($cells as $index => $header) {
                                $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', $header));
                                $normalized = trim($normalized, '_');
                                if ($normalized !== '') {
                                    $headerMap[$normalized] = $index;
                                }
                            }

                            foreach ($requiredColumns as $field => $aliases) {
                                $exists = false;
                                foreach ($aliases as $alias) {
                                    if (isset($headerMap[$alias])) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                if (!$exists) {
                                    $importErrors[] = "Missing required column for '{$field}' in teacher file.";
                                }
                            }
                            continue;
                        }

                        if (count(array_filter($cells, static fn($value) => $value !== '')) === 0) {
                            continue;
                        }

                        $processedRows++;
                        $email = strtolower($getColumnValue($cells, $headerMap, $requiredColumns['email']));
                        $name = $getColumnValue($cells, $headerMap, $requiredColumns['name']);
                        $password = $getColumnValue($cells, $headerMap, $requiredColumns['password']);
                        $department = $getColumnValue($cells, $headerMap, $requiredColumns['department']);

                        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $importErrors[] = "Teacher row {$rowNumber}: Invalid or missing email.";
                            continue;
                        }
                        if (isset($seenEmails[$email])) {
                            $importErrors[] = "Teacher row {$rowNumber}: Duplicate email '{$email}' in uploaded file.";
                            continue;
                        }
                        if ($name === '') {
                            $importErrors[] = "Teacher row {$rowNumber}: Name is required.";
                            continue;
                        }
                        if ($password === '') {
                            $importErrors[] = "Teacher row {$rowNumber}: Password is required.";
                            continue;
                        }
                        if ($department === '') {
                            $importErrors[] = "Teacher row {$rowNumber}: Department is required.";
                            continue;
                        }
                        if ($adminDepartment !== '' && strcasecmp(trim($department), trim($adminDepartment)) !== 0) {
                            $importErrors[] = "Teacher row {$rowNumber}: Department '{$department}' is outside your scope ({$adminDepartment}).";
                            continue;
                        }

                        $seenEmails[$email] = true;
                        $hashedPassword = hashPasswordArgon2id($password);

                        $selectUserStmt->bind_param("s", $email);
                        $selectUserStmt->execute();
                        $userResult = $selectUserStmt->get_result();

                        $userId = 0;
                        if ($userResult && $userResult->num_rows > 0) {
                            $existingUser = $userResult->fetch_assoc();
                            if (($existingUser['role'] ?? '') !== 'teacher') {
                                $importErrors[] = "Teacher row {$rowNumber}: Email already belongs to a non-teacher role.";
                                continue;
                            }

                            $userId = (int)$existingUser['user_id'];
                            $updateUserPasswordStmt->bind_param("si", $hashedPassword, $userId);
                            if (!$updateUserPasswordStmt->execute()) {
                                $importErrors[] = "Teacher row {$rowNumber}: Failed to update teacher password.";
                                continue;
                            }
                            $updatedUsers++;
                        } else {
                            $insertUserStmt->bind_param("ss", $email, $hashedPassword);
                            if (!$insertUserStmt->execute()) {
                                $importErrors[] = "Teacher row {$rowNumber}: Failed to create user ({$conn->error}).";
                                continue;
                            }
                            $userId = (int)$conn->insert_id;
                            $createdUsers++;
                        }

                        $upsertTeacherStmt->bind_param("iss", $userId, $name, $department);
                        if (!$upsertTeacherStmt->execute()) {
                            $importErrors[] = "Teacher row {$rowNumber}: Failed to upsert teacher profile ({$conn->error}).";
                            continue;
                        }
                        $upsertedTeachers++;
                    }

                    $sheetHandled = true;
                    break;
                }

                $reader->close();
                $selectUserStmt->close();
                $insertUserStmt->close();
                $updateUserPasswordStmt->close();
                $upsertTeacherStmt->close();

                if (!$sheetHandled) {
                    $importErrors[] = 'Teacher file did not contain readable sheets.';
                }

                if (!empty($importErrors)) {
                    $conn->rollback();
                    $error = 'Teacher import failed. Found ' . count($importErrors) . ' issue(s).';
                } else {
                    $conn->commit();
                    $success = "Teacher import completed: {$processedRows} row(s), {$createdUsers} new user(s), {$updatedUsers} existing user(s) updated, {$upsertedTeachers} teacher profile(s) synced.";
                }
            } catch (Throwable $exception) {
                if ($conn->errno) {
                    $conn->rollback();
                }
                $error = 'Teacher import failed: ' . $exception->getMessage();
            }
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_courses'])) {
    if (!isset($_FILES['course_excel']) || $_FILES['course_excel']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid course Excel/CSV file to upload.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } else {
        $fileTmpPath = $_FILES['course_excel']['tmp_name'];
        $fileName = $_FILES['course_excel']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'csv', 'ods'];

        if (!in_array($fileExt, $allowedExtensions, true)) {
            $error = 'Invalid course file type. Allowed: .xlsx, .csv, .ods';
        } else {
            try {
                $reader = match ($fileExt) {
                    'xlsx' => new \OpenSpout\Reader\XLSX\Reader(),
                    'csv' => new \OpenSpout\Reader\CSV\Reader(),
                    'ods' => new \OpenSpout\Reader\ODS\Reader(),
                    default => throw new Exception('Unsupported file type.'),
                };
                $reader->open($fileTmpPath);

                $headerMap = [];
                $processedRows = 0;
                $upsertedCourses = 0;

                $conn->begin_transaction();

                $upsertCourseStmt = $conn->prepare("INSERT INTO courses (course_code, course_title, department, semester_no, credit_hours) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE course_title = VALUES(course_title), department = VALUES(department), semester_no = VALUES(semester_no), credit_hours = VALUES(credit_hours)");

                $requiredColumns = [
                    'course_code' => ['course_code', 'code'],
                    'course_title' => ['course_title', 'title', 'course_name'],
                    'department' => ['department', 'dept'],
                    'semester_no' => ['semester_no', 'semester', 'sem']
                ];

                $optionalColumns = [
                    'credit_hours' => ['credit_hours', 'credits', 'credit']
                ];

                $getColumnValue = function (array $rowCells, array $map, array $aliases): string {
                    foreach ($aliases as $alias) {
                        if (isset($map[$alias])) {
                            $index = $map[$alias];
                            return trim((string)($rowCells[$index] ?? ''));
                        }
                    }
                    return '';
                };

                $seenCourseCodes = [];
                $sheetHandled = false;

                foreach ($reader->getSheetIterator() as $sheet) {
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        $cells = array_map(static fn($cell) => trim((string)$cell), $row->toArray());

                        if ($rowNumber === 1) {
                            foreach ($cells as $index => $header) {
                                $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', $header));
                                $normalized = trim($normalized, '_');
                                if ($normalized !== '') {
                                    $headerMap[$normalized] = $index;
                                }
                            }

                            foreach ($requiredColumns as $field => $aliases) {
                                $exists = false;
                                foreach ($aliases as $alias) {
                                    if (isset($headerMap[$alias])) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                if (!$exists) {
                                    $importErrors[] = "Missing required column for '{$field}' in course file.";
                                }
                            }
                            continue;
                        }

                        if (count(array_filter($cells, static fn($value) => $value !== '')) === 0) {
                            continue;
                        }

                        $processedRows++;

                        $courseCode = strtoupper($getColumnValue($cells, $headerMap, $requiredColumns['course_code']));
                        $courseTitle = $getColumnValue($cells, $headerMap, $requiredColumns['course_title']);
                        $department = $getColumnValue($cells, $headerMap, $requiredColumns['department']);
                        $semesterNo = (int)$getColumnValue($cells, $headerMap, $requiredColumns['semester_no']);
                        $creditHoursRaw = $getColumnValue($cells, $headerMap, $optionalColumns['credit_hours']);
                        $creditHours = $creditHoursRaw === '' ? 3 : (int)$creditHoursRaw;

                        if ($courseCode === '') {
                            $importErrors[] = "Course row {$rowNumber}: Course code is required.";
                            continue;
                        }
                        if (isset($seenCourseCodes[$courseCode])) {
                            $importErrors[] = "Course row {$rowNumber}: Duplicate course code '{$courseCode}' in uploaded file.";
                            continue;
                        }
                        if ($courseTitle === '') {
                            $importErrors[] = "Course row {$rowNumber}: Course title is required.";
                            continue;
                        }
                        if ($department === '') {
                            $importErrors[] = "Course row {$rowNumber}: Department is required.";
                            continue;
                        }
                        if ($adminDepartment !== '' && strcasecmp(trim($department), trim($adminDepartment)) !== 0) {
                            $importErrors[] = "Course row {$rowNumber}: Department '{$department}' is outside your scope ({$adminDepartment}).";
                            continue;
                        }
                        if ($semesterNo < 1 || $semesterNo > 12) {
                            $importErrors[] = "Course row {$rowNumber}: Semester must be between 1 and 12.";
                            continue;
                        }
                        if ($creditHours < 1 || $creditHours > 10) {
                            $importErrors[] = "Course row {$rowNumber}: Credit hours must be between 1 and 10.";
                            continue;
                        }

                        $seenCourseCodes[$courseCode] = true;

                        $upsertCourseStmt->bind_param("sssii", $courseCode, $courseTitle, $department, $semesterNo, $creditHours);
                        if (!$upsertCourseStmt->execute()) {
                            $importErrors[] = "Course row {$rowNumber}: Failed to upsert course ({$conn->error}).";
                            continue;
                        }
                        $upsertedCourses++;
                    }

                    $sheetHandled = true;
                    break;
                }

                $reader->close();
                $upsertCourseStmt->close();

                if (!$sheetHandled) {
                    $importErrors[] = 'Course file did not contain readable sheets.';
                }

                if (!empty($importErrors)) {
                    $conn->rollback();
                    $error = 'Course import failed. Found ' . count($importErrors) . ' issue(s).';
                } else {
                    $conn->commit();
                    $success = "Course import completed: {$processedRows} row(s) processed, {$upsertedCourses} course(s) synced.";
                }
            } catch (Throwable $exception) {
                if ($conn->errno) {
                    $conn->rollback();
                }
                $error = 'Course import failed: ' . $exception->getMessage();
            }
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_course'])) {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $offeringSession = trim($_POST['offering_session'] ?? '');
    $offeringSemester = (int)($_POST['offering_semester_no'] ?? 0);
    $offeringSection = strtoupper(trim($_POST['offering_section'] ?? ''));

    if ($teacherId <= 0 || $courseId <= 0 || $offeringSession === '' || $offeringSemester <= 0 || $offeringSection === '') {
        $error = 'Please select teacher, course, session, semester, and section.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } elseif (!preg_match('/^[A-Z0-9_-]{1,10}$/', $offeringSection)) {
        $error = 'Section is invalid. Use values like A or B.';
    } else {
        $scopeStmt = $conn->prepare("SELECT t.department AS teacher_department, c.department AS course_department, c.semester_no AS course_semester
                                     FROM teacher t
                                     INNER JOIN courses c ON c.course_id = ?
                                     WHERE t.teacher_id = ?
                                     LIMIT 1");
        $scopeStmt->bind_param("ii", $courseId, $teacherId);
        $scopeStmt->execute();
        $scopeResult = $scopeStmt->get_result();
        $scopeRow = $scopeResult ? $scopeResult->fetch_assoc() : null;
        $scopeStmt->close();

        $teacherDepartment = trim((string)($scopeRow['teacher_department'] ?? ''));
        $courseDepartment = trim((string)($scopeRow['course_department'] ?? ''));
        $courseSemester = (int)($scopeRow['course_semester'] ?? 0);

        if ($teacherDepartment === '' || $courseDepartment === '') {
            $error = 'Selected teacher or course was not found.';
        } elseif (strcasecmp($teacherDepartment, $adminDepartment) !== 0 || strcasecmp($courseDepartment, $adminDepartment) !== 0) {
            $error = 'Assignment blocked: teacher and course must belong to your department.';
        } elseif ($offeringSemester !== $courseSemester) {
            $error = "Selected semester does not match course semester ({$courseSemester}).";
        } else {
            $offeringStmt = $conn->prepare("INSERT INTO course_offerings (course_id, department, session, semester_no, section)
                                            VALUES (?, ?, ?, ?, ?)
                                            ON DUPLICATE KEY UPDATE offering_id = LAST_INSERT_ID(offering_id)");
            $offeringStmt->bind_param("issis", $courseId, $adminDepartment, $offeringSession, $offeringSemester, $offeringSection);

            if (!$offeringStmt->execute()) {
                $error = 'Failed to create or resolve class offering: ' . $conn->error;
            } else {
                $offeringId = (int)$conn->insert_id;

                $checkStmt = $conn->prepare("SELECT assignment_id FROM teacher_course_assignments WHERE offering_id = ? LIMIT 1");
                $checkStmt->bind_param("i", $offeringId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result();
                $alreadyExists = $existing && $existing->num_rows > 0;
                $checkStmt->close();

                if ($alreadyExists) {
                    $error = 'This class offering already has a teacher assigned.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO teacher_course_assignments (teacher_id, course_id, offering_id, assigned_by) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiii", $teacherId, $courseId, $offeringId, $adminId);
                    if ($stmt->execute()) {
                        $success = "Course assigned successfully for Session {$offeringSession}, Semester {$offeringSemester}, Section {$offeringSection}.";
                    } else {
                        $error = 'Failed to assign course: ' . $conn->error;
                    }
                    $stmt->close();
                }
            }
            if ($offeringStmt) {
                $offeringStmt->close();
            }
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_student'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $courseId = (int)($_POST['enroll_course_id'] ?? 0);
    $enrollDepartment = trim($_POST['enroll_department'] ?? '');
    $enrollSession = trim($_POST['enroll_session'] ?? '');
    $enrollSemester = (int)($_POST['enroll_semester_no'] ?? 0);
    $enrollSection = strtoupper(trim($_POST['enroll_section'] ?? ''));

    if ($studentId <= 0 || $courseId <= 0 || $enrollDepartment === '' || $enrollSession === '' || $enrollSemester <= 0 || $enrollSection === '') {
        $error = 'Please select department, course, session, semester, and section.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } elseif (strcasecmp($enrollDepartment, $adminDepartment) !== 0) {
        $error = 'Enrollment blocked: you can only enroll students from your own department.';
    } elseif (!preg_match('/^[A-Z0-9_-]{1,10}$/', $enrollSection)) {
        $error = 'Section is invalid. Use values like A or B.';
    } else {
        $studentScopeStmt = $conn->prepare("SELECT department, session, semester_no, section
                                            FROM student
                                            WHERE student_id = ?
                                            LIMIT 1");
        $studentScopeStmt->bind_param("i", $studentId);
        $studentScopeStmt->execute();
        $studentScopeResult = $studentScopeStmt->get_result();
        $studentScopeRow = $studentScopeResult ? $studentScopeResult->fetch_assoc() : null;
        $studentScopeStmt->close();

        if (!$studentScopeRow) {
            $error = 'Selected student was not found.';
        } elseif (
            strcasecmp(trim((string)$studentScopeRow['department']), $enrollDepartment) !== 0
            || strcasecmp(trim((string)$studentScopeRow['session']), $enrollSession) !== 0
            || (int)$studentScopeRow['semester_no'] !== $enrollSemester
            || strcasecmp(strtoupper(trim((string)$studentScopeRow['section'])), $enrollSection) !== 0
        ) {
            $error = 'Selected student does not match the chosen department/session/semester/section.';
        } else {
            $offeringStmt = $conn->prepare("SELECT co.offering_id, co.course_id
                                            FROM course_offerings co
                                            INNER JOIN teacher_course_assignments tca ON tca.offering_id = co.offering_id
                                            WHERE co.course_id = ?
                                              AND LOWER(TRIM(co.department)) = LOWER(TRIM(?))
                                              AND co.session = ?
                                              AND co.semester_no = ?
                                              AND UPPER(TRIM(co.section)) = ?
                                            LIMIT 1");
            $offeringStmt->bind_param("issis", $courseId, $enrollDepartment, $enrollSession, $enrollSemester, $enrollSection);
            $offeringStmt->execute();
            $offeringResult = $offeringStmt->get_result();
            $offeringRow = $offeringResult ? $offeringResult->fetch_assoc() : null;
            $offeringStmt->close();

            if (!$offeringRow) {
                $error = 'Enrollment blocked: no teacher is assigned for the selected class offering.';
            } else {
                $offeringId = (int)$offeringRow['offering_id'];
                $offeringCourseId = (int)$offeringRow['course_id'];

                $checkStmt = $conn->prepare("SELECT enrollment_id FROM student_course_enrollments WHERE student_id = ? AND offering_id = ?");
                $checkStmt->bind_param("ii", $studentId, $offeringId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result();
                $alreadyExists = $existing && $existing->num_rows > 0;
                $checkStmt->close();

                if ($alreadyExists) {
                    $error = 'This student is already enrolled in the selected class offering.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO student_course_enrollments (student_id, course_id, offering_id, enrolled_by) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiii", $studentId, $offeringCourseId, $offeringId, $adminId);
                    if ($stmt->execute()) {
                        $success = 'Student enrolled successfully.';
                    } else {
                        $error = 'Enrollment failed: ' . $conn->error;
                    }
                    $stmt->close();
                }
            }
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_student_group'])) {
    $groupDepartment = trim($_POST['group_department'] ?? '');
    $groupSession = trim($_POST['group_session'] ?? '');
    $groupSemester = (int)($_POST['group_semester_no'] ?? 0);
    $groupSection = strtoupper(trim($_POST['group_section'] ?? ''));
    $courseId = (int)($_POST['group_enroll_course_id'] ?? 0);

    if ($groupDepartment === '' || $groupSession === '' || $groupSemester <= 0 || $groupSection === '' || $courseId <= 0) {
        $error = 'Please select department, session, semester, section, and course for group enrollment.';
    } elseif ($adminDepartment === '') {
        $error = 'Department scope is not configured for this super admin.';
    } elseif (strcasecmp(trim($groupDepartment), trim($adminDepartment)) !== 0) {
        $error = 'Group enrollment blocked: you can only enroll students from your own department.';
    } elseif (!preg_match('/^[A-Z0-9_-]{1,10}$/', $groupSection)) {
        $error = 'Section is invalid. Use values like A or B.';
    } else {
        $offeringStmt = $conn->prepare("SELECT co.offering_id, co.course_id
                                        FROM course_offerings co
                                        INNER JOIN teacher_course_assignments tca ON tca.offering_id = co.offering_id
                                        WHERE co.course_id = ?
                                          AND LOWER(TRIM(co.department)) = LOWER(TRIM(?))
                                          AND co.session = ?
                                          AND co.semester_no = ?
                                          AND UPPER(TRIM(co.section)) = ?
                                        LIMIT 1");
        $offeringStmt->bind_param("issis", $courseId, $groupDepartment, $groupSession, $groupSemester, $groupSection);
        $offeringStmt->execute();
        $offeringResult = $offeringStmt->get_result();
        $offeringRow = $offeringResult ? $offeringResult->fetch_assoc() : null;
        $offeringStmt->close();

        if (!$offeringRow) {
            $error = 'Group enrollment blocked: selected class offering has no teacher assignment.';
        } else {
            $offeringId = (int)$offeringRow['offering_id'];
            $offeringCourseId = (int)$offeringRow['course_id'];

            $insertGroupStmt = $conn->prepare("INSERT INTO student_course_enrollments (student_id, course_id, offering_id, enrolled_by)
                                               SELECT s.student_id, ?, ?, ?
                                               FROM student s
                                               LEFT JOIN student_course_enrollments sce
                                                 ON sce.student_id = s.student_id AND sce.offering_id = ?
                                               WHERE s.department = ?
                                                 AND s.session = ?
                                                 AND s.semester_no = ?
                                                 AND UPPER(TRIM(s.section)) = ?
                                                 AND sce.student_id IS NULL");
            $insertGroupStmt->bind_param("iiiissis", $offeringCourseId, $offeringId, $adminId, $offeringId, $groupDepartment, $groupSession, $groupSemester, $groupSection);

            if ($insertGroupStmt->execute()) {
                $addedCount = $insertGroupStmt->affected_rows;
                if ($addedCount > 0) {
                    $success = "Group enrollment completed: {$addedCount} student(s) enrolled.";
                } else {
                    $error = 'No new students were enrolled (they may already be enrolled or no students matched this group).';
                }
            } else {
                $error = 'Group enrollment failed: ' . $conn->error;
            }
            $insertGroupStmt->close();
        }
    }

    finalizeImportRequest($success, $error, $importErrors);
}

$teachers = [];
$teacherStmt = $conn->prepare("SELECT teacher_id, name, department FROM teacher WHERE LOWER(TRIM(department)) = LOWER(TRIM(?)) ORDER BY name ASC");
$teacherStmt->bind_param("s", $adminDepartment);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
if ($teacherResult) {
    while ($row = $teacherResult->fetch_assoc()) {
        $teachers[] = $row;
    }
}
$teacherStmt->close();

$students = [];
$studentStmt = $conn->prepare("SELECT student_id, name, Roll_no, department, session, semester_no, section FROM student WHERE LOWER(TRIM(department)) = LOWER(TRIM(?)) ORDER BY department ASC, session ASC, semester_no ASC, section ASC, name ASC");
$studentStmt->bind_param("s", $adminDepartment);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
if ($studentResult) {
    while ($row = $studentResult->fetch_assoc()) {
        $students[] = $row;
    }
}
$studentStmt->close();

$studentsByGroup = [];
$groupDepartments = [];
$groupSessions = [];
$groupSemesters = [];
$groupSections = [];

foreach ($students as $student) {
    $department = $student['department'] ?? 'Unknown';
    $sessionValue = $student['session'] ?? 'Unknown';
    $semesterValue = (int)($student['semester_no'] ?? 0);
    $sectionValue = strtoupper(trim((string)($student['section'] ?? 'A')));
    if ($sectionValue === '') {
        $sectionValue = 'A';
    }

    $groupKey = $department . ' | ' . $sessionValue . ' | Sem ' . $semesterValue . ' | Section ' . $sectionValue;
    if (!isset($studentsByGroup[$groupKey])) {
        $studentsByGroup[$groupKey] = [];
    }
    $studentsByGroup[$groupKey][] = $student;

    $groupDepartments[$department] = true;
    $groupSessions[$sessionValue] = true;
    if ($semesterValue > 0) {
        $groupSemesters[$semesterValue] = true;
    }
    $groupSections[$sectionValue] = true;
}

ksort($studentsByGroup);
$groupDepartments = array_keys($groupDepartments);
$groupSessions = array_keys($groupSessions);
$groupSemesters = array_keys($groupSemesters);
$groupSections = array_keys($groupSections);
sort($groupDepartments);
sort($groupSessions);
sort($groupSemesters, SORT_NUMERIC);
sort($groupSections);

$courses = [];
$courseStmt = $conn->prepare("SELECT course_id, course_code, course_title, department, semester_no FROM courses WHERE LOWER(TRIM(department)) = LOWER(TRIM(?)) ORDER BY course_code ASC");
$courseStmt->bind_param("s", $adminDepartment);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courses[] = $row;
    }
}
$courseStmt->close();

$enrollableCourses = [];
$enrollableCourseStmt = $conn->prepare("SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.department, c.semester_no
                                                                             FROM courses c
                                                                             INNER JOIN teacher_course_assignments tca ON tca.course_id = c.course_id
                                                                             INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                                                                             WHERE LOWER(TRIM(c.department)) = LOWER(TRIM(?))
                                                                                 AND LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                                                                             ORDER BY c.course_code ASC");
$enrollableCourseStmt->bind_param("ss", $adminDepartment, $adminDepartment);
$enrollableCourseStmt->execute();
$enrollableCourseResult = $enrollableCourseStmt->get_result();
if ($enrollableCourseResult) {
        while ($row = $enrollableCourseResult->fetch_assoc()) {
                $enrollableCourses[] = $row;
        }
}
$enrollableCourseStmt->close();

$recentAssignments = [];
$assignmentStmt = $conn->prepare("SELECT MAX(tca.assigned_at) AS assigned_at,
                                         co.session,
                                         co.semester_no,
                                         co.section,
                                         COUNT(*) AS assigned_count,
                          GROUP_CONCAT(DISTINCT CONCAT(c.course_code, ' - ', c.course_title) ORDER BY c.course_code SEPARATOR ' | ') AS course_list,
                                         GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS teacher_names,
                                         u.email AS assigned_by_email
                                  FROM teacher_course_assignments tca
                                  INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
                                  INNER JOIN courses c ON c.course_id = tca.course_id
                                  INNER JOIN course_offerings co ON co.offering_id = tca.offering_id
                                  INNER JOIN user u ON u.user_id = tca.assigned_by
                                  WHERE LOWER(TRIM(t.department)) = LOWER(TRIM(?))
                      GROUP BY co.session, co.semester_no, co.section, u.email
                                  ORDER BY assigned_at DESC
                                  LIMIT 10");
$assignmentStmt->bind_param("s", $adminDepartment);
$assignmentStmt->execute();
$assignmentResult = $assignmentStmt->get_result();
if ($assignmentResult) {
    while ($row = $assignmentResult->fetch_assoc()) {
        $recentAssignments[] = $row;
    }
}
$assignmentStmt->close();

$recentEnrollments = [];
$enrollmentStmt = $conn->prepare("SELECT MAX(sce.enrolled_at) AS enrolled_at,
                                         c.course_code,
                                         c.course_title,
                                         co.session,
                                         co.semester_no,
                                         co.section,
                                         COUNT(*) AS enrolled_count,
                                         u.email AS enrolled_by_email
                                  FROM student_course_enrollments sce
                                  INNER JOIN student s ON s.student_id = sce.student_id
                                  INNER JOIN courses c ON c.course_id = sce.course_id
                                  INNER JOIN course_offerings co ON co.offering_id = sce.offering_id
                                  INNER JOIN user u ON u.user_id = sce.enrolled_by
                                  WHERE LOWER(TRIM(s.department)) = LOWER(TRIM(?))
                                  GROUP BY sce.offering_id, c.course_code, c.course_title, co.session, co.semester_no, co.section, u.email
                                  ORDER BY enrolled_at DESC
                                  LIMIT 10");
$enrollmentStmt->bind_param("s", $adminDepartment);
$enrollmentStmt->execute();
$enrollmentResult = $enrollmentStmt->get_result();
if ($enrollmentResult) {
    while ($row = $enrollmentResult->fetch_assoc()) {
        $recentEnrollments[] = $row;
    }
}
$enrollmentStmt->close();

$counts = [
    'teachers' => 0,
    'students' => 0,
    'courses' => 0,
    'assignments' => 0,
    'enrollments' => 0
];

$countStmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM teacher WHERE LOWER(TRIM(department)) = LOWER(TRIM(?))) AS teachers,
    (SELECT COUNT(*) FROM student WHERE LOWER(TRIM(department)) = LOWER(TRIM(?))) AS students,
    (SELECT COUNT(*) FROM courses WHERE LOWER(TRIM(department)) = LOWER(TRIM(?))) AS courses,
    (SELECT COUNT(*)
        FROM teacher_course_assignments tca
        INNER JOIN teacher t ON t.teacher_id = tca.teacher_id
        WHERE LOWER(TRIM(t.department)) = LOWER(TRIM(?))) AS assignments,
    (SELECT COUNT(*)
        FROM student_course_enrollments sce
        INNER JOIN student s ON s.student_id = sce.student_id
        WHERE sce.status = 'active' AND LOWER(TRIM(s.department)) = LOWER(TRIM(?))) AS enrollments");
$countStmt->bind_param("sssss", $adminDepartment, $adminDepartment, $adminDepartment, $adminDepartment, $adminDepartment);
$countStmt->execute();
$countResult = $countStmt->get_result();
if ($countResult) {
    $counts = $countResult->fetch_assoc();
}
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard – E-Notice</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sora: ['Sora','sans-serif'], sans: ['Source Sans 3','sans-serif'] },
                colors: {
                    navy: '#0F172A',
                    'error': '#ba1a1a', 'error-container': '#ffdad6', 'on-error-container': '#93000a',
                    'on-primary-container': '#7c839b', 'on-surface-variant': '#45464d'
                }
            }}
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { background-color: #F8FAFC; font-family: 'Source Sans 3', sans-serif; }
    </style>
</head>
<body class="text-slate-800">

<!-- ── Sidebar ── -->
<aside class="fixed left-0 top-0 w-[280px] h-full bg-[#0F172A] border-r border-slate-800 flex flex-col z-50 shadow-xl">
    <div class="p-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-400/40 shrink-0">
            <img src="../assets/images/must_logo.png" alt="MUST Logo" class="w-full h-full object-cover">
        </div>
        <div>
            <h1 class="text-white text-xl font-bold font-sora leading-none">E-Notice</h1>
            <p class="text-slate-400 text-xs uppercase tracking-widest mt-0.5">Academic Admin</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-2 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600/10 text-blue-400 border-l-4 border-blue-500 font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a href="re_enroll.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">manage_search</span>Re-enroll Search
        </a>
        <a href="audit_logs.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">receipt_long</span>Audit Logs
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold">
            <span class="material-symbols-outlined">settings</span>Settings
        </a>
    </nav>
    <div class="px-4 py-4 border-t border-slate-800">
        <button id="logout-btn" class="w-full flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white transition-all font-sora text-sm font-semibold text-left">
            <span class="material-symbols-outlined">logout</span>Log out
        </button>
    </div>
</aside>

<!-- ── Top Bar ── -->
<header class="fixed top-0 right-0 left-[280px] h-16 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between px-8 z-40 shadow-sm">
    <div class="flex items-center gap-3">
        <h2 class="text-slate-900 font-black text-lg font-sora">Assignment Dashboard</h2>
        <span class="flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full border border-blue-100 text-[11px] font-bold uppercase tracking-wider">
            <span class="w-2 h-2 bg-blue-500 rounded-full"></span>Super Admin
        </span>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-right">
            <p class="font-bold text-slate-900 text-sm font-sora leading-none"><?php echo htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin'); ?></p>
            <p class="text-[10px] text-blue-600 font-bold uppercase"><?php echo htmlspecialchars($adminDepartment ?: 'Department N/A'); ?></p>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
            <?php echo strtoupper(substr($_SESSION['super_admin_name'] ?? 'A', 0, 1)); ?>
        </div>
    </div>
</header>

<!-- ── Main Content ── -->
<main class="ml-[280px] mt-16 p-6 min-h-screen">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-slate-500 text-sm">
            <span>Admin</span>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="font-semibold text-slate-900">Academic Dashboard</span>
        </nav>

        <!-- Flash Messages -->
        <?php if ($error): ?>
        <div class="p-4 bg-error-container border-l-4 border-error flex gap-3 items-start rounded-r-lg">
            <span class="material-symbols-outlined text-error" style="font-variation-settings:'FILL' 1">error</span>
            <div>
                <p class="font-bold text-on-error-container text-sm font-sora">Error</p>
                <p class="text-on-error-container text-sm"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 flex gap-3 items-start rounded-r-lg">
            <span class="material-symbols-outlined text-emerald-600" style="font-variation-settings:'FILL' 1">check_circle</span>
            <div>
                <p class="font-bold text-emerald-800 text-sm font-sora">Success</p>
                <p class="text-emerald-800 text-sm"><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($importErrors)): ?>
        <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg">
            <p class="font-bold text-amber-800 text-sm font-sora mb-2">Import Validation Issues</p>
            <ul class="list-disc list-inside space-y-1">
                <?php foreach (array_slice($importErrors, 0, 12) as $ie): ?>
                    <li class="text-amber-800 text-sm"><?php echo htmlspecialchars($ie); ?></li>
                <?php endforeach; ?>
                <?php if (count($importErrors) > 12): ?>
                    <li class="text-amber-700 text-sm">...and <?php echo count($importErrors) - 12; ?> more issue(s).</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-blue-600">
                <div class="flex justify-between items-start mb-3">
                    <span class="material-symbols-outlined text-slate-400 bg-slate-50 p-2 rounded-lg">person_pin</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Teachers</p>
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['teachers']; ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-emerald-500">
                <div class="flex justify-between items-start mb-3">
                    <span class="material-symbols-outlined text-slate-400 bg-slate-50 p-2 rounded-lg">groups</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Students</p>
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['students']; ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-indigo-500">
                <div class="flex justify-between items-start mb-3">
                    <span class="material-symbols-outlined text-slate-400 bg-slate-50 p-2 rounded-lg">book_5</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Courses</p>
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['courses']; ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-amber-500">
                <div class="flex justify-between items-start mb-3">
                    <span class="material-symbols-outlined text-amber-500 bg-amber-50 p-2 rounded-lg">assignment_turned_in</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Assignments</p>
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['assignments']; ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm border-t-4 border-t-slate-600">
                <div class="flex justify-between items-start mb-3">
                    <span class="material-symbols-outlined text-slate-400 bg-slate-50 p-2 rounded-lg">fact_check</span>
                </div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Enrollments</p>
                <p class="text-2xl font-bold text-slate-900 font-sora"><?php echo (int)$counts['enrollments']; ?></p>
            </div>
        </div>

        <!-- Quick Nav -->
        <div class="flex flex-wrap gap-3">
            <a href="#students-section" class="flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-bold hover:bg-emerald-100 transition-all">
                <span class="material-symbols-outlined text-sm">school</span>Students
            </a>
            <a href="#teachers-section" class="flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-sm font-bold hover:bg-blue-100 transition-all">
                <span class="material-symbols-outlined text-sm">person_pin</span>Teachers
            </a>
            <a href="#courses-section" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-bold hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-sm">book_5</span>Courses
            </a>
        </div>

        <!-- ── Students Section ── -->
        <section id="students-section" class="space-y-4">
            <div class="flex items-center gap-3 pb-2 border-b border-slate-200">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-emerald-600 text-lg">school</span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-sora">Students</h3>
            </div>

            <!-- Top row: Re-enroll + Import -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Individual Re-enroll -->
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-blue-600 bg-blue-50 p-2 rounded-lg">manage_search</span>
                        <h4 class="font-sora font-semibold text-slate-900">Individual Re-enroll</h4>
                    </div>
                    <p class="text-slate-500 text-sm mb-4">For failed/repeat cases, search by <strong class="text-slate-700">Roll No / Name / Email</strong> and re-enroll from a dedicated page.</p>
                    <a href="re_enroll.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">search</span>Open Re-enroll Search
                    </a>
                </div>

                <!-- Import Students -->
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-emerald-600 bg-emerald-50 p-2 rounded-lg" style="font-variation-settings:'FILL' 1">upload_file</span>
                        <h4 class="font-sora font-semibold text-slate-900">Import Students from Excel/CSV</h4>
                    </div>
                    <p class="text-slate-500 text-xs mb-4">Required headers: <span class="font-mono bg-slate-100 px-1 rounded text-slate-700">email, name, password, roll_no, session, department, semester_no</span> + optional <span class="font-mono bg-slate-100 px-1 rounded text-slate-700">section</span></p>
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-3">
                        <div>
                            <label for="student_excel" class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-wide">Excel / CSV File (.xlsx, .csv, .ods)</label>
                            <input type="file" id="student_excel" name="student_excel" accept=".xlsx,.csv,.ods" required
                                class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold hover:file:bg-emerald-100 border border-slate-200 rounded-lg p-1">
                        </div>
                        <button type="submit" name="import_students" class="w-full flex items-center justify-center gap-2 bg-emerald-600 text-white py-2.5 rounded-lg font-bold text-sm hover:bg-emerald-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-sm">cloud_upload</span>Import Students
                        </button>
                    </form>
                </div>
            </div>

            <!-- Group Enroll -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-5">
                    <span class="material-symbols-outlined text-amber-600 bg-amber-50 p-2 rounded-lg">group_add</span>
                    <h4 class="font-sora font-semibold text-slate-900">Group Enroll Students (Batch)</h4>
                </div>
                <form method="POST" action="">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
                        <div>
                            <label for="group_department" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Department</label>
                            <select id="group_department" name="group_department" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                                <option value="">Select</option>
                                <?php foreach ($groupDepartments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_session" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Session</label>
                            <select id="group_session" name="group_session" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                                <option value="">Select</option>
                                <?php foreach ($groupSessions as $sessionValue): ?>
                                    <option value="<?php echo htmlspecialchars($sessionValue); ?>"><?php echo htmlspecialchars($sessionValue); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_semester_no" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Semester</label>
                            <select id="group_semester_no" name="group_semester_no" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                                <option value="">Select</option>
                                <?php foreach ($groupSemesters as $semesterValue): ?>
                                    <option value="<?php echo (int)$semesterValue; ?>"><?php echo (int)$semesterValue; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_section" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Section</label>
                            <select id="group_section" name="group_section" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                                <option value="">Select</option>
                                <?php foreach ($groupSections as $sectionValue): ?>
                                    <option value="<?php echo htmlspecialchars($sectionValue); ?>"><?php echo htmlspecialchars($sectionValue); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_enroll_course_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Course</label>
                            <select id="group_enroll_course_id" name="group_enroll_course_id" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 outline-none bg-slate-50">
                                <option value="">Select</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code']); ?> (Sem <?php echo (int)$course['semester_no']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($courses)): ?>
                                <p class="text-xs text-slate-400 mt-1">No courses available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="enroll_student_group"
                        class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-lg font-bold text-sm hover:bg-amber-600 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">group_add</span>Enroll Group
                    </button>
                </form>
            </div>

            <!-- Recent Student Enrollments -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                    <span class="material-symbols-outlined text-slate-500">history_edu</span>
                    <h4 class="font-sora font-semibold text-slate-900">Recent Student Enrollments</h4>
                </div>
                <?php if (empty($recentEnrollments)): ?>
                    <div class="px-6 py-8 text-center text-slate-400 text-sm">No enrollments yet.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 border-b border-slate-200">Course</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Session</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Sem</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Section</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Enrolled</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($recentEnrollments as $item): ?>
                                <tr class="hover:bg-slate-50/80 transition-all">
                                    <td class="px-6 py-3 font-semibold text-slate-900">
                                        <?php echo htmlspecialchars($item['course_code']); ?>
                                        <span class="block text-xs text-slate-400 font-normal"><?php echo htmlspecialchars($item['course_title']); ?></span>
                                    </td>
                                    <td class="px-6 py-3 text-slate-600"><?php echo htmlspecialchars($item['session']); ?></td>
                                    <td class="px-6 py-3 text-slate-600"><?php echo (int)$item['semester_no']; ?></td>
                                    <td class="px-6 py-3 text-slate-600"><?php echo htmlspecialchars($item['section']); ?></td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full">
                                            <?php echo (int)$item['enrolled_count']; ?> student(s)
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-slate-400 text-xs"><?php echo date('M d, Y h:i A', strtotime($item['enrolled_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>


        <!-- ── Teachers Section ── -->
        <section id="teachers-section" class="space-y-4">
            <div class="flex items-center gap-3 pb-2 border-b border-slate-200">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-lg">person_pin</span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-sora">Teachers</h3>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Import Teachers -->
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-blue-600 bg-blue-50 p-2 rounded-lg" style="font-variation-settings:'FILL' 1">upload_file</span>
                        <h4 class="font-sora font-semibold text-slate-900">Import Teachers from Excel/CSV</h4>
                    </div>
                    <p class="text-slate-500 text-xs mb-4">Required headers: <span class="font-mono bg-slate-100 px-1 rounded text-slate-700">email, name, password, department</span></p>
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-3">
                        <div>
                            <label for="teacher_excel" class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-wide">Excel / CSV File (.xlsx, .csv, .ods)</label>
                            <input type="file" id="teacher_excel" name="teacher_excel" accept=".xlsx,.csv,.ods" required
                                class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-bold hover:file:bg-blue-100 border border-slate-200 rounded-lg p-1">
                        </div>
                        <button type="submit" name="import_teachers"
                            class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white py-2.5 rounded-lg font-bold text-sm hover:bg-blue-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-sm">cloud_upload</span>Import Teachers
                        </button>
                    </form>
                </div>

                <!-- Teachers in System -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                        <span class="material-symbols-outlined text-slate-500">badge</span>
                        <h4 class="font-sora font-semibold text-slate-900">Teachers in System</h4>
                        <span class="ml-auto text-xs font-bold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full"><?php echo count($teachers); ?> total</span>
                    </div>
                    <?php if (empty($teachers)): ?>
                        <div class="px-6 py-8 text-center text-slate-400 text-sm">No teachers found.</div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach (array_slice($teachers, 0, 15) as $teacher): ?>
                            <li class="flex items-center justify-between px-6 py-3 hover:bg-slate-50/80 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm">
                                        <?php echo strtoupper(substr($teacher['name'], 0, 1)); ?>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($teacher['name']); ?></span>
                                </div>
                                <span class="text-xs text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full"><?php echo htmlspecialchars($teacher['department']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($teachers) > 15): ?>
                        <div class="px-6 py-3 border-t border-slate-100 text-xs text-slate-400 text-center">
                            Showing 15 of <?php echo count($teachers); ?> teachers
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </section>



        <!-- ── Courses Section ── -->
        <section id="courses-section" class="space-y-4">
            <div class="flex items-center gap-3 pb-2 border-b border-slate-200">
                <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-indigo-600 text-lg">book_5</span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-sora">Courses</h3>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Assign Course to Teacher -->
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="material-symbols-outlined text-indigo-600 bg-indigo-50 p-2 rounded-lg" style="font-variation-settings:'FILL' 1">assignment_add</span>
                        <h4 class="font-sora font-semibold text-slate-900">Assign Course to Teacher</h4>
                    </div>
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label for="teacher_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Teacher</label>
                            <select id="teacher_id" name="teacher_id" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 outline-none bg-slate-50">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo (int)$teacher['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?> (<?php echo htmlspecialchars($teacher['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="course_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Course</label>
                            <select id="course_id" name="course_id" required
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 outline-none bg-slate-50">
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code']); ?> – <?php echo htmlspecialchars($course['course_title']); ?>
                                        (Sem <?php echo (int)$course['semester_no']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label for="offering_session" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Session</label>
                                <input type="text" id="offering_session" name="offering_session" placeholder="e.g. 2022-2026" required
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 outline-none bg-slate-50">
                            </div>
                            <div>
                                <label for="offering_semester_no" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Semester</label>
                                <select id="offering_semester_no" name="offering_semester_no" required
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 outline-none bg-slate-50">
                                    <option value="">Select</option>
                                    <?php for ($semesterOption = 1; $semesterOption <= 12; $semesterOption++): ?>
                                        <option value="<?php echo $semesterOption; ?>"><?php echo $semesterOption; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label for="offering_section" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Section</label>
                                <input type="text" id="offering_section" name="offering_section" placeholder="A / B" maxlength="10" required
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/20 outline-none bg-slate-50">
                            </div>
                        </div>
                        <button type="submit" name="assign_course"
                            class="w-full flex items-center justify-center gap-2 bg-indigo-600 text-white py-2.5 rounded-lg font-bold text-sm hover:bg-indigo-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-sm">bolt</span>Execute Assignment
                        </button>
                    </form>
                </div>

                <!-- Import Courses -->
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-indigo-600 bg-indigo-50 p-2 rounded-lg" style="font-variation-settings:'FILL' 1">upload_file</span>
                        <h4 class="font-sora font-semibold text-slate-900">Import Courses from Excel/CSV</h4>
                    </div>
                    <p class="text-slate-500 text-xs mb-4">Required headers: <span class="font-mono bg-slate-100 px-1 rounded text-slate-700">course_code, course_title, department, semester_no</span> + optional <span class="font-mono bg-slate-100 px-1 rounded text-slate-700">credit_hours</span></p>
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-3">
                        <div>
                            <label for="course_excel" class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-wide">Excel / CSV File (.xlsx, .csv, .ods)</label>
                            <input type="file" id="course_excel" name="course_excel" accept=".xlsx,.csv,.ods" required
                                class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-bold hover:file:bg-indigo-100 border border-slate-200 rounded-lg p-1">
                        </div>
                        <button type="submit" name="import_courses"
                            class="w-full flex items-center justify-center gap-2 bg-indigo-600 text-white py-2.5 rounded-lg font-bold text-sm hover:bg-indigo-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-sm">cloud_upload</span>Import Courses
                        </button>
                    </form>
                </div>

            </div>

            <!-- Recent Teacher Assignments -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                    <span class="material-symbols-outlined text-slate-500">history</span>
                    <h4 class="font-sora font-semibold text-slate-900">Recent Teacher Assignments</h4>
                </div>
                <?php if (empty($recentAssignments)): ?>
                    <div class="px-6 py-8 text-center text-slate-400 text-sm">No assignments yet.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 border-b border-slate-200">Session / Sem / Section</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Courses</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Teacher(s)</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Count</th>
                                    <th class="px-6 py-3 border-b border-slate-200">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($recentAssignments as $item): ?>
                                <tr class="hover:bg-slate-50/80 transition-all">
                                    <td class="px-6 py-3 font-semibold text-slate-900">
                                        <?php echo htmlspecialchars($item['session']); ?>
                                        <span class="block text-xs text-slate-400 font-normal">Sem <?php echo (int)$item['semester_no']; ?> · §<?php echo htmlspecialchars($item['section']); ?></span>
                                    </td>
                                    <td class="px-6 py-3 text-slate-600 text-xs max-w-xs truncate"><?php echo htmlspecialchars($item['course_list'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-3 text-slate-600 text-xs"><?php echo htmlspecialchars($item['teacher_names'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-full">
                                            <?php echo (int)$item['assigned_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-slate-400 text-xs"><?php echo date('M d, Y h:i A', strtotime($item['assigned_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Footer -->
        <footer class="pt-8 pb-4 border-t border-slate-200 flex justify-between items-center text-xs text-slate-400">
            <p>&copy; <?php echo date('Y'); ?> E-Notice Institutional Portal. All rights reserved.</p>
            <div class="flex gap-6 font-bold">
                <a href="#" class="hover:text-blue-600 transition-colors">System Status</a>
                <a href="#" class="hover:text-blue-600 transition-colors">Support</a>
            </div>
        </footer>

    </div><!-- /max-w-7xl -->
</main>

<script>
    document.getElementById('logout-btn').addEventListener('click', () => {
        window.location.href = '../logout.php';
    });
</script>
</body>
</html>
