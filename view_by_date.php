<?php
session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Please log in first.");
}

date_default_timezone_set('Europe/Berlin');

$mysqli = new mysqli("localhost", "root", "", "login_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$tasks = [];
$selected_date = $_GET['date'] ?? '';

if (!empty($selected_date)) {
    $stmt = $mysqli->prepare("
        SELECT 
            d.data,
            t.name AS task_name,
            d.completed,
            d.note,
            d.time_spent
        FROM 
            daily_task_data d
        JOIN 
            tasks t ON d.task_id = t.id
        WHERE 
            d.user_id = ? AND d.data = ?
        ORDER BY 
            t.name ASC
    ");
    $stmt->bind_param("is", $user_id, $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Tasks by Date</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
</head>
<body>

<h2>View Tasks by Date</h2>

<form method="get">
    <label>Select Date:</label>
    <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" required>
    <button type="submit">View Tasks</button>
</form>

<?php if ($selected_date): ?>
    <h3>Tasks for <?= htmlspecialchars($selected_date) ?>:</h3>

    <?php if (empty($tasks)): ?>
        <p>No tasks found for this date.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Task</th>
                    <th>Completed</th>
                    <th>Note</th>
                    <th>Time Spent (Mins)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?= htmlspecialchars($task['data']) ?></td>
                        <td><?= htmlspecialchars($task['task_name']) ?></td>
                        <td><?= $task['completed'] ? "✅" : "❌" ?></td>
                        <td><?= htmlspecialchars($task['note']) ?></td>
                        <td><?= htmlspecialchars($task['time_spent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>

<br>
<a href="dashboard.php"><button type="button">← Back to Dashboard</button></a>

</body>
</html>
