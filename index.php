<?php
// Print the response as plain text so that the gateway can read it
header('Content-type: text/plain');

// Mysql connection
$servername = "localhost";

//Development
// $username = "luke";
// $password = "Zivish2019#";

//production
$username = "ussd";
$password = "elms2022!";
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

$sql_poll = "SELECT polling_station_id FROM agent_polling_stations WHERE agent_id = ". $row[0];
$result_poll = mysqli_query($conn, $sql_poll);

$res_poll = array();
if (mysqli_num_rows($result_poll) > 0) {
  // output data of each row
  while($row_poll_agent = mysqli_fetch_assoc($result_poll)) {
    $query = mysqli_query($conn, "SELECT name FROM polling_stations WHERE id = ". $row_poll_agent["polling_station_id"]);
    $row_poll = mysqli_fetch_array($query);
    array_push($res_poll, $row_poll["name"]);
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

//$level=0 means the user hasnt replied.We use levels to track the number of user replies
if($level == 0)
{
    displayMenu($conn, $res_poll); // show the home/first menu
}

if ($level>0)
{  
    $count = count($res);
    register_vote($ussd_string_explode,$phone,$row_agent_polling, $res_poll, $conn);
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
function displayMenu($conn, $res_poll)
{
    $count = count($res_poll);
    if ($count > 0)
    {
        $ussd_text  = "Select polling stations \n";
        for ($i = 0; $i < count($res_poll); $i++) {
            $ussd_text .= ($i + 1).". ". $res_poll[$i] ." \n";
        }

        ussd_proceed($ussd_text);
    }
    else
    {
        $ussd_text="You are not a registered Agent";
        ussd_stop($ussd_text);
    }
    
}

function register_vote($details,$phone,$polling_station_id, $res_poll, $conn){
    $sql = "SELECT election_results.id AS election_results_id, polling_stations.id AS polling_station_id, polling_stations.name, election_results.aspirant_id,
            aspirants.name, election_results.no_of_votes
        FROM polling_stations, election_results, aspirants
        WHERE polling_stations.id = election_results.polling_station_id
        AND election_results.aspirant_id = aspirants.id
        AND polling_stations.name = '". $res_poll[$details[0]-1] ."'";
    $result = mysqli_query($conn, $sql);

    $res = array();
    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while($row_aspirants = mysqli_fetch_assoc($result)) {
            array_push($res, $row_aspirants);
        }
    }
    if (count($details)==1)
    {   
        $ussd_text  = "Select aspitant \n";
        for ($i = 0; $i < count($res); $i++) {
            $ussd_text .= ($i + 1).". ". $res[$i]["name"] ." (Votes: ". $res[$i]["no_of_votes"] .") \n";
        }

        ussd_proceed($ussd_text);
    }
    if(count($details) == 2)
    {
        if (count($res) < (int)$details[1] || (int)$details[1] <= 0)
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

    else if(count($details) == 3)
    {   
        $votes=$details[2]; 
        // get current selection
        $ent = $res[$details[1]-1];

        if ((int)$details[2] < 0)
        {
            $ussd_text="Number of votes cannot be less than 0";
            ussd_stop($ussd_text);
        }
        else if(!is_numeric($details[2]))
        {
            $ussd_text="Votes must be a Numeric Number";
            ussd_stop($ussd_text);
        }
        else
        {
            $ussd_text="Confirm aspirants vote
            Aspirant: " . $ent . "\n" .
            "Total Votes: " . $votes. "\n\n " .
            "1. Accept \n 2. Cancel \n" ;
            ussd_proceed($ussd_text);
        }
        
    }
    else if(count($details) == 4)
    {
        ///////////////////////
        $votes=$details[2]; 
        $acceptDeny=$details[3]; 
        // get current selection
        $ent = $res[$details[1]-1];

        if($acceptDeny=="1")
        {  
            $query = mysqli_query($conn, "SELECT id FROM aspirants WHERE name = '". $ent ."'");
            $row = mysqli_fetch_array($query);
        
            //execute insert query   
            $sql = "UPDATE election_results SET no_of_votes='". $votes ."' WHERE id=".$res[$details[1]-1]["election_results_id"];

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