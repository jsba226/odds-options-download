<?php
/**
 * downloadIssuerData.php
 * 
 * Author: Matthew Denninghoff
 * Date: 4/18/2015
 * 
 * Web form for choosing "Issuer Data" to download, which is data from
 * the `eqprice` and `eqmaster` tables.
 * 
 * 
 * Requires PHP version >= 5.2 for INPUT filtering.
 * 
 * 
 * Copyright 2015 Fishback Research and Management.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Include a file containing functions and constants shared between this script
// and other downloader scripts. Also attempts database connection.
require './inc.download.php';

const VOLATILITY_DIGITS_SHOW = 12;

// Start the session to setup cookies.
session_start();

// A stack of error messages to display to the user.
$errors = array();

////
////
//// Start error checking and parsing _GET requests.
//// (Never trust _GET or _POST raw values especially when the values are used
//// in database queries. MD.)
////
////

// Start Date, default to 30 days ago. Overwritten if user submitted good value.
$startDate = date(DATE_YYYYMMDD,  time() - 30*86400 );
parse_date_entry(INPUT_GET, 'startDate', $startDate, $errors);

// End Date, default to now. Overwritten if user submitted good value.
$endDate = date(DATE_YYYYMMDD);
parse_date_entry(INPUT_GET, 'endDate', $endDate, $errors);

// Parse and sanitize the ticker symbol.
$ticker = 'SPY';
if( isset($_GET['ticker']))
{
    // Remove anything that isn't alphanumeric, period, $, /, space, or dash.
    $ticker = ticker_sanitize($_GET['ticker']);    
}
// done parsing ticker.

////
////
//// done error checking and parsing _GET requests.
////
////


/*
 * Make a query string based on the user inputs, if any.
 */
$query_str = <<<ENDQSTR
SELECT eqp.date_, eqm.ticker, eqm.eqId, eqm.issuer, eqp.open_, eqp.high,
    eqp.low, eqp.close_, eqp.volume, eqp.bid, eqp.ask, eqp.totRtn
FROM eqprice eqp
LEFT JOIN eqmaster eqm ON eqp.eqid=eqm.eqid AND eqp.date_ >= eqm.startDate AND eqp.date_ <= eqm.endDate
WHERE eqm.ticker like '$ticker'
  AND eqp.date_ >= '$startDate' AND eqp.date_ <= '$endDate'
ENDQSTR;

// Set a limit on the result size. CSV downloads have different limit than
// preview data.
if( isset($_GET['submit']) && $_GET['submit'] == 'Download')
{
    $query_str .= " LIMIT " . MAX_DOWNLOAD_ROWS;
}
else
{
    $query_str .= " LIMIT " . MAX_PREVIEW_ROWS;
}

//
// Create the table object for either preview or download.
// 
$ResultTable = new MysqlResultTable();
$ResultTable->executeQuery($query_str);

$colNo=0;   // colNo avoids renumbering if order changes.
$ResultTable->set_column_name($colNo++, 'Pricing Date');
$ResultTable->set_column_name($colNo++, 'Ticker');
$ResultTable->set_column_name($colNo++, 'ID');
$ResultTable->set_column_name($colNo++, 'Issuer Name');
$ResultTable->set_column_name($colNo++, 'Open');
$ResultTable->set_column_name($colNo++, 'High');
$ResultTable->set_column_name($colNo++, 'Low');
$ResultTable->set_column_name($colNo++, 'Closing Price');
$ResultTable->set_column_name($colNo++, 'Volume');
$ResultTable->set_column_name($colNo++, 'Bid');
$ResultTable->set_column_name($colNo++, 'Ask');
$ResultTable->set_column_name($colNo++, 'Total Return');


// Format the date for each row.
for($row=0; $row < $ResultTable->get_num_rows(); $row++)
{
    $val = $ResultTable->get_value_at($row, 0);
    $ts = strtotime($val);
    $DT = new DateTime($val);
    $ResultTable->set_value_at($row, 0, $DT->format(DATE_YYYYMMDD) );
}


/*
 * Check if we're printing CSV data.
 * Possible EXIT point for script.
 */
if( isset($_GET['submit']) && $_GET['submit'] == 'Download')
{
    $ResultTable->csv_filename = 'issuerdata.csv';
    $ResultTable->print_csv_headers();
    $ResultTable->print_table_csv();
    
    // Stop the script so that only CSV output gets transmitted in the download.
    exit;
}
/*
 * done printing CSV data.
 */

/*
 * Print Page Top.
 */
?>
<!DOCTYPE html>
<html>
 <head>
  <title>OptionApps</title>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <link rel="stylesheet" type="text/css" href="download.css" />
  <style type="text/css"><?php
// Print the CSS for the data table.
$TDC = new TablesetDefaultCSS();
$TDC->set_css_tdOdd_value('background-color', null);
$TDC->set_css_td_value('background-color', '#444');
$TDC->set_tr_hover_value('background-color', null);
$TDC->print_css();
?></style>
 </head>
 <body>
  <?php
    // Show any error messages here.
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
  <img src="barlogo2.png" width="310px" height="100px" /><br>
   <div id="filters">
     <form action="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" method="GET">
      
      <fieldset style="position:relative;">
       <legend>Date Range</legend>
       Start <input type="text" name="startDate" value="<?php echo $startDate ?>" style="position:absolute; right:10px; width: 100px;" />
       <hr/>
       End <input type="text" name="endDate" value="<?php echo $endDate;?>" style="position:absolute; right:10px; width: 100px;" />
      </fieldset>
      
      <div class="padSmall">Ticker Symbol: <input class="ticker" type="text" name="ticker" value="<?php echo $ticker;?>"/></div>
      
      <a href="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" style="display:inline-block">Reset</a>
      <input type="submit" value="Preview" name="submit" />
    </form>
  </div>
<?php
/*
 * done printing page top.
 */
  
/*
 * Page Body.
 */

// Print from database.

// Print the hidden download form so that the preview is consistent
// with whatever the user downloads as CSV.
print_download_form();

echo '<div id="preview" style="width: 1500px;">'."\n";
echo '<h1>Issuer Data</h1>';
    

$ResultTable->caption = 'Preview';
$ResultTable->footer = 'Showing up to '.MAX_PREVIEW_ROWS.' rows.';
$ResultTable->set_column_width(3, '15%');

// Set column formats for the volatility columns: specify the number of digits to show.
//for($col=4, $n=$ResultTable->get_num_cols(); $col < $n; $col++)
//{
//    $ResultTable->set_column_format($col, '%0.'.VOLATILITY_DIGITS_SHOW.'f');
//}

$ResultTable->print_table_html();


// Print the raw query for debugging.
echo '<pre>'.$query_str.'</pre>';

echo "</div>\n";


/*
 * done printing page Body.
 */
  
/*
 * Print Page bottom.
 */
//  nothing here yet.
/*
 * done printing page bottom.
 */

// Debugging output to verify correctness of volType array. MD.
//echo '<pre>'. print_r($_GET,true) . print_r($volType,true) . '</pre>';

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
///////////////////////////////// Functions ////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

// Nothing here yet.

?>
 </body>
</html>
<?php
/*
James's pseudo code for grouping result data by date.

Show Matt the pivot needed for outputing data grouped by date

$array=array();
while ($mysql){
   if(empty($array[$my.date]){
	 $array[$my.date]=array();
   }
  $ARRAY[$MY.DATE][HV4]=5.0
 }
foreach ($array as $date=>$darray){
<td>$date</td><td>$darray['ticker']

} 
 */