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

// Volatility types, used in html form and parsing input.
const VOLTYPE_HIST = 0;    // historical volatility.
const VOLTYPE_IMPL = 1;    // implied volatility.

// The limit on the number of database rows to display in a result.
const MAX_PREVIEW_ROWS = 100;

// The limit on the number of database rows to display in a download.
const MAX_DOWNLOAD_ROWS = 5000;

// List of possible moneyness values to show filters for.
// The order of the elements in this array determine which order the filters
// are displayed; e.g. array(95,90,85) shows 95, then 90, then 85.
// (Arrays cannot be constants, so this is a variable. MD.)
$MONEYNESS_VALUES = array(105,95,90,85,80);

// Start the session to setup cookies.
session_start();

// Connect to the database or die. MD.
require('./dbconnect.php');

// Library for displaying nice tables.
require('./class-MysqlResultTable.php');

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
// @TODO: look into PHP's new filtering functions.
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
        // Or the volType is Implied volatility from 1 to 6 months.
        else if( $dataType == VOLTYPE_IMPL && (int)$val >= -6 && (int)$val < 0 )
        {
            $volTypes[] = (int)$val;
        }
    }
    // done looping over each volType checkbox value.
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
// done parsing startdate.

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
// done parsing endDate.

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
    // @TODO: check this input for bad data, such as SQL injection.
//    $ticker = $_GET['ticker'];

    // Use a custom filter.
//    $ticker = filter_input(INPUT_GET, 'ticker', FILTER_CALLBACK, array('options' => 'sanitizeTicker') );
//    $ticker = filter_input(INPUT_GET, 'ticker', FILTER_SANITIZE_STRING);
    
    // Remove anything that isn't alphanumeric, period, $, /, space, or dash.
    $ticker = preg_replace('@[^a-z0-9\.\$\/ \-!]@i', '', $_GET['ticker']);
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
$ivolmap = array(
    '1' => 'IV 1 Month',
    '2' => 'IV 2 Months',
    '3' => 'IV 3 Months',
    '4' => 'IV 4 Months',
    '5' => 'IV 5 Months',
    '6' => 'IV 6 Months' );

/*
 * Decide which query to use for both the preview and the CSV download.
 */
$query_str;
if( $dataType == VOLTYPE_HIST )
{
    
    // This is the original query I thought was correct but wasn't. MD.
    // SQL Query from chart.php line 262.    
//    $query_str="SELECT eqp.date_, eqp.vol \n"
//    . "FROM eqhvol AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
//    . "  AND eqm.startDate >= '$startDate' and eqm.endDate <= '$endDate' \n"
//    . "WHERE eqm.ticker like '$ticker' AND eqp.date_<= now() \n"
//    . "  AND eqp.volType=$volType\n";
    // Note: Does term really mean volatility type? No. His requirements specify
    //       that the user chooses "term." However, the query asks for a volatility
    //       type. Also, there is a volatility type on the chart menu that matches 
    //       what this query expects.


    // SQL query is adapted from query in chart.php Line 351.
    $query_str="SELECT eqm.ticker, hvol.volType, eqp.close_, hvol.vol, \n"
    . "  date_format(eqp.date_, '".SQL_DATE_FORMAT."') AS eqp_date\n"
    . "FROM eqprice AS eqp\n"
    . "LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
    . "  AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
    . "JOIN eqhvol AS hvol ON hvol.eqId=eqp.eqId\n"
    //. " AND hvol.volType=".$volType."\n"
    . "  AND hvol.volType IN (".  implode(',', $volTypes).")\n"
    . "  AND hvol.date_=eqp.date_\n"
    . "WHERE eqm.ticker like '$ticker'\n"
    . "  AND eqp.date_>='$startDate'\n"
    . "  AND eqp.date_<='$endDate'\n"
    . "ORDER BY eqp.date_, hvol.volType\n" ;
}
else
{
    // Take the selected values of IV expiry and make them positive.
    // IV expiry values are submitted in the form with negative integers
    // to distinguish them from Historical Volatility expiration values.
    $expiry = array();
    foreach($volTypes as $val )
    {
        $expiry[] = $val * -1;
    }
    
    // SQL query adapted from chart.php line 349.
    $query_str="SELECT eqm.ticker AS Ticker,\n"
    . "  FLOOR(iv.expiry) as Indicator,\n"   // expiry values are float; easier to use int.
    . "  iv.strike as Moneyness, eqp.close_,\n"
    . "  iv.ivBid, iv.ivAsk, iv.ivMid,\n"
    . "  date_format(eqp.date_, '".SQL_DATE_FORMAT."') AS PricingDate\n"
    . "FROM eqprice AS eqp\n"
    . "LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
    . "  AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
    . "JOIN ivcmpr AS iv ON iv.eqId=eqp.eqId\n"
    . "  AND iv.strike=$moneyness\n"
//        . "      AND iv.strike=100\n"
    . "  AND iv.expiry IN (".implode(',',$expiry).")\n"
    . "  AND iv.date_=eqp.date_\n"
    . "WHERE eqm.ticker like '$ticker'\n"
    . "  AND eqp.date_ <= '$endDate'\n"
    . "  AND eqp.date_ >= '$startDate'\n"
    . "ORDER BY eqp.date_, iv.expiry \n";
    
    // SQL query adapted from chart.php line 249.
//    $query_str = "SELECT eqp.date_, eqp.ivMid\n"
//    . "FROM ivcmpr AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId\n"
//    . "  AND '$currentDate' between eqm.startDate and eqm.endDate\n"
//    . "WHERE eqm.ticker like '$ticker'\n"
//    . "  AND eqp.date_<='$currentDate'\n"
//    . "  AND eqp.expiry IN (".implode(',',$expiry).") \n"
//    . "  AND eqp.strike=$moneyness";
}
// done deciding which query to use.

