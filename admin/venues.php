<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Venue Management";

// Handle venue creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $venueId = $_POST['venue_id'] ?? null;
                $venueName = sanitizeInput($_POST['venue_name']);
                $venueCode = sanitizeInput($_POST['venue_code']);
                $capacity = intval($_POST['capacity']);
                $venueType = sanitizeInput($_POST['venue_type']);
                $facilities = sanitizeInput($_POST['facilities']);
                $location = sanitizeInput($_POST['location']);
                $isAvailable = isset($_POST['is_available']) ? 1 : 0;
                
                if ($action === 'create') {
                    $query = "INSERT INTO venues (venue_name, venue_code, capacity, venue_type, facilities, location, is_available) 
                             VALUES (:venue_name, :venue_code, :capacity, :venue_type, :facilities, :location, :is_available)";
                    $stmt = $db->prepare($query);
                } else {
                    $query = "UPDATE venues SET venue_name = :venue_name, venue_code = :venue_code, capacity = :capacity, 
                             venue_type = :venue_type, facilities = :facilities, location = :location, is_available = :is_available 
                             WHERE venue_id = :venue_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':venue_id', $venueId);
                }
                
                $stmt->bindParam(':venue_name', $venueName);
                $stmt->bindParam(':venue_code', $venueCode);
                $stmt->bindParam(':capacity', $capacity);
                $stmt->bindParam(':venue_type', $venueType);
                $stmt->bindParam(':facilities', $facilities);
                $stmt->bindParam(':location', $location);
                $stmt->bindParam(':is_available', $isAvailable);
                
                $stmt->execute();
                
                setAlert('success', $action === 'create' ? 'Venue created successfully.' : 'Venue updated successfully.');
            } elseif ($action === 'delete') {
                $venueId = intval($_POST['venue_id']);
                
                $query = "UPDATE venues SET is_available = 0 WHERE venue_id = :venue_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':venue_id', $venueId);
                $stmt->execute();
                
                setAlert('success', 'Venue deactivated successfully.');
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: venues.php");
    exit();
}

// Get venues with pagination
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$recordsPerPage = 15;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM venues";
    $whereClause = "";
    
    if (!empty($search)) {
        $whereClause = " WHERE venue_name LIKE :search OR venue_code LIKE :search OR location LIKE :search";
        $countQuery .= $whereClause;
    }
    
    $countStmt = $db->prepare($countQuery);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $countStmt->bindParam(':search', $searchParam);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get venues
    $query = "SELECT * FROM venues" . 
              $whereClause . 
              " ORDER BY venue_name 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading venues: " . $e->getMessage();
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-building"></i> Venue Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#venueModal" onclick="openVenueModal()">
                <i class="fas fa-plus"></i> Add Venue
            </button>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by venue name, code, or location..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="venues.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Venues Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Venues (<?php echo number_format($totalRecords); ?> total)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($venues)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Venue Code</th>
                                <th>Venue Name</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Location</th>
                                <th>Facilities</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($venues as $venue): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($venue['venue_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($venue['venue_name']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($venue['venue_type']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $venue['capacity']; ?> seats</span>
                                </td>
                                <td><?php echo htmlspecialchars($venue['location']); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($venue['facilities'] ?: 'None specified'); ?></small>
                                </td>
                                <td>
                                    <span class="badge status-<?php echo $venue['is_available'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $venue['is_available'] ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editVenue(<?php echo htmlspecialchars(json_encode($venue)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            onclick="deleteVenue(<?php echo $venue['venue_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['totalPages'] > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagination['page'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No venues found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Venue Modal -->
<div class="modal fade" id="venueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="venueModalTitle">Add Venue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="venueForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="venue_id" id="venueId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="venueName" class="form-label">Venue Name *</label>
                            <input type="text" class="form-control" id="venueName" name="venue_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="venueCode" class="form-label">Venue Code *</label>
                            <input type="text" class="form-control" id="venueCode" name="venue_code" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="capacity" class="form-label">Capacity *</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" required min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="venueType" class="form-label">Venue Type *</label>
                            <select class="form-control" id="venueType" name="venue_type" required>
                                <option value="">Select Type</option>
                                <option value="Hall">Hall</option>
                                <option value="Classroom">Classroom</option>
                                <option value="Laboratory">Laboratory</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="facilities" class="form-label">Facilities</label>
                        <textarea class="form-control" id="facilities" name="facilities" rows="3" 
                                  placeholder="e.g., Projector, Air Conditioning, Sound System"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isAvailable" name="is_available" checked>
                        <label class="form-check-label" for="isAvailable">Available for scheduling</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Venue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate this venue? It will no longer be available for scheduling.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="venue_id" id="deleteVenueId">
                    <button type="submit" class="btn btn-danger">Deactivate Venue</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openVenueModal() {
    document.getElementById('venueModalTitle').textContent = 'Add Venue';
    document.getElementById('formAction').value = 'create';
    document.getElementById('venueForm').reset();
    document.getElementById('venueId').value = '';
}

function editVenue(venue) {
    document.getElementById('venueModalTitle').textContent = 'Edit Venue';
    document.getElementById('formAction').value = 'update';
    document.getElementById('venueId').value = venue.venue_id;
    document.getElementById('venueName').value = venue.venue_name;
    document.getElementById('venueCode').value = venue.venue_code;
    document.getElementById('capacity').value = venue.capacity;
    document.getElementById('venueType').value = venue.venue_type;
    document.getElementById('location').value = venue.location;
    document.getElementById('facilities').value = venue.facilities || '';
    document.getElementById('isAvailable').checked = venue.is_available == 1;
    
    new bootstrap.Modal(document.getElementById('venueModal')).show();
}

function deleteVenue(venueId) {
    document.getElementById('deleteVenueId').value = venueId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
