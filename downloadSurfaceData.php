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

// Volatility types, used in html form and parsing input.
const VOLTYPE_HIST = 0;    // historical volatility.
const VOLTYPE_IMPL = 1;    // implied volatility.

// Start the session to setup cookies.
session_start();

// Connect to the database or die. MD.
require('./dbconnect.php');

// A stack of error messages to display to the user.
$errors = array();

////////////////////////////////////////////////////////////////////////////////
// Error Checking and Parsing _GET requests.

// Extract dataType from _GET, or default to hvol.
$dataType = isset($_GET['dataType']) && $_GET['dataType'] == VOLTYPE_IMPL ? VOLTYPE_IMPL : VOLTYPE_HIST;


// Get the volatility type.
// @TODO: look into PHP's new filtering functions.
$volType = array();
if( isset($_GET['volType']))
{
    foreach($_GET['volType'] as $val)
    {
        // Make sure the volType is either an adjusted historical volatility.
        if( $dataType == VOLTYPE_HIST && (int)$val > 13 && (int)$val <= 26 )
        {
            $volType[] = (int)$val;
        }
        // Or the volType is Implied volatility from 1 to 6 months.
        else if( $dataType == VOLTYPE_IMPL && (int)$val >= -6 && (int)$val < 0 )
        {
            $volType[] = (int)$val;
        }
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

// Get the mapping of historical volatility types to names.
$hvolmap = get_hvolmap();

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
       margin: 10px 20px 100px;
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
   
   #preview {font-family: courier new, courier,monospace;
             font-size: 12pt;
             border-spacing: 0px;
             border-left: solid 1px #ddd;
             border-bottom: solid 1px #ddd;
             width: 50%; }
   #preview td, #preview th { padding: 0px 5px; border-style: solid; border-width: 1px 1px 0px 0px; border-color: #ddd; }
   #preview td.float { text-align: right;  }
   

   
   fieldset { border-color: goldenrod;}
   
   
   
  </style>
  <script type="text/javascript">
      function showHvol()
      {
          var hvolChk = document.getElementById('dataTypeHvolChk');
          if( ! hvolChk ){ alert('hvolChk is null'); return -1; }
          
          var ivolChk = document.getElementById('dataTypeIvolChk');
          if( ! ivolChk ){ alert('ivolChk is null'); return -1; }
          
          var hvolFS = document.getElementById('hvolFS');
          if( ! hvolFS ){ alert('hvolFS is null'); return -1; }
          
          var ivolFS = document.getElementById('ivolFS');
          if( ! ivolFS ){ alert('ivolFS is null'); return -1; }
          
          // hvol has been checked.
          if( hvolChk.checked )
          {
              hvolFS.style.display = '';
              ivolFS.style.display = 'none';
          }
          else
          {
              hvolFS.style.display = 'none';
              
              ivolFS.style.display = '';
          }
          
      }
  </script>
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
       <label><input type="radio" id="dataTypeHvolChk" name="dataType" <?php echo 'value="'.VOLTYPE_HIST.'"'. ($dataType == VOLTYPE_HIST ? ' checked="true"' : ''); ?> onchange="showHvol()" />
       Historical Volatility</label><br>
      <label><input type="radio" id="dataTypeIvolChk" name="dataType" <?php echo 'value="'.VOLTYPE_IMPL.'"'.($dataType == VOLTYPE_IMPL ? ' checked="true"' : ''); ?>  onchange="showHvol()" />
      Implied Volatility</label>
      </fieldset>
        
      <fieldset id="hvolFS" <?php if($dataType == VOLTYPE_IMPL){echo 'style="display:none"';} ?> >
       <legend>Historical Volatility</legend>
      <?php
    
    
    // Print checkboxes for each historical volatility type.
    foreach( $hvolmap as $id => $name )
    {
        echo '<label><input type="checkbox" name="volType[]" value="'.$id.'"';
        echo in_array($id, $volType) ? ' checked="checked"' : '';
        echo ">$name</label><br/>\n";
    }
      ?>
       </fieldset>
      
      <fieldset id="ivolFS" <?php if($dataType == VOLTYPE_HIST){echo 'style="display:none"';} ?> >
       <legend>Implied Volatility</legend>
       <?php
       // Output the 1 month first, since its label isn't plural.
        echo '<label><input type="checkbox" name="volType[]" value="'.$i.'"';
        echo in_array($i, $volType) ? ' checked="checked"' : '';
        echo '>1 month</label><br/>'."\n";
        // Output 2-6 months, using plural form of "months".
       for($i=-2; $i >= -6; $i--)
       {
           echo '<label><input type="checkbox" name="volType[]" value="'.$i.'"';
           echo in_array($i, $volType) ? ' checked="checked"' : '';
           echo '>'.($i*-1).' months</label><br/>'."\n";
       }
       ?>
      </fieldset>

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
  
/*
 * Page Body.
 */
if( $dataType == VOLTYPE_HIST )
{
    print_hvoldata($ticker, $startDate, $endDate, $volType, $hvolmap);
}
else
{
//    prin
}

/*
 * done printing page Body.
 */
  
/*
 * Print Page bottom.
 */

// Debugging output to verify correctness of volType array. MD.
//echo '<pre>'. print_r($_GET,true) . print_r($volType,true) . '</pre>';
    

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
       $harray[$id]= trim($name);
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
 * Given some parameters, query the database for historical volatility data.
 * Print html out to the page with a html table with the data.
 * 
 * @param string $ticker
 * @param string $startDate
 * @param string $endDate
 * @param int $volType
 * @param array $hvolmap
 */
function print_hvoldata($ticker, $startDate, $endDate, $volType, $hvolmap)
{
    //
    // Show a preview of the data:
    // Code from chart.php lines 261-281.

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
    $mysqlq_str="SELECT eqm.ticker, hvol.volType, eqp.close_, hvol.vol, \n"
    . "date_format(eqp.date_, '".SQL_DATE_FORMAT."') AS eqp_date\n"
    . "FROM eqprice AS eqp LEFT JOIN eqmaster AS eqm ON eqm.eqId=eqp.eqId AND eqp.date_ between eqm.startDate AND eqm.endDate\n"
    . "  JOIN eqhvol AS hvol ON hvol.eqId=eqp.eqId\n"
    //. " AND hvol.volType=".$volType."\n"
    . "   AND hvol.volType IN (".  implode(',', $volType).")\n"
    . "AND hvol.date_=eqp.date_\n"
    . "WHERE eqm.ticker like '$ticker' AND eqp.date_>='$startDate' AND eqp.date_<='$endDate'\n"
    . "ORDER BY eqp.date_, hvol.volType LIMIT 10";

    echo '<pre>'.$mysqlq_str.'</pre>';

    //$col_hdr = array('Ticker', 'Vol. Type', 'Price', 'Volatility', 'Date');
    //$hdr_formats = array('%-6s', '%9s',     '%-10s',    '%-17s',      '%-10s');
    //$col_formats = array('%-6s', '%-9s',       '%0.6f', '%0.15f',     '%s');
    //$format_str = '| ' . implode(' | ', $col_formats) . " |\n";
    //$hformat_str = '| ' . implode(' | ', $hdr_formats) . " |\n";

    // Now, run query to get data
    $mysqlq=mysql_query($mysqlq_str);
    if($mysqlq)
    {
        echo '<table id="preview"><thead>'."\n";
        echo ' <tr><th>Ticker</th>'."\n"
                . '<th width="20%">Volatility Calculation Method</th>'."\n"
                . '<th>Closing Price</th>'."\n"
                . '<th>Realized Volatility</th>'."\n"
                . '<th>Pricing Date</th>'."\n"
                . '</thead></tr><tbody>'."\n";

        while($row=mysql_fetch_array($mysqlq))
        {
            // Try to use the Hvol descriptive name instead of the numeric ID.
            $hvolname = $row[1];
            if( isset($hvolmap[$row[1]]) ) $hvolname = $hvolmap[$row[1]];

            echo '<tr><td class="str">'.$row[0]."</td>\n"
                    .'<td class="str">'.$hvolname."</td>\n"
                    .'<td class="float">'.sprintf('%0.6f',$row[2])."</td>\n"
                    .'<td class="float">'.sprintf('%0.15f',$row[3])."</td>\n"
                    .'<td class="str">'.$row[4]."</td>\n"
                    ."</tr>\n";
        }
        echo '</tbody></table>';
    }
    else
    {
        echo '<p>'.mysql_error()."</p>\n";
    }
}
// end print_hvoldata().

// @TODO: figure out the correct query. This one does not have moneyness in it.
function print_ivoldata($ticker, $startDate, $endDate, $volType, $moneyness, $hvolmap)
{
    // Code from chart.php lines 272-283.
    
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
}
// end print_ivoldata().

?>
 </body>
</html>
