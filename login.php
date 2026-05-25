<?php
session_start();
include 'includes/db_connect.php';

$error = ""; 
$login_success = false; 
$redirect_url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];
    $pass_encrypted = md5($pass_input);

    $sql = "SELECT * FROM users WHERE username = '$user_input' AND password = '$pass_encrypted'";
    $result = $conn->query($sql);
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // CHECK IF SUSPENDED
        if ($row['status'] == 'suspended') {
            $error = "🚫 Access Denied: Your account has been suspended. Contact Admin.";
        } else {
            // Account is Active - Proceed to Login
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            $login_success = true;
            
            if ($row['role'] == 'admin') {
                $redirect_url = "admin/dashboard.php";
            } else {
                $redirect_url = "sales/dashboard.php";
            }
        }
    } else {
        $error = "Incorrect Username or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Galadawa Textiles</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/toastify.min.css">
    <script src="js/toastify.min.js"></script>
    <script src="js/toast.js"></script>
</head>
<body class="login-page">

    <div class="login-wrapper">
        <div class="login-box">
            
            <img src="img/logo.png" alt="Galadawa Logo" class="login-logo">

            <h2>Welcome Back</h2>
            <p>Galadawa Textile Management System</p>
            
            <?php if($error != "") { echo "<div class='error-msg'>$error</div>"; } ?>

            <form action="" method="POST">
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter username" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit" class="btn-login">Secure Login</button>
            </form>
        </div>
    </div>

    <?php if ($login_success): ?>
    <script>
        const redirectUrl = '<?php echo $redirect_url; ?>';
        if (window.showToast) {
            showToast("Login Successful", { type: "success", duration: 2000 });

            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 2000);
        } else {
            window.location.href = redirectUrl;
        }
    </script>
    <?php endif; ?>

</body>
</html>
