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
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { padding-top: 56px; }

        @media (min-width: 992px) {
            body { padding-top: 70px; }
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
    <nav class="navbar navbar-dark bg-primary fixed-top d-none d-lg-flex" style="left: 250px; width: calc(100% - 250px);">
        <div class="container-fluid justify-content-center">
            <div class="d-flex text-white gap-3 flex-wrap justify-content-center">
                <span><strong>Super Admin:</strong> <?php echo htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin'); ?></span>
                <span>|</span>
                <span><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['super_admin_email'] ?? ''); ?></span>
                <span>|</span>
                <span><strong>Department:</strong> <?php echo htmlspecialchars($adminDepartment ?: 'Not Set'); ?></span>
            </div>
        </div>
    </nav>

    <nav class="navbar navbar-dark bg-primary fixed-top d-lg-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0">Super Admin</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebar" style="width: 250px; height: 100vh;">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    <h4 class="mb-4"><a href="dashboard.php" class="text-white text-decoration-none">Super Admin Panel</a></h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white active bg-secondary rounded mb-2" href="dashboard.php"><i class="fas fa-gauge me-2"></i>Dashboard</a>
                        <a class="nav-link text-white mb-2" href="re_enroll.php"><i class="fas fa-search me-2"></i>Re-enroll Search</a>
                        <a class="nav-link text-white mb-2" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                        <button class="nav-link btn btn-link text-white text-start mb-2" id="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Log out</button>
                    </nav>
                </div>
            </div>

            <main class="col-lg-9 col-xl-10 ms-lg-auto px-md-4">
                <div class="container py-4">
                    <h2 class="mb-4">Academic Assignment Dashboard</h2>

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

                    <?php if (!empty($importErrors)): ?>
                        <div class="alert alert-warning">
                            <h6 class="mb-2">Import Validation Details</h6>
                            <ul class="mb-0">
                                <?php foreach (array_slice($importErrors, 0, 12) as $importError): ?>
                                    <li><?php echo htmlspecialchars($importError); ?></li>
                                <?php endforeach; ?>
                                <?php if (count($importErrors) > 12): ?>
                                    <li>...and <?php echo count($importErrors) - 12; ?> more issue(s).</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-primary"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['teachers']; ?></h5><small>Teachers</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-success"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['students']; ?></h5><small>Students</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-2 mb-3">
                            <div class="card text-bg-info"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['courses']; ?></h5><small>Courses</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card text-bg-warning"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['assignments']; ?></h5><small>Teacher Assignments</small></div></div>
                        </div>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card text-bg-secondary"><div class="card-body text-center"><h5 class="mb-0"><?php echo (int)$counts['enrollments']; ?></h5><small>Active Enrollments</small></div></div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body d-flex flex-wrap gap-2">
                            <a href="#students-section" class="btn btn-outline-success btn-sm">Students Section</a>
                            <a href="#teachers-section" class="btn btn-outline-primary btn-sm">Teachers Section</a>
                            <a href="#courses-section" class="btn btn-outline-info btn-sm">Courses Section</a>
                        </div>
                    </div>

                    <section id="students-section" class="mb-5">
                        <h4 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Students</h4>
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Individual Re-enroll (Search)</h5></div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">For failed/repeat cases, search by <strong>Roll No / Name / Email</strong> and re-enroll from a dedicated page.</p>
                                        <a href="re_enroll.php" class="btn btn-outline-primary">
                                            <i class="fas fa-search me-1"></i>Open Re-enroll Search
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Import Students from Excel/CSV</h5></div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">Headers: <strong>email, name, password, roll_no, session, department, semester_no</strong> (+ optional <strong>section</strong>)</p>
                                        <form method="POST" action="" enctype="multipart/form-data">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-12">
                                                    <label for="student_excel" class="form-label">Excel/CSV File (.xlsx, .csv, .ods)</label>
                                                    <input type="file" class="form-control" id="student_excel" name="student_excel" accept=".xlsx,.csv,.ods" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" name="import_students" class="btn btn-primary w-100">
                                                        <i class="fas fa-file-import me-1"></i>Import Students
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Group Enroll Students (Batch)</h5></div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label for="group_department" class="form-label">Department</label>
                                                    <select class="form-select" id="group_department" name="group_department" required>
                                                        <option value="">Select Department</option>
                                                        <?php foreach ($groupDepartments as $department): ?>
                                                            <option value="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="group_session" class="form-label">Session</label>
                                                    <select class="form-select" id="group_session" name="group_session" required>
                                                        <option value="">Select Session</option>
                                                        <?php foreach ($groupSessions as $sessionValue): ?>
                                                            <option value="<?php echo htmlspecialchars($sessionValue); ?>"><?php echo htmlspecialchars($sessionValue); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="group_semester_no" class="form-label">Semester</label>
                                                    <select class="form-select" id="group_semester_no" name="group_semester_no" required>
                                                        <option value="">Select</option>
                                                        <?php foreach ($groupSemesters as $semesterValue): ?>
                                                            <option value="<?php echo (int)$semesterValue; ?>"><?php echo (int)$semesterValue; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="group_section" class="form-label">Section</label>
                                                    <select class="form-select" id="group_section" name="group_section" required>
                                                        <option value="">Select</option>
                                                        <?php foreach ($groupSections as $sectionValue): ?>
                                                            <option value="<?php echo htmlspecialchars($sectionValue); ?>"><?php echo htmlspecialchars($sectionValue); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="group_enroll_course_id" class="form-label">Course</label>
                                                    <select class="form-select" id="group_enroll_course_id" name="group_enroll_course_id" required>
                                                        <option value="">Select Course</option>
                                                        <?php foreach ($courses as $course): ?>
                                                            <option value="<?php echo (int)$course['course_id']; ?>">
                                                                <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?>
                                                                (Sem <?php echo (int)$course['semester_no']; ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php if (empty($courses)): ?>
                                                        <small class="text-muted d-block mt-1">No courses available.</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button type="submit" name="enroll_student_group" class="btn btn-success">
                                                    <i class="fas fa-users me-1"></i>Enroll Group
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Recent Student Enrollments</h5></div>
                                    <div class="card-body">
                                        <?php if (empty($recentEnrollments)): ?>
                                            <p class="text-muted mb-0">No enrollments yet.</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($recentEnrollments as $item): ?>
                                                    <li class="list-group-item px-0">
                                                        <strong><?php echo htmlspecialchars($item['course_code']); ?></strong>
                                                        - <?php echo htmlspecialchars($item['course_title']); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            Session <?php echo htmlspecialchars($item['session']); ?> | Semester <?php echo (int)$item['semester_no']; ?> | Section <?php echo htmlspecialchars($item['section']); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            Enrolled: <?php echo (int)$item['enrolled_count']; ?> student(s)
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y h:i A', strtotime($item['enrolled_at'])); ?>
                                                        </small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="teachers-section" class="mb-5">
                        <h4 class="mb-3"><i class="fas fa-chalkboard-teacher me-2"></i>Teachers</h4>
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Import Teachers from Excel/CSV</h5></div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">Headers: <strong>email, name, password, department</strong></p>
                                        <form method="POST" action="" enctype="multipart/form-data">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-12">
                                                    <label for="teacher_excel" class="form-label">Excel/CSV File (.xlsx, .csv, .ods)</label>
                                                    <input type="file" class="form-control" id="teacher_excel" name="teacher_excel" accept=".xlsx,.csv,.ods" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" name="import_teachers" class="btn btn-primary w-100">
                                                        <i class="fas fa-file-import me-1"></i>Import Teachers
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header"><h5 class="mb-0">Teachers in System</h5></div>
                                    <div class="card-body">
                                        <?php if (empty($teachers)): ?>
                                            <p class="text-muted mb-0">No teachers found.</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach (array_slice($teachers, 0, 15) as $teacher): ?>
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span><?php echo htmlspecialchars($teacher['name']); ?></span>
                                                        <small class="text-muted"><?php echo htmlspecialchars($teacher['department']); ?></small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="courses-section" class="mb-4">
                        <h4 class="mb-3"><i class="fas fa-book me-2"></i>Courses</h4>
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Assign Course to Teacher</h5></div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="teacher_id" class="form-label">Teacher</label>
                                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                                <option value="">Select Teacher</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo (int)$teacher['teacher_id']; ?>">
                                                        <?php echo htmlspecialchars($teacher['name']); ?> (<?php echo htmlspecialchars($teacher['department']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="course_id" class="form-label">Course</label>
                                            <select class="form-select" id="course_id" name="course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo (int)$course['course_id']; ?>">
                                                        <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?>
                                                        (Sem <?php echo (int)$course['semester_no']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="offering_session" class="form-label">Session</label>
                                            <input type="text" class="form-control" id="offering_session" name="offering_session" placeholder="e.g. 2022-2026" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="offering_semester_no" class="form-label">Semester</label>
                                            <select class="form-select" id="offering_semester_no" name="offering_semester_no" required>
                                                <option value="">Select Semester</option>
                                                <?php for ($semesterOption = 1; $semesterOption <= 12; $semesterOption++): ?>
                                                    <option value="<?php echo $semesterOption; ?>"><?php echo $semesterOption; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="offering_section" class="form-label">Section</label>
                                            <input type="text" class="form-control" id="offering_section" name="offering_section" placeholder="A / B" maxlength="10" required>
                                        </div>
                                        <button type="submit" name="assign_course" class="btn btn-primary">Assign Course</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                            <div class="col-lg-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Import Courses from Excel/CSV</h5></div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">Headers: <strong>course_code, course_title, department, semester_no</strong> (+ optional <strong>credit_hours</strong>)</p>
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-12">
                                                <label for="course_excel" class="form-label">Excel/CSV File (.xlsx, .csv, .ods)</label>
                                                <input type="file" class="form-control" id="course_excel" name="course_excel" accept=".xlsx,.csv,.ods" required>
                                            </div>
                                            <div class="col-md-12">
                                                <button type="submit" name="import_courses" class="btn btn-primary w-100">
                                                    <i class="fas fa-file-import me-1"></i>Import Courses
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                            <div class="col-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header"><h5 class="mb-0">Recent Teacher Assignments</h5></div>
                                <div class="card-body">
                                    <?php if (empty($recentAssignments)): ?>
                                        <p class="text-muted mb-0">No assignments yet.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recentAssignments as $item): ?>
                                                <li class="list-group-item px-0">
                                                        <strong>Combined Class Block</strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Session <?php echo htmlspecialchars($item['session']); ?> | Semester <?php echo (int)$item['semester_no']; ?> | Section <?php echo htmlspecialchars($item['section']); ?>
                                                        </small>
                                                    <br>
                                                        <small class="text-muted">
                                                            Courses: <?php echo htmlspecialchars($item['course_list'] ?: 'N/A'); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            Teacher(s): <?php echo htmlspecialchars($item['teacher_names'] ?: 'N/A'); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            Assignments: <?php echo (int)$item['assigned_count']; ?>
                                                        </small>
                                                        <br>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y h:i A', strtotime($item['assigned_at'])); ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        </div>
                    </section>
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
