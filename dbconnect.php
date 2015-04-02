<?php
/* 
 * dbconnect.php
 * 
 * This file connects to the database or outputs an error message.
 * 
 * Using this file allows the mysql login credentials to change without
 * needing to change dozens of files.
 * 
 * Pre-Conditions: 
 * This should only be included by other PHP scripts, which are executed
 * by user navigating to them.
 * 
 * Post-Conditions:
 * The connection to the database is successful and subsequent queries
 * use this connection. Upon error, a message is output.
 * 
 * Author: Matthew Denninghoff
 * Date: 3/25/2015
 * 
 */
$user = "";
$pass = "";
$db = "ovs";
$msdatalink = mysql_connect ("127.0.0.1:3307", $user, $pass);
if ( ! $msdatalink)
        die ( "Couldn't connect" );
mysql_select_db( $db )
        or die ( "Couldn't connect to $db: ".mysql_error() );
// Clear these variables so the info cannot be leaked accidentally later.
unset($user);
unset($pass);