/*
 * Check if we're printing CSV data.
 * Possible EXIT point for script.
 */
if( isset($_GET['submit']) && $_GET['submit'] == 'Download')
{
    $query_str .= "LIMIT ".MAX_DOWNLOAD_ROWS;
    
    $ResultTable = new MysqlResultTable($query_str);
    
    if( $dataType == VOLTYPE_HIST )
    {
        $ResultTable->set_column_value_map(1, $hvolmap);
        $ResultTable->set_column_name(0, 'Ticker');
        $ResultTable->set_column_name(1, 'Indicator');
        $ResultTable->set_column_name(2, 'Closing Price');
        $ResultTable->set_column_name(3, 'Realized Volatility');
        $ResultTable->set_column_name(4, 'Pricing Date');
    }
    else
    {
        $ResultTable->set_column_value_map(1, $ivolmap);
        
//        $ResultTable->set_column_name(0, 'Ticker');
        $ResultTable->set_column_name(3, 'Closing Price');
//        $ResultTable->set_column_name(3, 'Pricing Date');
    }
    
    $ResultTable->print_csv_headers();
    $ResultTable->print_table_csv();
    
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
  <style type="text/css">
   body {
       color: white;
       /*background: linear-gradient(#3c3c3c, #111) repeat scroll 0 0 #111;*/
       /*background: linear-gradient(#444, #222) repeat scroll 0 0 #222;*/
       background-color: #444;
       font-family: Helvetica,Arial,sans-serif;
       margin: 10px 20px 100px;
   }
   ul#errors{
       color:red;
       background:white;
       padding: 20px;
   }
   
   #filters { width: 200px;}
