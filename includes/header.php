<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Exam Scheduling System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/Exam-Scheduling/assets/css/style.css">
    
    <!-- Additional CSS -->
    <?php if (isset($additionalCSS)) echo $additionalCSS; ?>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/Exam-Scheduling/">
                <i class="fas fa-calendar-alt"></i> Exam Scheduling
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (hasRole('Admin')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Administration
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/users.php">User Management</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/departments.php">Departments</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/courses.php">Courses</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/examinations.php">Examinations</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/periods.php">Academic Periods</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/venues.php">Venues</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/admin/schedules.php">Exam Schedules</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('Faculty')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chalkboard-teacher"></i> Faculty
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/Exam-Scheduling/faculty/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/faculty/exams.php">My Exams</a></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/faculty/students.php">Students</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('Student')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/Exam-Scheduling/student/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Exam-Scheduling/student/enrollment.php">
                            <i class="fas fa-book"></i> Course Enrollment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Exam-Scheduling/student/schedule.php">
                            <i class="fas fa-calendar"></i> My Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Exam-Scheduling/student/registration.php">
                            <i class="fas fa-clipboard-list"></i> My Examinations
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/Exam-Scheduling/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/Exam-Scheduling/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Alert Messages -->
    <?php 
    $alert = getAlert();
    if ($alert): 
    ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $alert['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="<?php echo isLoggedIn() ? 'container my-4' : ''; ?>">
