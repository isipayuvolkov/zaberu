<?php
session_start();

// Если пользователь уже вошел в аккаунт — кидаем в ЛК, если нет — на логин
if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
} else {
    header('Location: login.php');
}
exit;
?>