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
// and other downloader scripts. Attempts database connection. Calls
//session_start(). Creates a download tracker object in $DLTracker.
require './inc.download.php';

const VOLATILITY_DIGITS_SHOW = 12;

// A stack of error messages to display to the user.
$errors = array();

////
////
//// Start error checking and parsing _GET requests.
//// (Never trust _GET or _POST raw values especially when the values are used
//// in database queries. MD.)
////
////

// Interval object used to subtract 30days from a dateTime object.
$Interval30Days = new DateInterval('P30D');

// Start Date: fetch from _GET or default to 30 days ago..
$DT_startDate;
try
{
    $DT_startDate = parse_dateEntry(INPUT_GET, 'startDate');
    
    // If the DateTime object wasn't created, then use a default.
    if( ! $DT_startDate )
    {
        // Default to 30 days ago.
        $DT_startDate = new DateTime();
        $DT_startDate->sub($Interval30Days);
    }
}
catch(Exception $e )
{
    $errors[] = $e->getMessage();
    
    // Default to 30 days ago.
    $DT_startDate = new DateTime();
    $DT_startDate->sub($Interval30Days);
}

// End Date, fetch from _GET or default to today.
$DT_endDate;
try
{
    $DT_endDate = parse_dateEntry(INPUT_GET, 'endDate');
    
    // If the DateTime object wasn't created, then use a default.
    if( ! $DT_endDate)
    {
        $DT_endDate = new DateTime();
    }
}
catch(Exception $e )
{
    $errors[] = $e->getMessage();
    
    // Default to now.
    $DT_endDate = new DateTime();
}

// Fetch the equity ID from _GET.
$eqID = null;
if( isset($_GET['eqID']))
{
    $eqID = (int)$_GET['eqID'];
}

// Parse and sanitize the ticker symbol, and lookup an eqID for it.
$ticker = null;
$matching_ticker_string = '';
if( isset($_GET['ticker']))
{
    // Remove anything that isn't alphanumeric, period, $, /, space, or dash.
    $ticker = ticker_sanitize($_GET['ticker']);

    // A search query for matching ticker symbols.
    $ticker_query = "SELECT eqm.ticker, eqm.eqId, max(eqm.issuer) "
        ."FROM eqmaster eqm "
        ."WHERE eqm.ticker like '".$ticker."' "
        ."GROUP BY eqm.ticker, eqm.eqId ";

    // If eqID is not set, then the user will need to choose an equity ID from
    // a list of matching names.
    if( $eqID == null )
    {
        $query = mysql_query($ticker_query);

        // If the query was good.
        if( $query )
        {
            $numrows = mysql_num_rows($query);
            
            // If there is only one equity matching the string, then 
            // use it and don't ask the user to choose it.
            if( $numrows == 1)
            {
                $row = mysql_fetch_row($query);
                
                // Redirect the page to one with the equity ID already chosen.
                header('Location: '.$_SERVER['SCRIPT_NAME']
                        .'?eqID='.$row[1]
                        .'&ticker='.$ticker
                        .'&startDate='.$DT_startDate->format(DATE_YYYYMMDD)
                        .'&endDate='.$DT_endDate->format(DATE_YYYYMMDD)
                        .'&submit=Preview');
                // Anything after header redirect isn't seen by the user, so exit.
                exit;
            }
            // done checking one result.
            // Handle no results.
            elseif( $numrows < 1)
            {
                $matching_ticker_string = '<p>No matching ticker symbols for '.$_GET['ticker'].'.</p>';
            }
            // Handle multiple results.
            else
            {
                $matching_ticker_string = '<p>Please choose an Issuer:</p>';

                $matching_ticker_string .= '<ul>'."\n";
                while( $row = mysql_fetch_row($query))
                {
                    $matching_ticker_string .= '<li><a href="'.basename($_SERVER['SCRIPT_NAME'])
                            .'?eqID='.$row[1]
                            .'&ticker='.$row[0]
                            .'&startDate='.$DT_startDate->format(DATE_YYYYMMDD)
                            .'&endDate='.$DT_endDate->format(DATE_YYYYMMDD)
                            .'&submit=Preview'
                            .'">'.$row[2].'</a></li>';
                }
                $matching_ticker_string .= '</ul>'."\n";
            }
            // done handling multiple results.
        }
        // The query was bad, so get an error string.
        else
        {
            $matching_ticker_string .= '<p>'.mysql_error().'</p>';
        }
        // done handling good or bad query.
    }
    // done handling eqID not being set.
}
// done handling ticker symbol being chosen.

////
////
//// done error checking and parsing _GET requests.
////
////


/*
 * Make a query string based on the user inputs, if any.
 */
// SQL dates are in the format Y-m-d or DATE_ISO8601.
$startDate_sql = $DT_startDate->format(DATE_YYYYMMDD);
$endDate_sql = $DT_endDate->format(DATE_YYYYMMDD);

$query_str = <<<ENDQSTR
SELECT eqp.date_, eqm.ticker, eqm.eqId, eqm.issuer, eqp.open_, eqp.high,
    eqp.low, eqp.close_, eqp.volume, eqp.bid, eqp.ask, eqp.totRtn
FROM eqprice eqp
LEFT JOIN eqmaster eqm ON eqp.eqid=eqm.eqid
    AND eqp.date_ between eqm.startDate AND eqm.endDate
WHERE eqm.eqId = '$eqID'
  AND eqp.date_ >= '$startDate_sql' AND eqp.date_ <= '$endDate_sql'
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

