<?php

$user_ID = $_SESSION['userid']; 

$sql= "SELECT * FROM notifications 
        WHERE user_ID = ?
        AND is_read = 0
        ORDER BY created_at DESC ";

$stmt =mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $user_ID);

mysqli_stmt_execute($stmt);

$notification = mysqli_stmt_get_result($stmt);

mysqli_stmt_close($stmt);
