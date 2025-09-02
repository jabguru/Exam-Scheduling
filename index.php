<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect to appropriate dashboard if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: /Exam-Scheduling/admin/dashboard.php");
            break;
        case 'Student':
            header("Location: /Exam-Scheduling/student/dashboard.php");
            break;
        case 'Lecturer':
            header("Location: /Exam-Scheduling/lecturer/dashboard.php");
            break;
        default:
            header("Location: /Exam-Scheduling/login.php");
    }
    exit();
}

$pageTitle = "Welcome";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Scheduling System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/Exam-Scheduling/assets/css/style.css">
</head>
<body>
    <div class="landing-page">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row min-vh-100 align-items-center">
                    <div class="col-lg-6">
                        <div class="hero-content">
                            <h1 class="display-4 fw-bold text-primary mb-4">
                                <i class="fas fa-calendar-alt"></i>
                                Exam Scheduling System
                            </h1>
                            <p class="lead mb-4">
                                Automated examination timetable management for educational institutions. 
                                Streamline your exam scheduling process with our intelligent conflict 
                                detection and resource optimization system.
                            </p>
                            <div class="hero-buttons">
                                <a href="/Exam-Scheduling/login.php" class="btn btn-primary btn-lg me-3">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                                <a href="#features" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-info-circle"></i> Learn More
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-image text-center">
                            <i class="fas fa-calendar-check display-1 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-5 bg-light">
            <div class="container">
                <div class="row mb-5">
                    <div class="col-12 text-center">
                        <h2 class="display-5 fw-bold mb-3">Key Features</h2>
                        <p class="lead text-muted">Comprehensive exam management solution for modern educational institutions</p>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                                <h4>Automated Scheduling</h4>
                                <p class="text-muted">
                                    Intelligent algorithm automatically generates conflict-free exam 
                                    timetables with optimal resource allocation.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-success mb-3"></i>
                                <h4>Multi-Role Access</h4>
                                <p class="text-muted">
                                    Role-based access control for administrators, lecturers, 
                                    and students with customized dashboards.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h4>Conflict Detection</h4>
                                <p class="text-muted">
                                    Real-time conflict detection prevents scheduling overlaps 
                                    and resource double-booking automatically.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-building fa-3x text-info mb-3"></i>
                                <h4>Venue Management</h4>
                                <p class="text-muted">
                                    Comprehensive venue management with capacity tracking 
                                    and facility requirements matching.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-bar fa-3x text-danger mb-3"></i>
                                <h4>Reports & Analytics</h4>
                                <p class="text-muted">
                                    Generate comprehensive reports and analytics for 
                                    exam statistics, venue utilization, and more.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <i class="fas fa-mobile-alt fa-3x text-secondary mb-3"></i>
                                <h4>Mobile Responsive</h4>
                                <p class="text-muted">
                                    Fully responsive design ensures seamless access 
                                    across all devices and screen sizes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Section -->
        <section class="py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h2 class="display-5 fw-bold mb-4">Why Choose Our System?</h2>
                        <div class="benefits-list">
                            <div class="benefit-item d-flex mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5>Time Saving</h5>
                                    <p class="text-muted">Reduce manual scheduling time from days to minutes with automated algorithms.</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item d-flex mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5>Error Reduction</h5>
                                    <p class="text-muted">Eliminate human errors with intelligent conflict detection and validation.</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item d-flex mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5>Resource Optimization</h5>
                                    <p class="text-muted">Maximize venue utilization and optimize invigilator assignments efficiently.</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item d-flex mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5>Real-time Updates</h5>
                                    <p class="text-muted">Instant notifications and updates keep all stakeholders informed.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="text-center">
                            <i class="fas fa-cogs display-1 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        .landing-page {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .hero-content h1 {
            color: white !important;
        }
        
        .hero-buttons .btn {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .benefit-item {
            padding: 1rem;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        
        .benefit-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</body>
</html>