if( $eqID != null )
    $ResultTable->executeQuery($query_str);

$colNo=0;   // colNo avoids renumbering if order changes.
$ResultTable->set_column_name($colNo++, 'Date');
$ResultTable->set_column_name($colNo++, 'Ticker');
$ResultTable->set_column_name($colNo++, 'ID');
$ResultTable->set_column_name($colNo++, 'Issuer Name');
$ResultTable->set_column_name($colNo++, 'Open');
$ResultTable->set_column_name($colNo++, 'High');
$ResultTable->set_column_name($colNo++, 'Low');
$ResultTable->set_column_name($colNo++, 'Close');
$ResultTable->set_column_name($colNo++, 'Volume');
$ResultTable->set_column_name($colNo++, 'Bid');
$ResultTable->set_column_name($colNo++, 'Ask');
$ResultTable->set_column_name($colNo++, 'Total Return');


// Format the date for each row.
for($row=0; $row < $ResultTable->get_num_rows(); $row++)
{
    $val = $ResultTable->get_value_at($row, 0);
    $DT = new DateTime($val);
    $ResultTable->set_value_at($row, 0, $DT->format(DATE_MMDDYYYY_JS) );
}


/*
 * Check if we're printing CSV data.
 * Possible EXIT point for script.
 */
if( isset($_GET['submit']) && $_GET['submit'] == 'Download')
{
    // First see if the user's account has been flagged.
    // Prevent downloads if so.
    if( $DLTracker->isAccountFlagged() )
    {
        $errors[] = DLWARNING_OVERLIMIT;
    }
    // For unflagged accounts, see if the user has exceeded his/her download
    // limit. Allow download, if the user is not over limit.
    elseif( $DLTracker->underLimit() )
    {

        $ResultTable->csv_filename = 'issuerdata.csv';
        $ResultTable->print_csv_headers();
        $ResultTable->print_table_csv();

        // Record this download in the tracker.
        $DLTracker->recordNew();
        
        // Stop the script so that only CSV output gets transmitted in the download.
        exit;
    }
    // User has exceeded limit, so add warning to the error stack, and flag account.
    else
    {
        $DLTracker->flagAccount();
        $errors[] = DLWARNING_OVERLIMIT;
    }
    // done checking download limit.
}
/*
 * done printing CSV data.
 */

/*
 * Print Page Top.
 */
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <title>OptionApps</title>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <link rel="stylesheet" type="text/css" href="download.css" />
  <!--
  Start DatePicker includes.
  Code is from https://jqueryui.com/datepicker/#date-range
  -->
  <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
  <script src="//code.jquery.com/jquery-1.10.2.js"></script>
  <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  <script type="text/javascript">
  $(function() {
    $( "#startDate" ).datepicker({
      defaultDate: new Date('<?php echo $DT_startDate->format(DATE_MMDDYYYY_JS); ?>'),
      changeMonth: true,
      numberOfMonths: 3,
      onClose: function( selectedDate ) {
        $( "#endDate" ).datepicker( "option", "minDate", selectedDate );
      }
    });
    $( "#endDate" ).datepicker({
      defaultDate: new Date('<?php echo $DT_endDate->format(DATE_MMDDYYYY_JS); ?>'),
      changeMonth: true,
      numberOfMonths: 3,
      onClose: function( selectedDate ) {
        $( "#startDate" ).datepicker( "option", "maxDate", selectedDate );
      }
    });
  });
  </script>
  <!-- end DatePicker includes. -->
  
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
       Start <input type="text" id="startDate" name="startDate" value="<?php echo $DT_startDate->format(DATE_MMDDYYYY_JS); ?>" style="position:absolute; right:10px; width: 100px;" />
       <hr/>
       End <input type="text" id="endDate" name="endDate" value="<?php echo $DT_endDate->format(DATE_MMDDYYYY_JS);?>" style="position:absolute; right:10px; width: 100px;" />
      </fieldset>
      <?php
      // If the user chose an eqID, then put a hidden form element to avoid
      // re-choosing it whenever date range changes but ticker does not.
      // Assume that $_GET['ticker'] is set when eqID is set.
      // Don't show an input box, just show text with the ticker. Otherwise,
      // they could choose a new symbol, but eqID would still point to the 
      // old symbol.
      if(isset($_GET['eqID']))
      {
          echo '<input type="hidden" name="eqID" value="'.$_GET['eqID'].'" />'."\n";
          echo '<div class="padSmall">Ticker Symbol: ' . $ticker . '</div>';
          echo '<input type="hidden" name="ticker" value="'.$ticker.'" />'."\n";
      }
      // No eqID was set, so show an input box for ticker name.
      else
      {
          echo '<div class="padSmall">Ticker Symbol:'
          . '<input class="ticker" type="text" name="ticker" value="'
          . ($ticker != null ? $ticker : ''). '"/></div>';
      }
      ?>
      <a class="button" href="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" style="display:inline-block">Reset</a>
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

// Only print table data if the user selected a valid eqID.
if( $eqID != null )
{
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
//    echo '<pre>'.$query_str.'</pre>';

    echo "</div>\n";
}
// If eqID is not set but ticker is set, then user entered a ticker symbol
// to find. So show matching ticker symbols
else if( $ticker != null )
{
    echo $matching_ticker_string;
}
// done handling chosen ticker.

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

// Debugging output.
//echo '<pre>SESSION '.print_r($_SESSION,true).'</pre>';
//echo '<pre>GET '.print_r($_GET,true).'</pre>';

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