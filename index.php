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
$phoneNumber = format_phone($_POST["phoneNumber"]);
$ussd_string = $_POST["text"];

//Get data from database
$query = mysqli_query($conn, "SELECT id FROM agents WHERE mobile_number = ". $phoneNumber);
$row = mysqli_fetch_array($query);

$sql_elective = "SELECT name FROM elective_posts";
$result_elective = mysqli_query($conn, $sql_elective);

$res_elective_posts = array();
if (mysqli_num_rows($result_elective) > 0) {
  // output data of each row
  while($row_elective_posts = mysqli_fetch_assoc($result_elective)) {
    array_push($res_elective_posts, $row_elective_posts["name"]);
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
    displayMenu($conn, $res_elective_posts); // show the home/first menu
}

if ($level>0)
{  
    $count = count($res);
    register_vote($ussd_string_explode,$phone,$row, $res_elective_posts, $conn);
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
function displayMenu($conn, $res_elective_posts)
{
    $count = count($res_elective_posts);
    if ($count > 0)
    {
        $ussd_text  = "Select elective post \n";
        for ($i = 0; $i < count($res_elective_posts); $i++) {
            $ussd_text .= ($i + 1).". ". $res_elective_posts[$i] ." \n";
        }

        ussd_proceed($ussd_text);
    }
    else
    {
        $ussd_text="You are not a registered Agent";
        ussd_stop($ussd_text);
    }
    
}

function register_vote($details,$phone,$row, $res_elective_posts, $conn){
    $sql_poll = "SELECT polling_station_id FROM agent_polling_stations WHERE agent_id = ". $row[0];
    $result_poll = mysqli_query($conn, $sql_poll);
    $res_poll = array();
    if (mysqli_num_rows($result_poll) > 0) {
      // output data of each row
      while($row_poll_agent = mysqli_fetch_assoc($result_poll)) {
        $query = mysqli_query($conn, "SELECT name FROM polling_centres WHERE id = ". $row_poll_agent["polling_station_id"]);
        $row_poll = mysqli_fetch_array($query);
        array_push($res_poll, $row_poll["name"]);
      }
    }

    $query = mysqli_query($conn, "SELECT id FROM elective_posts WHERE name = '". $res_elective_posts[$details[0]-1] ."'");
    $row_elective = mysqli_fetch_array($query);

    if (count($details)==1)
    {   
        $ussd_text  = "Select Polling Station \n";
        for ($i = 0; $i < count($res_poll); $i++) {
            $ussd_text .= ($i + 1).". ". $res_poll[$i] ." \n";
            // $ussd_text .= ($i + 1).". ". $row_elective[0] ." \n";
        }

        ussd_proceed($ussd_text);
    }

    $sql = "SELECT election_results.id AS election_results_id, polling_centres.id AS polling_center_id, polling_centres.name, election_results.aspirant_id,
            aspirants.name, aspirants.elective_post_id, election_results.no_of_votes
        FROM polling_centres, election_results, aspirants
        WHERE polling_centres.id = election_results.polling_centre_id
        AND election_results.aspirant_id = aspirants.id
        AND aspirants.elective_post_id = '". $row_elective[0] ."'
        AND polling_centres.name = '". $res_poll[$details[1]-1] ."'";
    $result = mysqli_query($conn, $sql);

    $res = array();
    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while($row_aspirants = mysqli_fetch_assoc($result)) {
            array_push($res, $row_aspirants);
        }
    }

    $sql_incidence = "SELECT id, name FROM incidence_types";
    $result_incidence = mysqli_query($conn, $sql_incidence);
        $res_incidence = array();
        if (mysqli_num_rows($result_incidence) > 0) {
            // output data of each row
            while($row_incidence_type = mysqli_fetch_assoc($result_incidence)) {
                array_push($res_incidence, $row_incidence_type);
            }
        }
    if (count($details)==2 and $res_elective_posts[$details[0]-1] == "Report Incident")
    {   
        $ussd_text  = "Incident type \n";
        for ($i = 0; $i < count($res_incidence); $i++) {
            $ussd_text .= ($i + 1).". ". $res_incidence[$i]["name"] ." \n";
        }

        ussd_proceed($ussd_text);
    }

    if (count($details)==2 and $res_elective_posts[$details[0]-1] != "Report Incident")
    {   
        $ussd_text  = "Select aspitant \n";
        for ($i = 0; $i < count($res); $i++) {
            $ussd_text .= ($i + 1).". ". $res[$i]["name"] ." (Votes: ". $res[$i]["no_of_votes"] .") \n";
        }

        ussd_proceed($ussd_text);
    }
    if(count($details) == 3 and $res_elective_posts[$details[0]-1] == "Report Incident")
    {
        $ussd_text="Write Incidence";
        ussd_proceed($ussd_text);

    }
    if(count($details) == 3 and $res_elective_posts[$details[0]-1] != "Report Incident")
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
    if(count($details) == 4 and $res_elective_posts[$details[0]-1] == "Report Incident")
    {
        // $query = mysqli_query($conn, "SELECT id FROM incidence_types WHERE name = ". $res_incidence[$details[2]-1]["id"]);
        // $row_incidence = mysqli_fetch_array($query);
        $sql = "INSERT INTO incidences (incidence_type_id, polling_centre_id, description, created, modified) 
            VALUES (".$res_incidence[$details[2]-1]["id"].", ".$row_elective[0].", '".$details[3]."', '".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."')";

        if (mysqli_query($conn, $sql)) 
        {
            $ussd_text="Successfully reported incidence!";
            ussd_stop($ussd_text);
        } else {
            $ussd_text="Error reporting incidence: Contact Admin";
            ussd_stop($ussd_text);
        }
    }
    else if(count($details) == 4 and $res_elective_posts[$details[0]-1] != "Report Incident")
    {   
        $votes=$details[3]; 
        // get current selection
        $ent = $res[$details[2]-1]["name"];

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
    else if(count($details) == 5)
    {
        ///////////////////////
        $votes = $details[3]; 
        $acceptDeny=$details[4]; 

        if($acceptDeny=="1")
        {          
            //execute insert query   
            $sql = "UPDATE election_results SET no_of_votes='". $votes ."' WHERE id=".$res[$details[2]-1]["election_results_id"];

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

function format_phone($unformatednumber)
{
    $formatednumber = "0";
    $unformatednumber = str_replace(" ", "", $unformatednumber);

    $int_number = (int)$unformatednumber;
    $number = (string)$int_number;

    $size = strlen($number);
    if ($size <= 10)
    {
        if ($number[0] == 0)
        {
            $number = substr($number,1);
            $formatednumber = "254". $number;
        }  
        else
        {
            $formatednumber = "254". $number;
        } 
    }  
    else if ($size == 12 && $number[3] != 0)
    {
        $formatednumber = $number;
    }
        
    return $formatednumber;
}
?>