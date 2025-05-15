<?php
session_start();
$user_id = $_SESSION['user_id']; 

date_default_timezone_set('EET');


$mysqli = new mysqli("localhost", "root", "", "login_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_task'])) {
    $task_name = $_POST['task_name'] ?? '';
    $task_description = $_POST['task_description'] ?? '';
    if (!empty($task_name)) {
        $stmt = $mysqli->prepare("INSERT INTO tasks (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $task_name, $task_description);
        $stmt->execute();
        $stmt->close();
    }
}

// handle loggin for completed tasks only 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_all_tasks'])) {
    $date = $_POST['date'] ?? '';
    if (!empty($date) && isset($_POST['task_data'])) {
        foreach ($_POST['task_data'] as $task_id => $data) {
            if (!isset($data['completed'])) {
                continue; 
            }

            $note = $data['note'] ?? '';
            $time_spent = $data['time_spent'] ?? null;

            $stmt = $mysqli->prepare("INSERT INTO daily_task_data (user_id, task_id, data, completed, note, time_spent) 
                                        VALUES (?, ?, ?, 1, ?, ?)");
            $stmt->bind_param("iissi", $user_id, $task_id, $date, $note, $time_spent);
            $stmt->execute();
            $stmt->close();
        }
    }
}

//  get tasks from user
$tasks = [];
$stmt = $mysqli->prepare("
    SELECT MIN(id) as id, name 
    FROM tasks 
    WHERE user_id = ? 
    GROUP BY name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();
while ($row = $results->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// get log history
$sql = "
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
        d.user_id = ?
    ORDER BY 
        d.data DESC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result(); 
$sql_user = "SELECT * FROM user 
            WHERE id = {$_SESSION["user_id"]}";
$result_user = $mysqli->query($sql_user);
$user= $result_user->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
</head>
<body>

<h1>Habit Tracker Dashboard</h1>
<h2>Hello <?= htmlspecialchars($user['name']) ?>,</h2>
<p><a href="logout.php">Log out</a></p>

<!-- 1. Task Creation -->
<h2>Create New Task:</h2>
<form method="post">
    <label>Task Name:</label>
    <input type="text" name="task_name" required>

    <label>Task Description:</label>
    <input name="task_description">
    <button class="btn" type="submit" name="create_task">Create Task</button>
</form>

<!-- 2. Log All Tasks -->
<h2>Log Today's Progress:</h2>
<form method="post">
    <?php $today = date('Y-m-d'); ?>
    <label for="date">Date:</label>
    <input type="date" name="date" value="<?php echo $today; ?>" required>

    <table>
        <thead>
            <tr>
                <th>Task</th>
                <th>Completed</th>
                <th>Minutes Spent</th>
                <th>Comment</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr class="task-row">
                    <td><?= htmlspecialchars($task['name']) ?></td>
                    <td><input type="checkbox" name="task_data[<?= $task['id'] ?>][completed]"></td>
                    <td><input type="number" name="task_data[<?= $task['id'] ?>][time_spent]" min="0" placeholder="Minutes"></td>
                    <td><input type="text" name="task_data[<?= $task['id'] ?>][note]" placeholder="Comment"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button class="btn" type="submit" name="log_all_tasks">Submit Today's Log</button>
</form>

<!-- 3. Task Log History (Today Only) -->
<h2>Today's Task Log:</h2>
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
        <?php
        $todayDate = (new DateTime('now'))->format('Y-m-d');

       
        $logs->data_seek(0); 

        while ($row = $logs->fetch_assoc()):
            $rowDate = (new DateTime($row['data']))->format('Y-m-d');
            if ($rowDate !== $todayDate) {
                continue; 
            }
        ?>
            <tr>
                <td><?= htmlspecialchars($row['data']) ?></td>
                <td><?= htmlspecialchars($row['task_name']) ?></td>
                <td><?= $row['completed'] ? "✅" : "❌" ?></td>
                <td><?= htmlspecialchars($row['note']) ?></td>
                <td><?= htmlspecialchars($row['time_spent']) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>



<a href="view_by_date.php"><button>View Tasks by Date</button></a>


<?php
$stmt->close(); 
$mysqli->close();
?>

</body>
</html>