/*   #filters input[value="Download"] { position:absolute; left:250px; top:123px; z-index:2; }*/
   
   #downloadForm { position:absolute; left:250px; top:123px; z-index:2; }
   
   a { color: white; padding: 0.55em 11px 0.5em;
       background: linear-gradient(#444, #222) repeat scroll 0 0 #222;
   }
   
   .padSmall{padding:10px;}
   
   input.ticker{ background:linear-gradient(#fffadf, #fff3a5) repeat scroll 0 0 #fff9df;}
      
   label{padding-right: 10px; border-radius: 10px;}
   label:hover { color: #eee; background-color: #222; }   
<?php
    // At page load, hide either the Hist. Volatility filters or the Implied
    // volatility filters, depending $dataType.
    echo $dataType == VOLTYPE_IMPL ? '#hvolFS{display:none}' : '#ivolFS{display:none}';
?>
   #preview { position: absolute; top: 123px; left: 240px;  }
   
   #preview table {font-family: courier new, courier,monospace;
             font-size: 12pt;
             border-spacing: 0px;
             border-left: solid 1px #777;
             border-bottom: solid 1px #777;
             /*width: 650px;*/
   }
   #preview th,#preview table caption {font-family: arial; background-color: #333;}
   
   #preview td, #preview th { padding: 0px 10px; border-style: solid; border-width: 1px 1px 0px 0px; border-color: #777; }
   #preview td.int,#preview td.real,#preview td.rowNo { text-align: right;  }

   #preview p.rightDim { color:#aaa; margin:6px 10px 30px; text-align: right; width:69%; }

   fieldset { border-color: goldenrod;}   
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
     <form action="<?php echo basename(__FILE__); ?>" method="GET">
      
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
        echo ">$name</label><br/>\n";
    }
      ?>
       </fieldset>
      </div>
      
      <div id="ivolFS">
      <fieldset>
       <legend>Implied Volatility</legend>
       <?php
       // Output the 1 month first, not using a loop; its label isn't plural.
        echo '<label><input type="checkbox" name="volType[]" value="-1"';
        echo in_array(-1, $volTypes) ? ' checked="checked"' : '';
        echo '>1 month</label><br/>'."\n";
        // Output 2-6 months, using plural form of "months".
       for($i=-2; $i >= -6; $i--)
       {
           echo '<label><input type="checkbox" name="volType[]" value="'.$i.'"';
           echo in_array($i, $volTypes) ? ' checked="checked"' : '';
           echo '>'.($i*-1).' months</label><br/>'."\n";
       }
       ?>
      </fieldset>
       <fieldset>
        <legend>Moneyness</legend>
        <?php
        
        // Display normal moneyness value of 100.
        echo '<label><input type="radio" name="moneyness" value="100"';
        echo $moneyness == 100 ? ' checked="checked"' : '';
        echo '>100%</label>'."\n";
        
        // Display other moneyness values.
        foreach( $MONEYNESS_VALUES as $val )
        {
            echo '<br><label><input type="radio" name="moneyness" value="'.$val.'"';
            echo $moneyness == $val ? ' checked="checked"' : '';
            echo '>'.$val.'%</label>'."\n";
        }
        ?>
       </fieldset>
      </div>

      <fieldset style="position:relative;">
       <legend>Date Range</legend>
       Start <input type="text" name="startDate" value="<?php echo $startDate ?>" style="position:absolute; right:10px; width: 100px;" />
       <hr/>
       End <input type="text" name="endDate" value="<?php echo $endDate;?>" style="position:absolute; right:10px; width: 100px;" />
      </fieldset>
      
      <div class="padSmall">Ticker Symbol: <input class="ticker" type="text" name="ticker" value="<?php echo $ticker;?>"/></div>
      
      <a href="<?php echo basename(__FILE__); ?>" style="display:inline-block">Reset</a>
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
    print_download_form();
    
    /*
     * ?>
   <div id="#downloadForm">
    <form action="<?php echo basename(__FILE__); ?>" method="POST">
     <input type="hidden" name=""
    </form>
  </div>
  <?php
     *     */
    
    $query_str .= "LIMIT ".MAX_PREVIEW_ROWS;
    
    echo '<div id="preview">'."\n";
    if( $dataType == VOLTYPE_HIST )
    {
        $ResultTable = new MysqlResultTable($query_str);

        $ResultTable->caption = 'Preview';
        $ResultTable->footer = 'Showing up to '.MAX_PREVIEW_ROWS.' rows.';
        
        $ResultTable->set_column_name(0, 'Ticker');
        $ResultTable->set_column_name(1, 'Indicator');
        $ResultTable->set_column_name(2, 'Closing Price');
        $ResultTable->set_column_name(3, 'Realized Volatility');
        $ResultTable->set_column_name(4, 'Pricing Date');
        
        $ResultTable->set_column_width(1, '20%');
        $ResultTable->set_column_value_map(1, $hvolmap);
        $ResultTable->set_column_type(1, MysqlResultTable::TYPE_STRING);
        
        $ResultTable->print_table_html();
        
        // Print the raw query for debugging.
        echo '<pre>'.$query_str.'</pre>';
    }
    else
    {   
        $ResultTable = new MysqlResultTable($query_str);
        $ResultTable->caption = 'Preview';
        $ResultTable->footer = 'Showing up to '.MAX_PREVIEW_ROWS.' rows.';
//        $ResultTable->set_column_name(0, 'Ticker');
//        $ResultTable->set_column_name(1, 'Indicator');
        $ResultTable->set_column_name(3, 'Closing Price');
//        $ResultTable->set_column_name(3, 'Realized Volatility');
//        $ResultTable->set_column_name(3, 'Pricing Date');
//        $ResultTable->set_column_name(5, 'Moneyness');
        
        $ResultTable->set_column_value_map(1, $ivolmap);
        
        $ResultTable->print_table_html();
        
        // Print the raw query for debugging.
        echo '<pre>'.$query_str.'</pre>';
        
        //
        //
        //
    }
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
       $harray[$id] = 'HV ' . trim($name);
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
 * Reprint the submitted form as another form with hidden values selected.
 * This makes the downloaded data consistent with whatever data is previewed
 * in the browser.
 * 
 * 
 * @global type $dataType
 * @global type $volTypes
 * @global type $startDate
 * @global type $endDate
 * @global type $moneyness
 * @global type $ticker
 */
function print_download_form()
{
    global $dataType, $volTypes, $startDate, $endDate, $moneyness, $ticker;
    
    echo '<div id="downloadForm">'."\n";
    
    echo '<form action="'.basename(__FILE__).'" method="GET">'."\n";
    
    echo ' <input type="hidden" name="dataType" value="'.$dataType.'" />'."\n";
    
    foreach($volTypes as $val)
    {
        echo ' <input type="hidden" name="volType[]" value="'.$val.'" />'."\n";
    }
    
    echo ' <input type="hidden" name="startDate" value="'.$startDate.'" />'."\n";
    echo ' <input type="hidden" name="endDate" value="'.$endDate.'" />'."\n";
    echo ' <input type="hidden" name="moneyness" value="'.$moneyness.'" />'."\n";
    echo ' <input type="hidden" name="ticker" value="'.$ticker.'" />'."\n";

    echo ' <input type="submit" name="submit" value="Download" />'."\n";
    
    echo "</form>\n";
    
    echo "</div>\n";
}
// end print_download_form().

?>
 </body>
</html>
