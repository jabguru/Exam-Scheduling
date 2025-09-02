// Exam Scheduling System - Main JavaScript

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Form validation
    $('form').on('submit', function() {
        var hasErrors = false;
        
        // Check required fields
        $(this).find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                hasErrors = true;
            } else {
                $(this).removeClass('is-invalid').addClass('is-valid');
            }
        });

        // Check email format
        $(this).find('input[type="email"]').each(function() {
            var email = $(this).val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                $(this).addClass('is-invalid');
                hasErrors = true;
            }
        });

        return !hasErrors;
    });

    // Real-time search
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.searchable-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Date picker initialization
    $('input[type="date"]').each(function() {
        if (!$(this).val()) {
            $(this).val(new Date().toISOString().split('T')[0]);
        }
    });

    // Time picker validation
    $('input[type="time"]').on('change', function() {
        var startTime = $('input[name="start_time"]').val();
        var endTime = $('input[name="end_time"]').val();
        
        if (startTime && endTime && startTime >= endTime) {
            $(this).addClass('is-invalid');
            showAlert('error', 'End time must be after start time');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
});

// Utility Functions
function showAlert(type, message) {
    var alertClass = 'alert-' + (type === 'error' ? 'danger' : type);
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
    
    $('#alertContainer').html(alertHtml);
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}

function showLoading(button) {
    var $btn = $(button);
    $btn.data('original-text', $btn.html());
    $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Loading...');
    $btn.prop('disabled', true);
}

function hideLoading(button) {
    var $btn = $(button);
    $btn.html($btn.data('original-text'));
    $btn.prop('disabled', false);
}

// AJAX Form Handler
function submitAjaxForm(form, successCallback, errorCallback) {
    var $form = $(form);
    var formData = new FormData(form);
    
    $.ajax({
        url: $form.attr('action'),
        type: $form.attr('method') || 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                if (successCallback) successCallback(response);
                else showAlert('success', response.message || 'Operation completed successfully');
            } else {
                if (errorCallback) errorCallback(response);
                else showAlert('error', response.message || 'An error occurred');
            }
        },
        error: function(xhr) {
            var message = 'An error occurred. Please try again.';
            try {
                var response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch (e) {}
            
            if (errorCallback) errorCallback({message: message});
            else showAlert('error', message);
        }
    });
}

// Data Tables Enhancement
function initializeDataTable(selector, options = {}) {
    var defaultOptions = {
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            search: "Search records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _Total_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    };
    
    return $(selector).DataTable($.extend(defaultOptions, options));
}

// Schedule Calendar Functions
function generateCalendar(schedules) {
    var calendar = $('#calendar');
    if (calendar.length === 0) return;
    
    calendar.fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        events: schedules.map(function(schedule) {
            return {
                title: schedule.course_title,
                start: schedule.exam_date + 'T' + schedule.start_time,
                end: schedule.exam_date + 'T' + schedule.end_time,
                description: 'Venue: ' + schedule.venue_name,
                backgroundColor: getStatusColor(schedule.status)
            };
        }),
        eventClick: function(event) {
            showScheduleDetails(event);
        }
    });
}

function getStatusColor(status) {
    switch (status) {
        case 'Scheduled': return '#0dcaf0';
        case 'Ongoing': return '#ffc107';
        case 'Completed': return '#198754';
        case 'Cancelled': return '#dc3545';
        default: return '#6c757d';
    }
}

// Conflict Detection
function checkScheduleConflicts(newSchedule) {
    return new Promise(function(resolve, reject) {
        $.ajax({
            url: '/Exam-Scheduling/api/check-conflicts.php',
            type: 'POST',
            data: JSON.stringify(newSchedule),
            contentType: 'application/json',
            success: function(response) {
                resolve(response.conflicts || []);
            },
            error: function() {
                reject('Unable to check for conflicts');
            }
        });
    });
}

// Auto-save functionality
function enableAutoSave(formSelector, saveUrl) {
    var $form = $(formSelector);
    var saveTimeout;
    
    $form.find('input, select, textarea').on('input change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
            saveFormData($form, saveUrl);
        }, 2000); // Save after 2 seconds of inactivity
    });
}

function saveFormData($form, saveUrl) {
    var formData = $form.serialize();
    
    $.ajax({
        url: saveUrl,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Changes saved automatically');
            }
        },
        error: function() {
            showAlert('warning', 'Auto-save failed. Please save manually.');
        }
    });
}

// Export Functions
function exportTableToCSV(tableId, filename) {
    var csv = [];
    var rows = document.querySelectorAll('#' + tableId + ' tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    var csvFile = new Blob([csv], {type: 'text/csv'});
    var downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print Functions
function printSchedule() {
    window.print();
}

function printElement(elementId) {
    var printContent = document.getElementById(elementId);
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent.innerHTML;
    window.print();
    document.body.innerHTML = originalContent;
}

// Notification Functions
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function showNotification(title, message, icon = '/Exam-Scheduling/assets/images/icon.png') {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: message,
            icon: icon
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Request notification permission
    requestNotificationPermission();
    
    // Initialize any data tables
    if (typeof DataTable !== 'undefined') {
        $('.data-table').each(function() {
            initializeDataTable(this);
        });
    }
    
    // Add fade-in animation to cards
    $('.card').addClass('fade-in');
});
