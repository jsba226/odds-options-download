<?php
/*
 * filename: downloadChecker.php
 * Date:
 * Author:
 * Description:
 * 
 */

// How many downloads per period should be allowed.
const DOWNLOAD_FREQ = 50;

// How long of a period to check for downloads, in seconds.
const DOWNLOAD_PERIOD_SEC = 60;

// Start using sessions (cookies).
// (This function must be called before anything is output to the browser).
session_start();

// Connect to the database or die. MD.
// Commented out for testing non-database functions. MD.
//require('./dbconnect.php');


// Check if the user has specified a download and is permitted to download
// anything.
$userMayDownload = user_may_download(session_id());
if( isset($_GET['download']) && $userMayDownload )
{    
    // Tell the browser to expect a csv file
    // Note: try application/octet-stream if the browser doesn't try to save the file.
    // It works in Firefox 36 on Mac. MD.
    header('Content-Type: text/csv', TRUE);
    
    // Suggest a filename for the browser to use when prompting the user to
    // save.
    header('Content-Disposition: attachment; filename="optiondata.csv"');
    
    // Output sample CSV file for now.
?>
"joint_0","joint_1","joint_2","joint_3","joint_4","joint_5","joint_6","joint_7","joint_8","joint_9","Movement Speeds"
"30","30","30","30","30","30","0","0","0","0","100"
"60","60","60","60","60","","","","","","90"
"20","20","20","20","20","20","","","","","100"
"30","30","30","30","30","","","","","","100"
"40","40","40","40","40","","","","","","100"
"50","50","50","50","50","","","","","","100"
"60","60","60","60","60","","","","","","100"
<?php
// Note: nothing should follow the CSV output.
}
else
{
////////////////////////////////////////////////////////////////////////////////
//
// Output for HTML page: the html form or the warning page.
//
?>
<!DOCTYPE html>
<html>
  <head>
   <title>OptionApps</title>
   <meta charset="UTF-8">
   <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
 </head>
 <body>
<?php

// Debugging output:
// See what is in the _SESSION variable, (unique for each logged in client).
// Note: in the future, don't use this as the primary key for userID.
// A client may delete their cookies and get a new session id. MD.
echo '<p>Session ID:' . session_id() ."</p>\n";

// Should we show the warning page:
if( isset($_GET['download']) && !$userMayDownload )
{
?>
  <h3>Exceeded download limit.</h3>
  <p>Sorry, you have exceeded our reasonable limit on downloads. Please contact
   us to lift the restriction on your account.</p>
<?php    
}
// done if showing warning; else Show the normal page.
else
{
    ?>
  <form action="<?php echo basename(__FILE__); ?>" method="GET">
   
   <input type="submit" name="download" value="Download" />
  </form>
  
  <?php
    
}
// done else show normal page (not warning page).
?>
  
</body>
</html>
<?php
}
//
// done output HTML page.
////////////////////////////////////////////////////////////////////////////////

// @TODO: make a function that returns true if the user is not blocked and
// if the user has not exceeded their limit.
function user_may_download($userID)
{
    // @TODO: make sure userID doesn't have SQL injection attacks.
    
    // Sample query. MD.
//    $result = mysql_query("SELECT COUNT(*) FROM downloadTracker WHERE userId ='$userID' AND download_timestamp > NOW() - ".DOWNLOAD_PERIOD_SEC);
//    $row = mysql_fetch_row($result);
//    
//    if( $row[0] < DOWNLOAD_FREQ )
//    {
//        return true;
//    }
    
    // @TODO: Otherwise, flag the user's account.

    return false;
}
