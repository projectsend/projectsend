<?php


$servername = "msend.cqk9wdfjm5ij.us-east-1.rds.amazonaws.com";
$username = "msenduser";
$password = "msendpassword";
$dbname = "msend";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);
// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "INSERT INTO tbl_options (name, value)
VALUES ('branding_title', 'Brand Name')";

if (mysqli_query($conn, $sql)) {
    echo "New record created successfully";
} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

mysqli_close($conn);
?>