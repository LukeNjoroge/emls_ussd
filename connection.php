<?php
function OpenCon()
{
    $servername = "localhost";
    $username = "luke";
    $password = "Zivish2019#";
    $db = "elms_db";

    // Create connection
    // $conn = new mysqli($servername, $username, $password,$db) or die("Connect failed: %s\n". $conn->connect_error);

    // Create connection
    $conn = mysqli_connect($servername, $username, $password, $db);
    // Check connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
}
?>