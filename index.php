<?php
// Print the response as plain text so that the gateway can read it
header('Content-type: text/plain');

// Mysql connection
$servername = "localhost";
$username = "luke";
$password = "Zivish2019#";
$db = "elms_db";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $db);
// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Read the variables sent via POST from our API
$sessionId   = $_POST["sessionId"];
$serviceCode = $_POST["serviceCode"];
$phoneNumber = $_POST["phoneNumber"];
$ussd_string = $_POST["text"];

//Get data from database
$query = mysqli_query($conn, "SELECT id FROM agents WHERE mobile_number = ". $phoneNumber);
$row = mysqli_fetch_array($query);

$query = mysqli_query($conn, "SELECT polling_station_id FROM agent_polling_stations WHERE agent_id = ". $row[0]);
$row_agent_polling = mysqli_fetch_array($query);

$query = mysqli_query($conn, "SELECT ward_id FROM polling_stations WHERE id = ". $row_agent_polling[0]);
$row_polling = mysqli_fetch_array($query);

$sql = "SELECT id, name FROM aspirants WHERE ward_id = ". $row_polling[0];
$result = mysqli_query($conn, $sql);

$res = array();
if (mysqli_num_rows($result) > 0) {
  // output data of each row
  while($row_aspirants = mysqli_fetch_assoc($result)) {
    array_push($res, $row_aspirants["name"]);
  }
}

//The count tells us what level the user is at i.e how many times the user has responded
$level =0; 
if($ussd_string != "")
{  
    $ussd_string= str_replace("#", "*", $ussd_string);  
    $ussd_string_explode = explode("*", $ussd_string);  
    $level = count($ussd_string_explode);  
}    
$ussd_text  = "Select aspitantttt ".$phoneNumber." \n";
ussd_proceed($ussd_text);
//$level=0 means the user hasnt replied.We use levels to track the number of user replies
if($level == 0)
{
    displayMenu($conn, $res); // show the home/first menu
}

if ($level>0)
{  
    $count = count($res);
    register_vote($ussd_string_explode,$phone,$count, $res, $row_agent_polling, $conn);
}

//show the USSD still expect some input from user
function ussd_proceed($ussd_text)
{
    echo "CON $ussd_text";
}

//USSD terminates
function ussd_stop($ussd_text)
{
    echo "END $ussd_text";
}

//This is the home menu function
function displayMenu($conn, $res)
{
    $count = count($res);
    if ($count > 0)
    {
        $ussd_text  = "Select aspitant \n";
        for ($i = 0; $i < count($res); $i++) {
            $ussd_text .= ($i + 1).". ". $res[$i] ." \n";
        }

        ussd_proceed($ussd_text);
    }
    else
    {
        $ussd_text="You are not a registered Agent";
        ussd_stop($ussd_text);
    }
    
}

function register_vote($details,$phone,$count,$res,$polling_station_id,$conn){
    if (count($details)==1)
    {
        if ($count < (int)$details[0] || (int)$details[0] <= 0)
        {
            $ussd_text="Invalid Entry, please try again";
            ussd_stop($ussd_text);
        }
        else
        {
            $ussd_text="Enter No of votes ";
            ussd_proceed($ussd_text);
        }
    }
    if(count($details) == 2)
    {
        $votes=$details[1]; 
        // get current selection
        $ent = $res[$details[0]-1];

        if ((int)$details[1] < 0)
        {
            $ussd_text="Number of votes cannot be less than 0";
            ussd_stop($ussd_text);
        }
        else if(!is_numeric($details[1]))
        {
            $ussd_text="Votes must be a Numeric Number";
            ussd_stop($ussd_text);
        }
        else
        {
            $ussd_text="Confirm aspirans vote
            Aspirant: " . $ent . "\n" .
            "Total Votes: " . $votes. "\n\n " .
            "1. Accept \n 2. Cancel \n" ;
            ussd_proceed($ussd_text);
        }

    }

    else if(count($details) == 3)
    { 
        $votes=$details[1]; 
        $acceptDeny=$details[2]; 
        // get current selection
        $ent = $res[$details[0]-1];

       if($acceptDeny=="1")
       {  
            $query = mysqli_query($conn, "SELECT id FROM aspirants WHERE name = '". $ent ."'");
            $row = mysqli_fetch_array($query);
        
            //execute insert query   
            $sql = "UPDATE election_results SET no_of_votes='". $votes ."' WHERE aspirant_id=".$row[0]." and polling_station_id=".$polling_station_id[0];

            if (mysqli_query($conn, $sql)) 
            {
                $ussd_text="Record updated successfully";
                ussd_stop($ussd_text);
            } else {
                $ussd_text="Error updating record: Contact Admin";
                ussd_stop($ussd_text);
            }
        }
        else
        {
            $ussd_text="Entry Canceled";
            ussd_stop($ussd_text);
        }
    }
}
?>