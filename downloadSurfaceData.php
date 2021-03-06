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
 * 
 * Requires PHP version >= 5.2 for INPUT filtering.
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

// Volatility types, used in html form and parsing input.
const VOLTYPE_HIST = 0;    // historical volatility.
const VOLTYPE_IMPL = 1;    // implied volatility.

const VOLATILITY_DIGITS_SHOW = 12;

// List of possible moneyness values to show filters for.
// The order of the elements in this array determine which order the filters
// are displayed; e.g. array(95,90,85) shows 95, then 90, then 85.
// (Arrays cannot be constants, so this is a variable. MD.)
$MONEYNESS_VALUES = array(50,60,70,75,80,85,90,95,100,105,110,115,120,125,130,140,150);


// A stack of error messages to display to the user.
$errors = array();

////
////
//// Start error checking and parsing _GET requests.
//// (Never trust _GET or _POST raw values especially when the values are used
//// in database queries. MD.)
////
////

// Extract dataType from _GET, or default to hvol.
$dataType = isset($_GET['dataType']) && $_GET['dataType'] == VOLTYPE_IMPL ? VOLTYPE_IMPL : VOLTYPE_HIST;


// Get the volatility type.
$volTypes = array();
if( isset($_GET['volType']))
{
    // volType fields are checkboxes that become an array within _GET, so
    // loop over that array.
    foreach($_GET['volType'] as $val)
    {
        // Make sure the volType is either an adjusted historical volatility.
        if( $dataType == VOLTYPE_HIST && (int)$val > 13 && (int)$val <= 26 )
        {
            $volTypes[] = (int)$val;
        }
        // Or the volType is a negative number.
        else if( $dataType == VOLTYPE_IMPL && (int)$val < 0 )
        {
            $volTypes[] = (int)$val;
        }
    }
    // done looping over each volType checkbox value.
}
// done getting the volatility type.

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

// Default moneyness/strike to 100. Only allow values in $MONEYNESS_VALUES.
$moneyness = 100;
if( isset($_GET['moneyness']))
{
    if(in_array((int)$_GET['moneyness'], $MONEYNESS_VALUES))
    {
        $moneyness = (int)$_GET['moneyness'];
    }
}
// done parsing moneyness.

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

// Get the mapping of historical volatility integer values to string names.
$hvolmap = get_hvolmap();

// Create the mapping of Implied Volatility DB result values to string names.
// This array is used to generate the list of implied volatility checkboxes.
// It is also used to make descriptive column headers.
$ivolmap = array(
    '1' => '1 Month',
    '2' => '2 Months',
    '3' => '3 Months',
    '4' => '4 Months',
    '5' => '5 Months',
    '6' => '6 Months',
    '9' => '9 Months',
    '12' => '12 Months',
    '18' => '18 Months',
    '24' => '24 Months' );

/*
 * Decide which query to use for both the preview and the CSV download.
 */
