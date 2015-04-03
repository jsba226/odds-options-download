<?php
/**
 * downloadSurfaceData.php
 * 
 * Author: Matthew Denninghoff
 * Date: 3/31/2015
 * 
 * Web form for choosing "Surface Data" to download:
 * 1) Download Historical Volatility.
 *    Input: User selects term, date range, ticker symbol.
 *    Output: Historical Volatility data from `eqhvol` table.
 * 
 * 2) Download Implied Volatility.
 *    Input: User selects term, date range, ticker symbol, Moneyness.
 *    Output: Implied Volatility data from `ivcmpr` table.
 */

// Date format to use in this script when printing timestamps as formatted dates.
// YYYY-mm-dd: example: 2015-04-02 means April 2, 2015.
const DATE_YYYYMMDD = 'Y-m-d';

// Default timezone to use in this script. Set default timezone to avoid PHP
// warnings output to browser (or log file).
const TIMEZONE_DEFAULT = 'America/New_York';

// Regular Expression used to verify format of dates submitted via GET requests.
const PREG_DATE_YYYYMMDD = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/';

// Format dates from the database with this style.
const SQL_DATE_FORMAT = '%Y-%m-%d';

// Start the session to setup cookies.
session_start();

// Connect to the database or die. MD.
require('./dbconnect.php');

// A stack of error messages to display to the user.
$errors = array();

////////////////////////////////////////////////////////////////////////////////
// Error Checking and Parsing _GET requests.

// Extract dataType from _GET, or default to hvol.
$dataType = isset($_GET['dataType']) && $_GET['dataType'] == 'ivol' ? 'ivol' : 'hvol';

//// Extract termRadio from _GET or default to 1.
//// This is the Short Term value.
//$termRadio = 1;
//if( isset($_GET['termRadio']))
//{
//    // Ensure that the values are integers between 1 and 6.
//    if( (int)$_GET['termRadio'] >= 1 && (int)$_GET['termRadio'] <= 6)
//    {
//        $termRadio = (int)$_GET['termRadio'];
//    }
//}
//// done getting short term.
//
//// Long Term, default to 2.
//$termRadioLong = 2;
//if( isset($_GET['termRadioL']))
//{
//    // Ensure that the values are integers between 2 and 6.
//    if( (int)$_GET['termRadioL'] >= 2 && (int)$_GET['termRadioL'] <= 6)
//    {
//        $termRadioLong = (int)$_GET['termRadioL'];
//    }
//}
//// done getting long term.

// Get the volatility type.
$volType = 14; // default to 2 week historical volatility.
if( isset($_GET['volType']))
{
    if( (int)$_GET['volType'] > 13 && (int)$_GET['volType'] <= 26 )
    {
        $volType = (int)$_GET['volType'];
    }
}
// done getting the volatility type.

// (Certain installations of PHP print warnings if default timezone is not set.)
date_default_timezone_set(TIMEZONE_DEFAULT);

// Start Date, default to 30 days ago.
$startDate = date(DATE_YYYYMMDD,  mktime() - 30*86400 );
if( isset($_GET['startDate']))
{
    if(preg_match(PREG_DATE_YYYYMMDD, $_GET['startDate']))
    {
        if(strtotime($_GET['startDate']) !== false)
        {
            $startDate = $_GET['startDate'];
        }else{
            $errors[] = 'The startDate you submitted, '.$_GET['startDate'].', is not a real date.';
        }
    }
    else
    {
        $errors[] = 'The startDate submitted must be in the format YYYY-mm-dd.';
    }
}

// End Date, default to now.
$endDate = date(DATE_YYYYMMDD);
if( isset($_GET['endDate']))
{
    if(preg_match(PREG_DATE_YYYYMMDD, $_GET['endDate']))
    {
        if(strtotime($_GET['endDate']) !== false)
        {
            $endDate = $_GET['endDate'];
        }else{
            $errors[] = 'The endDate you submitted, '.$_GET['endDate'].', is not a real date.';
        }
    }
    else
    {
        $errors[] = 'The endDate submitted must be in the format YYYY-mm-dd.';
    }
    
}

$ticker = 'SPY';
if( isset($_GET['ticker']))
{
    // @TODO: check this input for bad data, such as SQL injection.
    $ticker = $_GET['ticker'];
}

// done error Checking and Parsing _GET requests.
////////////////////////////////////////////////////////////////////////////////

