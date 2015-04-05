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
 * License goes here. Created for Fishback Management and Research.
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
const MAX_PREVIEW_ROWS = 1000;

// The limit on the number of database rows to display in a download.
const MAX_DOWNLOAD_ROWS = 5000;

// List of possible moneyness values to show filters for.
// The order of the elements in this array determine which order the filters
// are displayed; e.g. array(95,90,85) shows 95, then 90, then 85.
// (Arrays cannot be constants, so this is a variable. MD.)
$MONEYNESS_VALUES = array(95,90,85);

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

// Default moneyness to 100%. Only allow values in the range [1,100].
$moneyness = 100;
if( isset($_GET['moneyness']))
{
    if( (int)$_GET['moneyness'] > 0 && (int)$_GET['moneyness'] <= 100 )
    {
        $moneyness = (int)$_GET['moneyness'];
    }
}
// done parsing moneyness.

$ticker = 'SPY';
if( isset($_GET['ticker']))
{
    // @TODO: check this input for bad data, such as SQL injection.
    $ticker = $_GET['ticker'];
}

////
////
//// done error checking and parsing _GET requests.
////
////

// Get the mapping of historical volatility integer values to string names.
$hvolmap = get_hvolmap();

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
   
   a { color: white; padding: 0.55em 11px 0.5em;
       background: linear-gradient(#444, #222) repeat scroll 0 0 #222;
   }
   
   .padSmall{padding:10px;}
   
   input.ticker{ background:linear-gradient(#fffadf, #fff3a5) repeat scroll 0 0 #fff9df;}
      
   label{padding-right: 10px; border-radius: 10px;}
   label:hover {
       color: #eee; background-color: #222;
   }
   
    <?php
    // At page load, hide either the Hist. Volatility filters or the Implied
    // volatility filters, depending $dataType.
   if($dataType == VOLTYPE_IMPL)
       {
        echo '#hvolFS{display:none}';
       
       }
       else
       {
        echo '#ivolFS{display:none}';   
       }?>
   
   #preview { position: absolute;
             top: 123px; left: 240px;  }
   
   #preview table {font-family: courier new, courier,monospace;
             font-size: 12pt;
             border-spacing: 0px;
             border-left: solid 1px #777;
             border-bottom: solid 1px #777;
             width: 650px;
   }
   #preview th,#preview table caption {font-family: arial; background-color: #333;}
   
   #preview td, #preview th { padding: 0px 5px; border-style: solid; border-width: 1px 1px 0px 0px; border-color: #777; }
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
       // Output the 1 month first, since its label isn't plural.
        echo '<label><input type="checkbox" name="volType[]" value="'.$i.'"';
        echo in_array($i, $volTypes) ? ' checked="checked"' : '';
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
        echo '>100% (normal)</lable>'."\n";
        
        // Display other moneyness values.
        foreach( $MONEYNESS_VALUES as $val )
        {
            echo '<br><label><input type="radio" name="moneyness" value="'.$val.'"';
            echo $moneyness == $val ? ' checked="checked"' : '';
            echo '>'.$val.'%</lable>'."\n";
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
      
      <a href="<?php echo basename(__FILE__); ?>" style="display:inline-block">Reset</a> <input type="submit" value="Submit" />
    </form>
  </div>
<?php
/*
 * done printing page top.
 */
  
/*
 * Page Body.
 */
if( count($volTypes) > 0 )
{
    echo '<div id="preview">'."\n";
    if( $dataType == VOLTYPE_HIST )
    {
//        print_hvoldata($ticker, $startDate, $endDate, $volTypes, $hvolmap);
            //
        // Show a preview of the data:
        // SQL Query from chart.php lines 261-281.

        // Note: Does term really mean volatility type? No. His requirements specify
        //       that the user chooses "term." However, the query asks for a volatility
        //       type. Also, there is a volatility type on the chart menu that matches 
        //       what this query expects.
        //       
        //       // This is the original query I thought was correct. MD.
        //    $mysqlq_str="SELECT eqp.date_, eqp.vol \n"
        //        . "FROM eqhvol AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND eqm.startDate >= '$startDate' and eqm.endDate <= '$endDate' \n"
        //        . "WHERE eqm.ticker like '$ticker' AND eqp.date_<= now() \n"
        //        . "  AND eqp.volType=$volType\n";


        // Query is adapted from chart.php Line 375.
        // @TODO: see if this data is correct. Need to ask about it, because 
        // a query will return results even if there is no hvol.vol data. (because of left join).
        // changing to normal join fixes that. data starts whenever hvol data starts.
        $query_str="SELECT eqm.ticker, hvol.volType, eqp.close_, hvol.vol, \n"
        . "date_format(eqp.date_, '".SQL_DATE_FORMAT."') AS eqp_date\n"
        . "FROM eqprice AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
        . "  JOIN eqhvol AS hvol ON hvol.eqId=eqp.eqId\n"
        //. " AND hvol.volType=".$volType."\n"
        . "   AND hvol.volType IN (".  implode(',', $volTypes).")\n"
        . "AND hvol.date_=eqp.date_\n"
        . "WHERE eqm.ticker like '$ticker' AND eqp.date_>='$startDate' AND eqp.date_<='$endDate'\n"
        . "ORDER BY eqp.date_, hvol.volType\n";
        
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
        
        $ResultTable->print_table_string();
        
        // Print the raw query for debugging.
        echo '<pre>'.$query_str.'</pre>';
    }
    else
    {
        //
        // Code from chart.php lines 272-283.
        //
    
        //Now, run query to get data
        $mysqlq="SELECT eqp.date_, eqp.ivMid\n"
        . "FROM ivcmpr AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND '$currentDate' between eqm.startDate and eqm.endDate\n"
        . "WHERE eqm.ticker like '$ticker'\n"
        . "  AND eqp.date_<='$currentDate'\n"
        . "  AND eqp.expiry=".abs($v)."\n"
        . "  AND eqp.strike=100";

        if($mysql=mysql_query($mysqlq)) {
            $row=mysql_fetch_array($mysql);
            $volOutput="[ [".(strtotime($row['date_'])*1000).", ".number_format($row['ivMid']*100, 4)." ]";
            while($row=mysql_fetch_array($mysql)) {
              $volOutput .= ", [".(strtotime($row['date_'])*1000).", ".number_format($row['ivMid']*100, 4)." ]\n";
            }
            $volOutput .= " ]";

            array_push($volOutArray, $volOutput);
        }
        $volName="Implied Volatility ".abs($v)."m";
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
 * Code adapted from chart.php lines 261-281, written by Chris Moore,
 * Fishback Management and Research 12/20/12.
 * 
 * @global array $errors
 * @return array
 * Returns an associative array with volType for array keys, and a formatted 
 * name as the array values.
 * 
 * If the database query fails, then an empty array is returned.
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

?>
 </body>
</html>