$query_str;
$startDate_sql = $DT_startDate->format(DATE_YYYYMMDD);
$endDate_sql = $DT_endDate->format(DATE_YYYYMMDD);
if( $dataType == VOLTYPE_HIST )
{

    
    // @TODO: Don't use date_format in SQL. James said their PHP has more
    // resources than their MySQL to handle processing in PHP.
    
    // @TODO: Don't use subqueries for hvol data.

    // SQL query is adapted from query in chart.php Line 351.
    $query_str="SELECT eqp.date_ AS eqp_date,\n"
    . " eqm.ticker, 'HV' as Indicator, eqp.close_"
    /* Generate the subqueries for each user-specified volatility type. */        
    . vol_sql_generate()

    . "\nFROM eqprice AS eqp\n"
    . "LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
    . "  AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
    . "WHERE eqm.ticker like '$ticker'\n"
    . "  AND eqp.date_>='$startDate_sql'\n"
    . "  AND eqp.date_<='$endDate_sql'\n"
    . "ORDER BY eqp.date_ " ;
}
else
{
    // Take the selected values of IV expiry and make them positive.
    // IV expiry values are submitted in the form with negative integers
    // to distinguish them from Historical Volatility expiration values.
    
    // @TODO: Don't use date_format in SQL. James said their PHP has more
    // resources than their MySQL to handle processing in PHP.
    
    // @TODO: Don't use subqueries for ivol data.
    
    // SQL query adapted from chart.php line 349.
    $query_str="SELECT eqp.date_ AS PricingDate,\n"
    . "  eqm.ticker AS Ticker, 'IV' as Indicator, '$moneyness' as Moneyness,\n"
    . "  eqp.close_ "
    /* Generate the subqueries for each user-specified volatility type. */        
    . vol_sql_generate()
    . "\nFROM eqprice AS eqp\n"
    . "LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
    . "  AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
    . "WHERE eqm.ticker like '$ticker'\n"
    . "  AND eqp.date_ <= '$endDate_sql'\n"
    . "  AND eqp.date_ >= '$startDate_sql'\n"
    . "ORDER BY eqp.date_ ";
}
// done deciding which query to use.

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
        $query_str .= "LIMIT ".MAX_DOWNLOAD_ROWS;

        $ResultTable = new MysqlResultTable();
        $ResultTable->executeQuery($query_str);

        // Set column names depending on the volatility type.
        if( $dataType == VOLTYPE_HIST )
        {
            $ResultTable->set_column_name(0, 'Date');
            $ResultTable->set_column_name(1, 'Ticker');
            $ResultTable->set_column_name(2, 'Indicator');
            $ResultTable->set_column_name(3, 'Close');

            replace_headers_by_map($hvolmap, 4, "vol", $ResultTable);

        }
        else
        {
            $ResultTable->set_column_name(0, 'Date');
            $ResultTable->set_column_name(4, 'Close');

            replace_headers_by_map($ivolmap, 5, "vol", $ResultTable);
        }
        
        // Format the date of the first cell in each row.
        for($row=0; $row < $ResultTable->get_num_rows(); $row++)
        {
            $val = $ResultTable->get_value_at($row, 0);
            $DT = new DateTime($val);
            $ResultTable->set_value_at($row, 0, $DT->format(DATE_MMDDYYYY_JS) );
        }

        $ResultTable->csv_filename = 'surfacedata.csv';
        
        // Write the output to the browser.
        $ResultTable->print_csv_headers();
        $ResultTable->print_table_csv();

        // Record this download in the tracker.
        $DLTracker->recordNew();
        
        // Stop the script so that only CSV output gets transmitted.
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
<html>
 <head>
  <title>OptionApps</title>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <link rel="stylesheet" type="text/css" href="download.css" />
  <style type="text/css"><?php
// At page load, hide either the Hist. Volatility filters or the Implied
// volatility filters, depending $dataType.
echo $dataType == VOLTYPE_IMPL ? '#hvolFS{display:none}' : '#ivolFS{display:none}';

// Print the CSS for the data table.
$TDC = new TablesetDefaultCSS();
$TDC->set_css_tdOdd_value('background-color', null);
$TDC->set_css_td_value('background-color', '#444');
$TDC->set_tr_hover_value('background-color', null);
$TDC->print_css();
?>
  </style>
  <script type="text/javascript">
    /**
     * Show or hide the historical or implied volatility filters.
     * This function checks the value of the dataType checkbox and hides
     * one set of filters and shows the other set.
     * 
     * @returns {undefined}
     */
    function showHvol()
    {
        var hvolChk = document.getElementById('dataTypeHvolChk');
        var hvolFS = document.getElementById('hvolFS');
        var ivolFS = document.getElementById('ivolFS');

        // hvol has been checked.
        if( hvolChk.checked )
        {
            hvolFS.style.display = 'block';
            ivolFS.style.display = 'none';
        }
        else
        {
            hvolFS.style.display = 'none';
            ivolFS.style.display = 'block';
        }
    }
  </script>
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
      
      <fieldset>
       <legend>Data Type</legend>
       <label><input type="radio" id="dataTypeHvolChk" name="dataType" <?php echo 'value="'.VOLTYPE_HIST.'"'. ($dataType == VOLTYPE_HIST ? ' checked="true"' : ''); ?> onchange="showHvol()" />
       Historical Volatility</label><br>
       <label><input type="radio" id="dataTypeIvolChk" name="dataType" <?php echo 'value="'.VOLTYPE_IMPL.'"'.($dataType == VOLTYPE_IMPL ? ' checked="true"' : ''); ?>  onchange="showHvol()" />
       Implied Volatility</label>
      </fieldset>
        
      <div id="hvolFS">
      <fieldset>
       <legend>Historical Volatility</legend>
      <?php
    
    // Print checkboxes for each historical volatility type.
    foreach( $hvolmap as $id => $name )
    {
        echo '<label><input type="checkbox" name="volType[]" value="'.$id.'"';
        echo in_array($id, $volTypes) ? ' checked="checked"' : '';
        echo ">HV $name</label><br/>\n";
    }
      ?>
       </fieldset>
      </div>
      
      <div id="ivolFS">
      <fieldset>
       <legend>Implied Volatility</legend>
       <?php
       echo '<ul class="inputlist">';
       
       foreach( $ivolmap as $key => $val )
       {
           // The submitted IV values are negative numbers, so we search
           // for a negative value in the volTypes array.
           $negated = $key * -1;
           
           echo '<li><label><input type="checkbox" name="volType[]" value="'.$negated.'"';
           echo in_array($negated, $volTypes) ? ' checked="checked"' : '';
           echo '>'.$val.'</label></li>'."\n";
       }
       
       echo "</ul>\n";
       ?>
      </fieldset>
       <fieldset>
        <legend>Moneyness</legend>
        <?php
        // Display all moneyness values from the array.
        echo '<ul class="inputlist">';
        foreach( $MONEYNESS_VALUES as $val )
        {
            echo '<li><label><input type="radio" name="moneyness" value="'.$val.'"';
            echo $moneyness == $val ? ' checked="checked"' : '';
            echo '>'.$val.'%</label></li>'."\n";
        }
        echo "</ul>\n";
        ?>
       </fieldset>
      </div>

      <fieldset style="position:relative;">
       <legend>Date Range</legend>
       Start <input type="text" id="startDate" name="startDate" value="<?php echo $DT_startDate->format(DATE_MMDDYYYY_JS); ?>" style="position:absolute; right:10px; width: 100px;" />
       <hr/>
       End <input type="text" id="endDate" name="endDate" value="<?php echo $DT_endDate->format(DATE_MMDDYYYY_JS); ?>" style="position:absolute; right:10px; width: 100px;" />
      </fieldset>
      
      <div class="padSmall">Ticker Symbol: <input class="ticker" type="text" name="ticker" value="<?php echo $ticker;?>"/></div>
      
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

