<?php

/*
Idsi x olan biletin tüm detaylarını getirmek için kullanılan fonksiyon.
 */
function getTicketDetails($conn, $ticket_id) {

    $sql = "SELECT 
                t.*, 
                d.department_name, 
                c.username AS customer_name, 
                e.username AS employee_name
            FROM tickets t
            LEFT JOIN departments d ON t.department_id = d.department_id
            LEFT JOIN users c ON t.customer_id = c.user_id
            LEFT JOIN users e ON t.employee_id = e.user_id
            WHERE t.ticket_id = ?";

    // sql injection için önlem olarak prepared statement kullanıyoruz
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("SQL Preparation Error: " . mysqli_error($conn));
    }

    // 2. '?' işareti yerine integer (i) türünde $ticket_id değişkenini bağlıyoruz
    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    
    // Sonuçları bir dizi olarak alıyoruz
    $data = mysqli_fetch_assoc($result);
    //  Statement bağlantısını kapatıyoruz
    mysqli_stmt_close($stmt);
    
    return $data;
}

/*
  Ticket ID'sine ait tüm mesajları tarih sırasına göre getirir 
  ve bu mesajları gönderen kullanıcıların bilgilerini getirir.
*/
function getTicketMessages($conn, $ticket_id) {
    // chat ile users tablosunu birleştirdim
    // chat_created_at ASC -> Mesajları eskiden yeniye doğru sıralar.
    $sql = "SELECT 
                c.*, 
                u.username,
                u.role_ID
            FROM chat c
            INNER JOIN users u ON c.user_ID = u.user_ID
            WHERE c.ticket_ID = ?
            ORDER BY c.chat_created_at ASC";

    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("An error occurred while retrieving chat messages. " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    
    // Sonuçları bir dizi olarak al
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    mysqli_stmt_close($stmt);
    
    return $messages;
}


/*
 mesajı veritabanına kaydetme fonksiyonu.
 */
function sendMessage($conn, $ticket_id, $user_id, $message, $attachment_file=null) {
    $sql = "INSERT INTO chat (ticket_ID, user_ID, chat_message,chat_attachment_file, chat_created_at) VALUES (?, ?, ?,?,  NOW());";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "iiss", $ticket_id, $user_id, $message,$attachment_file);
    
    if (mysqli_stmt_execute($stmt)) {
        // Eklenen son kaydın ID'sini doğrudan stmt nesnesinden alıyoruz
        $inserted_id = mysqli_stmt_insert_id($stmt);
        mysqli_stmt_close($stmt);
        return $inserted_id; // True yerine oluşan ID'yi döndürüyoruz
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/*
  Ticket durumunu (Open, In Progress, Resolved, Closed) güncelleyen fonk.
 */
function updateTicketStatus($conn, $ticket_id, $status) {
    $sql = "UPDATE tickets SET ticket_status = ? WHERE ticket_ID = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("SQL Preparation Error (UpdateStatus): " . mysqli_error($conn));
    }

    // Parametre tipleri: s =($status), i =($ticket_id)
    mysqli_stmt_bind_param($stmt, "si", $status, $ticket_id);
    
    $success = mysqli_stmt_execute($stmt);
    
    mysqli_stmt_close($stmt);
    
    return $success;
}

function assignTicketToDepartment($conn, $ticket_id, $department_id) {
    $sql = "UPDATE tickets SET department_id = ? WHERE ticket_ID = ?";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ii", $department_id, $ticket_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}
// Bildirim Oluşturma Fonksiyonu
if (!function_exists('createNotification')) {
    function createNotification($conn, $user_id, $type, $message) {
        $sql = "INSERT INTO notifications (user_ID, notification_type, notification_message, created_at, is_read) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $type, $message);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }
}

?>