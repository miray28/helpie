<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
use function Helpie\Auth\requireAdmin;

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Verify admin access
requireAdmin();

// Available FontAwesome icons for categories
$availableIcons = [
    'fa-wheelchair' => 'Wheelchair',
    'fa-stethoscope' => 'Medical',
    'fa-kit-medical' => 'First Aid',
    'fa-hand-holding-heart' => 'Care',
    'fa-pills' => 'Medication',
    'fa-hospital' => 'Hospital',
    'fa-bed' => 'Bed',
    'fa-crutch' => 'Crutch'
];

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);

                // Validate inputs
                if (empty($name)) {
                    throw new Exception("Category name is required");
                }

                if (!array_key_exists($icon, $availableIcons)) {
                    throw new Exception("Invalid icon selected");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO categories (name, description, icon) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$name, $description, $icon]);
                $_SESSION['success'] = "Category added successfully";
                break;

            case 'edit':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $id = (int)$_POST['category_id'];

                // Validate inputs
                if (empty($name) || empty($id)) {
                    throw new Exception("Invalid input data");
                }

                if (!array_key_exists($icon, $availableIcons)) {
                    throw new Exception("Invalid icon selected");
                }

                $stmt = $pdo->prepare("
                    UPDATE categories 
                    SET name = ?, description = ?, icon = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $icon, $id]);
                $_SESSION['success'] = "Category updated successfully";
                break;

            case 'delete':
                $id = (int)$_POST['category_id'];
                
                // Check if category has posts
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cannot delete category with existing posts");
                }

                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Category deleted successfully";
                break;
        }
    } catch (Exception $e) {
        error_log("Category Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: categories.php');
    exit;
}

// Fetch all categories with post counts
try {
    $categories = $pdo->query("
        SELECT 
            c.*,
            COUNT(p.id) as post_count
        FROM categories c
        LEFT JOIN posts p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Category Fetch Error: " . $e->getMessage());
    $categories = [];
    $_SESSION['error'] = "Error loading categories";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Include the admin sidebar -->
    <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Categories</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
        </div>

        <?php include '../../includes/alerts.php'; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Posts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <i class="fas <?= htmlspecialchars($category['icon']) ?> fa-lg"></i>
                                </td>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td><?= htmlspecialchars($category['description']) ?></td>
                                <td><?= $category['post_count'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-category" 
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= htmlspecialchars($category['name']) ?>"
                                            data-description="<?= htmlspecialchars($category['description']) ?>"
                                            data-icon="<?= htmlspecialchars($category['icon']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-category"
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= htmlspecialchars($category['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <select name="icon" class="form-select" required>
                                <option value="">Select an icon</option>
                                <?php foreach ($availableIcons as $value => $label): ?>
                                    <option value="<?= $value ?>">
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <select name="icon" id="edit_icon" class="form-control" required>
                                <?php foreach ($availableIcons as $icon => $label): ?>
                                    <option value="<?= $icon ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit category handlers
        document.querySelectorAll('.edit-category').forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
                document.getElementById('edit_category_id').value = this.dataset.id;
                document.getElementById('edit_name').value = this.dataset.name;
                document.getElementById('edit_description').value = this.dataset.description;
                document.getElementById('edit_icon').value = this.dataset.icon;
                modal.show();
            });
        });

        // Delete category handlers
        document.querySelectorAll('.delete-category').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm(`Are you sure you want to delete "${this.dataset.name}"?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="${this.dataset.id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    </script>
</body>
</html>