// Print from database if the user selected at least one term name.
if( count($volTypes) > 0 )
{
    // Print the hidden download form so that the preview is consistent
    // with whatever the user downloads as CSV.
    print_download_form();
        
    $query_str .= "LIMIT ".MAX_PREVIEW_ROWS;
    
    // Create the Table object and run the query.
    $ResultTable = new MysqlResultTable();
    $ResultTable->executeQuery($query_str);

    // Set the table caption and footer.
    $ResultTable->caption = 'Preview';
    $ResultTable->footer = 'Showing up to '.MAX_PREVIEW_ROWS.' rows.';
    
    echo '<div id="preview">'."\n";
    echo '<h1>Surface Data</h1>';
    
    // Set column headers depending on the volatility type.
    if( $dataType == VOLTYPE_HIST )
    {
        $ResultTable->set_column_name(0, 'Date');
        $ResultTable->set_column_name(1, 'Ticker');
        $ResultTable->set_column_name(2, 'Indicator');
        $ResultTable->set_column_name(3, 'Close');
        
        replace_headers_by_map($hvolmap, 4, "vol", $ResultTable);
        
        // Set column formats for the volatility columns: specify the number of digits to show.
        for($col=4, $n=$ResultTable->get_num_cols(); $col < $n; $col++)
        {
            $ResultTable->set_column_format($col, '%0.'.VOLATILITY_DIGITS_SHOW.'f');
        }    
    }
    else
    {
        $ResultTable->set_column_name(0, 'Date');
        $ResultTable->set_column_name(4, 'Close');

        replace_headers_by_map($ivolmap, 5, "vol", $ResultTable);
        
        // Set column formats for the volatility columns: specify the number of digits to show.
        for($col=5, $n=$ResultTable->get_num_cols(); $col < $n; $col++)
        {
            $ResultTable->set_column_format($col, '%0.'.VOLATILITY_DIGITS_SHOW.'f');
        }
    }
    // done setting column headers.
    
    // Format the date of the first cell in each row.
    for($row=0; $row < $ResultTable->get_num_rows(); $row++)
    {
        $val = $ResultTable->get_value_at($row, 0);
        $DT = new DateTime($val);
        $ResultTable->set_value_at($row, 0, $DT->format(DATE_MMDDYYYY_JS) );
    }
    
    $ResultTable->print_table_html();
    
    // Print the raw query for debugging.
//    echo '<pre>'.$query_str.'</pre>';
    
    echo "</div>\n";
}

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

////////////////////////////////////////////////////////////////////////////////
/**
 * Read each Historical Volatility type from the database. Only read the
 * adjusted volatilities (volType > 13).
 * 
 * If the query fails, then $errors gets a descriptive error.
 * 
 * Code adapted from chart.php lines 272-281, written by Chris Moore,
 * Fishback Management and Research 12/20/12.
 * 
 * Post-Conditions: If the database query fails, then an empty array is
 *   returned, and an error message is pushed onto $errors.
 * 
 * @global array $errors
 * @return array
 * Returns an associative array with volType for array keys, and a formatted 
 * name as the array values.
 */
