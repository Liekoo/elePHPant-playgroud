<?php
session_start();

// This goes up one level to find config.php in your root htdocs folder
require_once '../config.php'; 

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // REPLACE THESE WITH ACTUAL GOOGLE CLOUD CREDENTIALS
    $client_id = '.apps.googleusercontent.com';
    $client_secret = '';
    $redirect_uri = 'https://liekoo.ct.ws/auth/google-callback.php';

    // 1. Exchange the authorization code from Google for an Access Token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ]));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    // 2. If we successfully got the token, fetch the user's profile info
    if (isset($response['access_token'])) {
        $user_info = json_decode(file_get_contents("https://www.googleapis.com/oauth2/v1/userinfo?access_token=" . $response['access_token']), true);
        
        $email = $user_info['email'];
        $name = $user_info['name'];
        $google_id = $user_info['id'];

        // 3. Database Logic: Check if the user already exists
        // Note: Using 'users' in lowercase to avoid the InfinityFree case-sensitivity error!
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists -> Log them in
            $user = $result->fetch_assoc();
            // Assuming your primary key is 'User_ID' based on our previous troubleshooting. 
            // Change this to 'user_id' if you made everything lowercase.
            $_SESSION['user_id'] = $user['User_ID']; 
        } else {
            // User doesn't exist -> Register them, then log them in
            $insert = $conn->prepare("INSERT INTO users (username, email, google_id) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $name, $email, $google_id);
            $insert->execute();
            $_SESSION['user_id'] = $conn->insert_id; // Grabs the newly created ID
        }

        // 4. Redirect back to your root homepage
        header("Location: /index.php"); 
        exit;
        
    } else {
        // Helpful for debugging if Google rejects the token exchange
        echo "Error getting access token. Please verify your Client ID, Client Secret, and Redirect URI.";
        echo "<br>Google Response: " . print_r($response, true);
        exit;
    }
} else {
    // If someone visits this page directly without coming from Google, send them to login
    header("Location: /auth/login.php");
    exit;
}
?>