<?php 
require_once 'config.inc.php';
require_once 'dbHandler.inc.php';

if(!isset($_SESSION['userid'])) {
    header("Location: signin.php");
    exit();
}

$sql = "SELECT tickets.*, departments.department_name
    FROM tickets
    LEFT JOIN departments
    ON tickets.department_ID = departments.department_ID
    WHERE tickets.customer_ID = ?";

$params =[$_SESSION['userid']];
$types ="i";

if(!empty($_GET['search'])){
    $sql .= " AND (ticket_title like ? OR department_name LIKE ?)";
    $search= "%" .$_GET['search'] . "%";
    $params[] =$search;
    $params[] =$search;
    $types .= "ss";
}
if(!empty($_GET['status'])){
    $sql .= " AND ticket_status = ?";
    $params[] =$_GET['status'];
    $types .= "s";
}

if(!empty($_GET['department'])){
    $sql .= " AND tickets.department_ID = ?";
    $params[] =$_GET['department'];
    $types .= "s";
}

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>

<?php if(mysqli_num_rows($result) > 0): ?>
<?php while($row = mysqli_fetch_assoc($result)): ?>

<tr>
    <td><?= htmlspecialchars($row['ticket_title']) ?></td>
    <td><?= htmlspecialchars($row['department_name']) ?></td>
    <td><?= htmlspecialchars($row['ticket_created_at']) ?></td>
    <td class="ticket-priority">
        <span class="ticket-priority <?= strtolower(str_replace(' ','-',htmlspecialchars($row['ticket_priority']))) ?>">
            <?= htmlspecialchars($row['ticket_priority']) ?>
        </span>
    </td>
    <td class="ticket-status">
        <span class="ticket-status <?= strtolower(htmlspecialchars($row['ticket_status'])) ?>">
            <?= htmlspecialchars($row['ticket_status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="6">No tickets found.</td>
    </tr>
<?php endif; ?>