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
session_start();

// Connect to the database or die. MD.
//require('./dbconnect.php');

////////////////////////////////////////////////////////////////////////////////
// Error Checking and Parsing _GET requests.

// Extract dataType from _GET, or default to hvol.
$dataType = isset($_GET['dataType']) && $_GET['dataType'] == 'ivol' ? 'ivol' : 'hvol';

// Extract termRadio from _GET or default to 1.
// This is the Short Term value.
$termRadio = 1;
if( isset($_GET['termRadio']))
{
    // Ensure that the values are integers between 1 and 6.
    if( (int)$_GET['termRadio'] >= 1 && (int)$_GET['termRadio'] <= 6)
    {
        $termRadio = (int)$_GET['termRadio'];
    }
}
// done getting short term.

// Long Term, default to 2.
$termRadioLong = 2;
if( isset($_GET['termRadioL']))
{
    // Ensure that the values are integers between 2 and 6.
    if( (int)$_GET['termRadioL'] >= 2 && (int)$_GET['termRadioL'] <= 6)
    {
        $termRadioLong = (int)$_GET['termRadioL'];
    }
}
// done getting long term.

// Start Date, default to 30 days ago.
date_default_timezone_set('America/New_York');

$startDate =   mktime() - 30*86400;
if( isset($_GET['startDate']))
{
    // @TODO: do error check on startDate.
    $startDate = $_GET['startDate'];
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
 </head>
 <body>
   <div>
     <form action="<?php echo basename(__FILE__); ?>" method="GET">
      
      <fieldset>
       <legend>Data Type</legend>
      <input type="radio" name="dataType" value="hvol" <?php echo $dataType == 'hvol' ? 'checked="true"' : ''; ?>/>
      Historical Volatility<br>
      <input type="radio" name="dataType" value="ivol" <?php echo $dataType == 'ivol' ? 'checked="true"' : ''; ?> />
      Implied Volatility
      </fieldset>
        
      <fieldset>
       <legend>Short Term</legend>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 1 ? 'checked="true"' : ''; ?>/>
       1 Month<br>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 2 ? 'checked="true"' : ''; ?>/>
       2 Month<br>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 3 ? 'checked="true"' : ''; ?>/>
       3 Month<br>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 4 ? 'checked="true"' : ''; ?>/>
       4 Month<br>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 5 ? 'checked="true"' : ''; ?>/>
       5 Month<br>
       <input type="radio" name="termRadio" value="<?php echo $termRadio; ?>" <?php echo $termRadio == 6 ? 'checked="true"' : ''; ?>/>
       6 Month<br>
      </fieldset>
      
      <fieldset>
       <legend>Long Term</legend>
       <input type="radio" name="termRadioL" value="<?php echo $termRadioLong; ?>" <?php echo $termRadioLong == 2 ? 'checked="true"' : ''; ?>/>
       2 Month<br>
       <input type="radio" name="termRadioL" value="<?php echo $termRadioLong; ?>" <?php echo $termRadioLong == 3 ? 'checked="true"' : ''; ?>/>
       3 Month<br>
       <input type="radio" name="termRadioL" value="<?php echo $termRadioLong; ?>" <?php echo $termRadioLong == 4 ? 'checked="true"' : ''; ?>/>
       4 Month<br>
       <input type="radio" name="termRadioL" value="<?php echo $termRadioLong; ?>" <?php echo $termRadioLong == 5 ? 'checked="true"' : ''; ?>/>
       5 Month<br>
       <input type="radio" name="termRadioL" value="<?php echo $termRadioLong; ?>" <?php echo $termRadioLong == 6 ? 'checked="true"' : ''; ?>/>
       6 Month<br>
      </fieldset>

      <fieldset>
       <legend>Date Range</legend>
       Start <input type="text" name="startDate" value="<?php echo $startDate ?>"/>
       End <input type="text" name="endDate" value=""/>
      </fieldset>
      
    </form>
  </div>
<?php
/*
 * done printing page head.
 */
  

  
/*
 * Print Page bottom.
 */
?>
 </body>
</html>
