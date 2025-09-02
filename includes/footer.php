    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-calendar-alt"></i> Exam Scheduling System</h5>
                    <p class="mb-0">Automated examination timetable management for educational institutions.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> University of Lagos (UNILAG). All rights reserved.</p>
                    <small class="text-muted">Version 1.0.0</small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="/Exam-Scheduling/assets/js/main.js"></script>
    
    <!-- Additional JS -->
    <?php if (isset($additionalJS)) echo $additionalJS; ?>
</body>
</html>