function get_hvolmap()
{
    global $errors;
    
    $harray = array();
    
    $mysqlq="select volType, name, details from eqhvolmap where volType > '13'";
    $mysql=mysql_query($mysqlq);
    if( $mysql)
    {
    
    // Read each volatility type and reformat the description.
    while($row=mysql_fetch_row($mysql)) {
       $id=$row[0];
       // split description at the comma so we can remove Textbook Hist volatility,"
       $d1=preg_split("/,/",$row[2]); 
       $name=$d1[1];
       
       // If volatility type is 1 month or 1 year, use singular month/year.
       if($id == 15 || $id == 22) {
          $name=preg_replace("/m/", " month", $name);
          $name=preg_replace("/y/", " year", $name);
       } else {
          $name=preg_replace("/m/", " months", $name);
          $name=preg_replace("/w/", " weeks", $name);
          $name=preg_replace("/y/", " years", $name);
          $name=preg_replace("/d/", " days", $name);
       }
       //Trim out the (X days)
       $name=preg_replace('/ \W.*\W/', "", $name);
       $harray[$id] = trim($name);
    }
    // done reading each eqhvolmap row.
    }
    else
    {
        $errors[] = mysql_error();
    }
    return $harray;
}
// end get_hvolmap().


/**
 * Create subquery strings for each selected volatility type.
 * The resulting string is put inside another query.
 * 
 * NOTE: This is a temporary solution. It would be better to 
 * run a separate query and then join the data with the other query in PHP,
 * based on the system constraints.
 * 
 * @global array $volTypes
 * @global array $moneyness
 * @global int $dataType
 * @return string
 */
function vol_sql_generate()
{
    global $volTypes, $moneyness, $dataType;
    
    $retstr = "";
    
    // Only generate subqueries if the user selected vol Types.
    if( count($volTypes) > 0)
    {
        if( $dataType == VOLTYPE_HIST )
        {
            // For each selection, query the database for the historical volatility data.
            foreach( $volTypes as $volType)
            {
                $retstr .= ",\n  ("
                . "SELECT hvol.vol FROM eqhvol hvol "
                . "WHERE hvol.eqId=eqp.eqId AND hvol.volType = '$volType' "
                . "AND hvol.date_=eqp.date_ ) as vol$volType";
            }
        }
        else if( $dataType == VOLTYPE_IMPL)
        {            
            // For each selection, query the database for the historical volatility data.
            foreach( $volTypes as $volType)
            {
                // Take the submitted form value, which is negatives for IVOL,
                // and make it positive.
                $exp = $volType * -1;
                
                $retstr .= ",\n  ("
                . "SELECT iv.ivMid FROM ivcmpr AS iv "
                . "WHERE iv.eqId=eqp.eqId AND iv.expiry = '$exp' "
                . "AND iv.date_=eqp.date_ "
                . "AND iv.strike='$moneyness' "
                . " ) as vol$exp";
            }
        }
        // end if type is HIST or IMPL.
    }
    // end if count volTypes > 0.
    
    return $retstr;
}
// end hvol_sql_generate().

/**
 * Replace column headers in a TableSet object with headers in the array, 
 * $map.
 * 
 * Array keys in $map are compared against column names. If a column
 * name sans prefix matches the array key, then the array value for that key
 * replaces the column name. I use this to replace volatility column names,
 * which are returned with integer values, and instead use descriptive titles.
 * 
 * @param array $map
 * @param int $startCol
 * @param string $colPrefix
 * @param TableSet $Tableset
 * @return boolean
 */
function replace_headers_by_map($map, $startCol, $colPrefix, $Tableset)
{
    if( !is_array($map))
    {
        return false;
    }
    
    // Replace column headers with descriptive ones.
    // For each column after closing price.
    for($col=$startCol, $n=$Tableset->get_num_cols(); $col < $n;$col++)
    {
        $cname = $Tableset->get_col_name($col);        
        $cname = str_replace($colPrefix, '', $cname);
        
        // the volType column name should be "vol" followed by a number.
        // remove "vol" and swap a description for the number.
        if( isset($map[$cname]))
        {
            $Tableset->set_column_name($col, $map[$cname]);
        }        
    }
    // done replacing column headers.
    return true;
}
// end replace_headers_by_map().
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