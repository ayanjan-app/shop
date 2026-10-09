<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jahan_journal";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// 🔹 Handle Approve/Reject Actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['paper_id'])) {
    $paper_id = intval($_POST['paper_id']);
    $action = $_POST['action'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE research_paper_submissions SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $paper_id);
        $stmt->execute();
        $stmt->close();
    }

    // Refresh page safely
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 🔹 Handle Comment Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['comment_text'], $_POST['paper_id'])) {
    $paper_id = intval($_POST['paper_id']);
    $comment = trim($_POST['comment_text']);

    if (!empty($comment)) {
        $stmt = $conn->prepare("UPDATE research_paper_submissions SET comment=? WHERE id=?");
        $stmt->bind_param("si", $comment, $paper_id);
        $stmt->execute();
        $stmt->close();
    }

    // Refresh page safely
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch journals
$sql = "SELECT * FROM research_paper_submissions ORDER BY submission_date DESC";
$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Dashboard | Admin Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #6f42c1;
            --accent-color: #36b9cc;
            --light-bg: #f8f9fc;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #4e4e4e;
        }
        
        .sidebar {
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.8rem 1rem;
            margin: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar-custom {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.3s;
        }
        
        .card-custom:hover {
            transform: translateY(-5px);
        }
        
        .journal-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        
        .journal-card:hover {
            border-left: 4px solid var(--secondary-color);
        }
        
        .stats-card {
            color: white;
            border-radius: 10px;
        }
        
        .stats-card-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        
        .stats-card-success {
            background: linear-gradient(to right, #1cc88a, #13855c);
        }
        
        .stats-card-info {
            background: linear-gradient(to right, var(--accent-color), #2c9faf);
        }
        
        .btn-view {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-view:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #6e707e;
        }
        
        .page-header {
            color: var(--primary-color);
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-md-block">
                <div class="text-center mb-4">
                    <h4><i class="bi bi-journal-text"></i> Journal Admin</h4>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-grid"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-journal-plus"></i> Add Journal
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Top Navbar -->
                <nav class="navbar navbar-expand-lg navbar-custom rounded mb-4">
                    <div class="container-fluid">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        
                        <div class="collapse navbar-collapse" id="navbarContent">
                            <ul class="navbar-nav ms-auto">
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-person-circle"></i> Welcome, <?php echo $_SESSION['username']; ?>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <h1 class="page-header"><i class="bi bi-journal-text"></i> Journal Submissions</h1>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Journals</div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $result->num_rows; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-journal-text fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">This Month</div>
                                        <div class="h5 mb-0 font-weight-bold">8</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-month fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-info h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Universities</div>
                                        <div class="h5 mb-0 font-weight-bold">12</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-building fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Provinces</div>
                                        <div class="h5 mb-0 font-weight-bold">7</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-geo-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Journals Table -->
                <div class="card card-custom shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">All Journal Submissions</h6>
                        <div>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Export</button>
                            <button class="btn btn-sm btn-success"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="journalsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Author</th>
                                        <th>University</th>
                                        <th>Paper Title</th>
                                        <th>Province</th>
                                        <th>Submission Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>{$row['id']}</td>";
                                            echo "<td>{$row['author_first_name']} {$row['author_last_name']}<br><small class='text-muted'>{$row['author_email']}</small></td>";
                                            echo "<td>{$row['university_name']}<br><small class='text-muted'>{$row['department_name']}</small></td>";
                                            echo "<td>{$row['paper_title']}</td>";
                                            echo "<td>{$row['province']}</td>";
                                            echo "<td>" . date('M j, Y', strtotime($row['submission_date'])) . "</td>";

                                            // Status badge
                                            $status = $row['status'] ?? 'pending';
                                            if ($status === 'approved') $badge = "<span class='badge bg-success mb-1'>Approved</span>";
                                            elseif ($status === 'rejected') $badge = "<span class='badge bg-danger mb-1'>Rejected</span>";
                                            else $badge = "<span class='badge bg-secondary mb-1'>Pending</span>";

                                            echo "<td>
                                                $badge<br>
                                                <button class='btn btn-sm btn-view mb-1' data-bs-toggle='modal' data-bs-target='#journalModal' data-id='{$row['id']}'><i class='bi bi-eye'></i> Details</button>
                                                <a href='{$row['pdf_file']}' class='btn btn-sm btn-view mb-1'>View PDF</a><br>
                                                
                                                <!-- Comment Button -->
                                                <button class='btn btn-sm btn-info mb-1' data-bs-toggle='modal' data-bs-target='#commentModal' 
                                                        data-paper-id='{$row['id']}' data-existing-comment='".htmlspecialchars($row['comment'] ?? '')."'>
                                                    <i class='bi bi-chat-left-text'></i> Comment
                                                </button><br>
                                                
                                                <form method='POST' style='display:inline-block'>
                                                    <input type='hidden' name='paper_id' value='{$row['id']}'>
                                                    <button type='submit' name='action' value='approve' class='btn btn-sm btn-success mb-1'><i class='bi bi-check-circle'></i> Approve</button>
                                                </form>
                                                <form method='POST' style='display:inline-block'>
                                                    <input type='hidden' name='paper_id' value='{$row['id']}'>
                                                    <button type='submit' name='action' value='reject' class='btn btn-sm btn-danger mb-1'><i class='bi bi-x-circle'></i> Reject</button>
                                                </form>
                                            </td>";

                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>No journals found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Journal Details Modal -->
        <div class="modal fade" id="journalModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Journal Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="journalDetails">
                        <!-- Details will be loaded via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comment Modal -->
        <div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Comment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="paper_id" id="comment_paper_id">
                            <div class="mb-3">
                                <label for="comment_text" class="form-label">Comment:</label>
                                <textarea class="form-control" id="comment_text" name="comment_text" rows="4" placeholder="Enter your comment here..."></textarea>
                            </div>
                            <!-- Display existing comment if any -->
                            <div id="existing_comment" class="mt-3" style="display: none;">
                                <h6>Existing Comment:</h6>
                                <div class="alert alert-info" id="current_comment_text"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Comment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#journalsTable').DataTable({
                "pageLength": 10,
                "order": [[0, 'desc']]
            });
            
            // Handle modal view
            $('#journalModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var journalId = button.data('id');
                var modal = $(this);
                
                // In a real application, you would fetch this data via AJAX
                // For this example, we'll just show a placeholder
                modal.find('#journalDetails').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Author Information</h6>
                            <p><strong>Name:</strong> John Doe<br>
                            <strong>Email:</strong> john.doe@example.com<br>
                            <strong>Phone:</strong> (123) 456-7890</p>
                        </div>
                        <div class="col-md-6">
                            <h6>University Information</h6>
                            <p><strong>University:</strong> Example University<br>
                            <strong>Department:</strong> Computer Science<br>
                            <strong>Province:</strong> Ontario</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Paper Details</h6>
                            <p><strong>Title:</strong> Advanced Research in Computer Science</p>
                            <p><strong>Abstract:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris. Vivamus hendrerit arcu sed erat molestie vehicula.</p>
                            <p><strong>Submitted on:</strong> June 15, 2023</p>
                        </div>
                    </div>
                `);
                
                modal.find('#viewPdf').attr('href', 'uploads/sample.pdf');
            });

            // Handle comment modal
            $('#commentModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var paperId = button.data('paper-id');
                var existingComment = button.data('existing-comment');
                var modal = $(this);
                
                // Set paper ID in hidden field
                modal.find('#comment_paper_id').val(paperId);
                
                // Clear textarea
                modal.find('#comment_text').val('');
                
                // Handle existing comment
                var existingCommentDiv = modal.find('#existing_comment');
                if (existingComment && existingComment.trim() !== '') {
                    modal.find('#current_comment_text').text(existingComment);
                    existingCommentDiv.show();
                } else {
                    existingCommentDiv.hide();
                }
            });

            // Optional: Clear modal when closed
            $('#commentModal').on('hidden.bs.modal', function () {
                $(this).find('#comment_text').val('');
                $(this).find('#existing_comment').hide();
            });
        });
    </script>
</body>
</html>