/*
 * Print Page Head.
 */
?>
<!DOCTYPE html>
<html>
 <head>
  <title>OptionApps</title>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <style type="text/css">
   body {
       color: white;
       /*background: linear-gradient(#3c3c3c, #111) repeat scroll 0 0 #111;*/
       /*background: linear-gradient(#444, #222) repeat scroll 0 0 #222;*/
       background-color: #444;
       font-family: Helvetica,Arial,sans-serif;
   }
   ul#errors{
       color:red;
       background:white; 
   }
   
   a { color: white; padding: 0.55em 11px 0.5em;
       background: linear-gradient(#444, #222) repeat scroll 0 0 #222;
   }
   
   .horzspace{display:inline-block;width:20px; border:none;}
   
   .padSmall{padding:10px;}
   
   input.ticker{ background:linear-gradient(#fffadf, #fff3a5) repeat scroll 0 0 #fff9df;}
      
   label{padding-right: 10px; border-radius: 10px;}
   label:hover {
       color: #eee; background-color: #222;
   }
   
  </style>
 </head>
 <body>
  <?php
    // Show any error messages
    if( count($errors) > 0){
        echo '<ul id="errors">';
        for($i=0, $n=count($errors); $i<$n; $i++)
        {
            echo '<li>'. $errors[$i] ."</li>\n";
        }
        echo "</ul>\n";
    } 
  // done printing any error messages.
  ?>
   <div>
     <form action="<?php echo basename(__FILE__); ?>" method="GET">
      
      <fieldset>
       <legend>Data Type</legend>
      <label><input type="radio" name="dataType" value="hvol" <?php echo $dataType == 'hvol' ? 'checked="true"' : ''; ?>/>
       Historical Volatility</label><br>
      <label><input type="radio" name="dataType" value="ivol" <?php echo $dataType == 'ivol' ? 'checked="true"' : ''; ?> />
      Implied Volatility</label>
      </fieldset>
        
      <fieldset>
       <legend>Volatility Type</legend>
      <?php
      // Code adapted from chart.php lines 261-281.
      // @TODO: make this a function.
    $mysqlq="select volType, name, details from eqhvolmap where volType > '13'"; //Only adjusted volatilities
    $mysql=mysql_query($mysqlq);

    while($row=mysql_fetch_row($mysql)) {
       $id=$row[0];
       $d1=preg_split("/,/",$row[2]); // split description at the comma so we can remove Textbook Hist volatility,"
       $name=$d1[1];
       
       // If volatility type is 1 month or 1 year.
       if($id == 15 || $id == 22) {
          $name=preg_replace("/m/", " month", $name);
          $name=preg_replace("/w/", " week", $name); // this line isn't necessary. md.
          $name=preg_replace("/y/", " year", $name);
          $name=preg_replace("/d/", " days", $name);
       } else {
          $name=preg_replace("/m/", " months", $name);
          $name=preg_replace("/w/", " weeks", $name);
          $name=preg_replace("/y/", " years", $name);
          $name=preg_replace("/d/", " days", $name);
       }
       //Trim out the (X days)
       $name=preg_replace('/ \W.*\W/', "", $name);
       
       echo '<label><input type="radio" name="volType" value="'.$id.'"';
       echo $id == $volType ? ' checked="checked"' : '';
       echo ">$name</label><br/>\n";
    }
    // done fetching volatility types.
      ?>
       </fieldset>
      
      <!--
      <fieldset>
       <legend>Short Term</legend>
       <input type="radio" name="termRadio" value="1" <?php echo $termRadio == 1 ? 'checked="true"' : ''; ?>/>
       1 Month<br>
       <input type="radio" name="termRadio" value="2" <?php echo $termRadio == 2 ? 'checked="true"' : ''; ?>/>
       2 Month<br>
       <input type="radio" name="termRadio" value="3" <?php echo $termRadio == 3 ? 'checked="true"' : ''; ?>/>
       3 Month<br>
       <input type="radio" name="termRadio" value="4" <?php echo $termRadio == 4 ? 'checked="true"' : ''; ?>/>
       4 Month<br>
       <input type="radio" name="termRadio" value="5" <?php echo $termRadio == 5 ? 'checked="true"' : ''; ?>/>
       5 Month<br>
       <input type="radio" name="termRadio" value="6" <?php echo $termRadio == 6 ? 'checked="true"' : ''; ?>/>
       6 Month<br>
      </fieldset>
      
      <fieldset>
       <legend>Long Term</legend>
       <input type="radio" name="termRadioL" value="2" <?php echo $termRadioLong == 2 ? 'checked="true"' : ''; ?>/>
       2 Month<br>
       <input type="radio" name="termRadioL" value="3" <?php echo $termRadioLong == 3 ? 'checked="true"' : ''; ?>/>
       3 Month<br>
       <input type="radio" name="termRadioL" value="4" <?php echo $termRadioLong == 4 ? 'checked="true"' : ''; ?>/>
       4 Month<br>
       <input type="radio" name="termRadioL" value="5" <?php echo $termRadioLong == 5 ? 'checked="true"' : ''; ?>/>
       5 Month<br>
       <input type="radio" name="termRadioL" value="6" <?php echo $termRadioLong == 6 ? 'checked="true"' : ''; ?>/>
       6 Month<br>
      </fieldset>-->

      <fieldset>
       <legend>Date Range</legend>
       Start <input type="text" name="startDate" value="<?php echo $startDate ?>"/>
       <hr class="horzspace"/>
       End <input type="text" name="endDate" value="<?php echo $endDate;?>"/>
      </fieldset>
      
      <div class="padSmall">Ticker Symbol: <input class="ticker" type="text" name="ticker" value="<?php echo $ticker;?>"/></div>
      
      <a href="<?php echo basename(__FILE__); ?>" style="display:inline-block">Reset</a> <input type="submit" value="Submit" />
    </form>
  </div>
<?php
/*
 * done printing page head.
 */
  
//
// Show a preview of the data:
// Code from chart.php lines 261-281.

// Note: Does term really mean volatility type? No. His requirements specify
//       that the user chooses "term." However, the query asks for a volatility
//       type. Also, there is a volatility type on the chart menu that matches 
//       what this query expects.
$mysqlq_str="SELECT eqp.date_, eqp.vol \n"
    . "FROM eqhvol AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND eqm.startDate >= '$startDate' and eqm.endDate <= '$endDate' \n"
    . "WHERE eqm.ticker like '$ticker' AND eqp.date_<= now() \n"
    . "  AND eqp.volType=$volType\n";


// Query is adapted from chart.php Line 375.
// @TODO: use constant for date format.
// @TODO: see if this data is correct. Need to ask about it, because 
// a query will return results even if there is no hvol.vol data. (because of left join).
// changing to normal join fixes that. data starts whenever hvol data starts.
$mysqlq_str="SELECT eqm.ticker, eqp.close_, hvol.vol, date_format(eqp.date_, '".SQL_DATE_FORMAT."')\n"
. "FROM eqprice AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
. "  JOIN eqhvol AS hvol ON hvol.eqId=eqp.eqId AND hvol.volType=".$volType." AND hvol.date_=eqp.date_\n"
. "WHERE eqm.ticker like '$ticker' AND eqp.date_>='$startDate' AND eqp.date_<='$endDate'\n"
. "ORDER BY eqp.date_ ASC LIMIT 10";

echo '<pre>'.$mysqlq_str.'</pre>';

//Now, run query to get data
//$mysqlq="SELECT eqp.date_, eqp.vol FROM eqhvol AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND '$currentDate' between eqm.startDate and eqm.endDate WHERE eqm.ticker like '$ticker' AND eqp.date_<='$currentDate' AND eqp.volType=$v";
$mysqlq=mysql_query($mysqlq_str);
if($mysqlq)
{
    echo '<pre>';
    while($row=mysql_fetch_array($mysqlq))
    {
        printf("%s %s %s %s\n", $row[0], $row[1], $row[2], $row[3]);
    }
    echo '</pre>';
}
else
{
    echo '<p>'.mysql_error()."</p>\n";
}

//$mysqlq="select * from eqhvolmap where volType = $v";
//$mysql=mysql_query($mysqlq);
//$row=mysql_fetch_array($mysql);
//$details=$row[2];
//$d1=preg_split("/,/",$details); 
//$name=$d1[1];
//$name=preg_replace("/w/", "week", $name);
//$name=preg_replace("/y/", "years", $name);
//$name=preg_replace("/d/", "days", $name);
//$volName=$name;


  
/*
 * Print Page bottom.
 */
?>
 </body>
</html>
