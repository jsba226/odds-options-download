<?php

/*
 * IndividualOptionData.php
 * 
 * Author: Jeremy Bailey
 * Data: 4/4/2015
 * 
 * Web form for choosing Individual Option Data to download:
 * Input:
 *  User specifies ticker symbol
 *  User selects type
 *  User selects strike range
 *  User selects IV Bid range
 *  User selects IV Ask range (found in "ivlisted" table)
 *  User selects unique Option ID
 * 
 * Output:
 *  Individual option data from the 'optcontract' table and prices from 'optprice' table
 */
// put call types
const TYPE_PUT = 1;
const TYPE_CALL = 0;
//The limit on the number of database rows to display in a result.
const MAX_PREVIEW_ROWS = 100;
//The limit on the number of database rows to display in a download.
const MAX_DOWNLOAD_ROWS = 5000;
//start the session
session_start();
// Include a file containing functions and constants shared between this script
// and other downloader scripts. Also attempts database connection.
require './dbconnect.php';
//require './inc.download.php';
//array of error messages to display to the user.
$errors = array();

//check for errors and parsing _GET requests

$ticker = 'SPY';
if( isset($_GET['ticker']))
{
    $ticker = $_GET['ticker'];
    //Remove any character that isn't alphanumeric, period, $, /, space, or dash.
   // $ticker = preg_replace('@[^a-z0-9\.\$\/ \-!]@i', '',$_GET['ticker']);
}
//Not Sure if the type that is listed in the requirements refers to the opttype or the put/call type
//From manual, table optContract. optType. varchar(4). Nullable. Two possible types: STAN = Standard, BINY = binary
//
//if optType
$optType; 
if (isset($_GET['opttype'])) 
{
    $optType = $_GET['opttype'];
}
//if Put/Call type
//Extract the put or call type from _GET or default to Call
$putCall;
if(isset($_GET['putCall']))
{
	$putCall = $_GET['putCall'];
}
//if multiplier from optContract table is <= 10, than it is a mini option
$lowStrike;
//Get low strike
if(isset($_GET['lowstrike']))
{
	$lowStrike = $_GET['lowstrike'];
}
$highStrike;
//Get high Strike
if(isset($_GET['highStrike']))
{
        $highStrike = $_GET['highstrike'];
}
//From ivListed table. type real. Nullable. Implied volatility for Bid Price.
$lowIvBid; 
if ( isset($_GET['lowivBid'])) 
{
   $lowIvBid = $_GET['lowivBid'];
}
$highIvBid;
if ( isset($_GET['highivBid']))
{
    $highIvBid = $_GET['highivBid'];
}

//From ivListed table. type real. Nullable. Implied volatility for Ask Price
$lowIvAsk; 
if ( isset($_GET['lowivAsk'])) 
{
    $lowIvAsk = $_GET['lowivAsk'];
}

$highIvAsk;
if ( isset($_GET['highivAsk']))
{
    $highIvAsk = $_GET['highivAsk'];
}

//From table optContract. int. Not Nullable. Option Identifer
$optId; 
//From unique optionID you can get the opt-price, 1.Type. 2. Strike. 3. Expiration
if ( isset($_GET['option-id'])) 
{
    $optId = $_GET['option-id'];
}
//from optContract table. putCall. type char(1). Not Nullable. Put or Call Indicator: P=Put, C=Call
////strike. decimal(9,3). Not Nullable. Strike Price.
//done parsing _GET requests

$query_str;
//$query_str = 
//example query from James
//"SELECT op.optId, oc.putCall, oc.strike, oc.expDate, oc.opraRoot, op.date_, iv.ivBid, iv.ivAsk, iv.ivMid, iv.delta, iv.gamma, iv.theta, iv.vega, iv.rho, op.volume, op.bid, op.ask, op.openInt, oc.corpAction FROM optprice AS op LEFT JOIN optcontract AS oc ON oc.optId=op.optId AND op.date_ between oc.startDate AND oc.endDate LEFT JOIN eqmaster AS eqm ON eqm.eqId=oc.eqId AND op.date_ between eqm.startDate and eqm.endDate LEFT JOIN ivlisted AS iv ON iv.optId=op.optId AND iv.date_=op.date_ WHERE eqm.ticker='$ticker' AND op.date_='$currentDate' AND oc.expDate='$expDate' AND oc.strike between '$lowStrike' AND '$highStrike'";
//test query for debugging
$query_str = "SELECT op.optId, oc.putCall, oc.strike, iv.ivBid, iv.ivAsk, iv.ivMid, iv.delta, iv.gamma, iv.theta, iv.vega, iv.rho, op.volume, op.bid, op.ask, op.openInt, oc.corpAction FROM optprice AS op LEFT JOIN optcontract AS oc ON oc.optId=op.optId AND op.date_ between oc.startDate AND oc.endDate LEFT JOIN eqmaster AS eqm ON eqm.eqId=oc.eqId AND op.date_ between eqm.startDate and eqm.endDate LEFT JOIN ivlisted AS iv ON iv.optId=op.optId AND iv.date_=op.date_ WHERE eqm.ticker='$ticker'";

//Check if we're printing CSV data.
//Possible EXIT point
if(isset($_GET['submit']) && $_GET['submit'] == 'Download') {
    $query_str .= "LIMIT ".MAX_DOWNLOAD_ROWS;
    
    $ResultTable = new MysqlResultTable();
    $ResultTable->executeQuery($query_str);

    $ResultTable->set_column_name(0,'Option ID');
    $ResultTable->set_column_name(1,'Put/Call');
    $ResultTable->set_column_name(2,'Strike');
    $ResultTable->set_column_name(3,'ivBid');
    $ResultTable->set_column_name(4,'ivAsk');
    $ResultTable->set_column_name(5,'ivMid');

    $ResultTable->print_csv_headers();
    $ResultTable->print_table_csv();

    //Stop the script so that only CSV output gets transmitted.
    //exit;
}
//done printing csv data
?>

<!DOCTYPE html>
<html>
    <head>
        <title>OptionApps</title>
        <meta charset="UTF-8">
        <meta http-equive="Content-Type" content="text/html;charset=UTF-8" />
        <link rel="stylesheet" type="text/css" href="download.css" />
        <style type="text/css">
	<?php
	// Print the CSS fro the data Table
	$TDC = new TablesetDefaultCSS();
	$TDC->set_css_tdOdd_value('background-color', null);
	$TDC->set_css_td_value('background-color', '#444');
	$TDC->set_tr_hover_value('background-color', null);
	$TDC->print_css();
	?>
	//Original CSS before TablesetDefaultCSS class was created
          /*  body {
                color: white;
                background-color: #444;
                font-family: Helvetica, Arial, sans-serif;
                margin: 10px 20px 100px;
            }
            u1#errors{
                color:red;
                background:white;
                padding: 20px;
            }
            #filters {width: 200px;}
            /*   #filters input[value="Download"] { position:absolute; left:250px; top:123px; z-index:2; }*/

           /* #downloadForm {position:absolute; left:250px;top:123px; z-index:2;}
            
            a { color: white; padding: 0.55em 11px 0.5em;
                background: linear-gradient(#444, #222) repeat scroll 0 0 #222;
            }
       
            /*.horzspace{display:inline-block;width:20px; border: none;}*/

            /*input.ticker {background:linear-gradient(#fffadf, #fff3a5) repeat scroll 0 0 #fff9df;}
            
            label{padding-right: 10px; border-radius: 10px;}
            label:hover {
                color: #eee; background-color: #222;
            }
            #preview {position: absolute; top: 123px; left: 240px;}
            
            #preview table {font-family: courier new, courier,monospace;
                      font-size: 12pt;
                      border-spacing: 0px;
                      border-left: solid 1px #777;
                      border-bottom: solid 1px #777;
                      /*width: 50%;*/ }
            /*#preview th, #preview table caption {font-family: arial; background-color: #333;}
            
            #preview td, #preview th { padding: 0px 10px; border-style: solid; border-width: 1px 1px 0px 0px; border-color: #777; }
            #preview td.int, #preview td.real,#preview td.rowNo { text-align: right;  }
            #preview p.rightDim { color: #aaa; margin:6px 10px 30px; text-align: right; width: 69%;}
            
            fieldset { border-color: goldenrod; margin-bottom:10px;
     	}
	    input {
		margin-bottom:5px;
	    }
		
	.blocked {
		display:block;
	}*/
            </style>
    </head>
    <body>
        <?php
            //Show any errors
            if(count($errors) > 0) {
                echo '<ul id="errors">';
                //loop through and print all errors
                for($i=0, $n=count($errors); $i<$n; $i++)
                {
                    echo '<li>'. $errors[$i]. "</li>\n";
                }
                echo "</ul>\n";
            }
        //done printing errors
        ?>
        <img src="barlogo2.png" width="310px" height="100px" /><br>
        <div id="filters">
            <form action="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" method="GET">
                <fieldset> /*either Put Call type or opt type*/
                    /*if option type is wanted*/
		    <legend>Type</legend>
                    <?php
                    //Display Option Type values
                    echo '<label class="blocked"><input type="radio" name="opttype" value="STAN"';
                    echo $optType == STAN ? ' checked="checked"' : '';
                    echo '>Standard</label>'."\n";
                    
                    echo '<label class="blocked"><input type="radio" name="opttype" value="BINY"';
                    echo $optType == BINY ? ' checked="checked"' : '';
                    echo '>Binary</label>'."\n";
                    ?>
		</fieldset>
		<fieldset>
		    /*if Put Call is wanted*/
		    <legend>Put/Call</legend>
                    <?php
                    //Display Option Type values
                    echo '<label class="blocked"><input type="radio" name="putCall" value="Call"';
                    echo $putCall == Call ? ' checked="checked"' : '';
                    echo '>Call</label>'."\n";

                    echo '<label class="blocked"><input type="radio" name="putCall" value="Put"';
                    echo $putCall == Put ? ' checked="checked"' : '';
                    echo '>Put</label>'."\n";
                    ?>

		</fieldset>
		<fieldset>
		    <legend>Strike Range</legend>
			<label for="low-strike">Low Strike</label>
			<input type="text" name="lowstrike" id="lowstrike" value="<?php echo $lowStrike; ?>"/>
 	                <label for="high-strike">High Strike</label>
                        <input type="text" name="highstrike" id="highstrike" value="<?php echo $highStrike; ?>"/>
		</fieldset>
		<fieldset>
		    <legend>IV Bid</legend>
                        <label for="low-ivbid">Low IV Bid</label>
                        <input type="text" name="lowivbid" id="lowivbid" value="<?php echo $lowIvBid; ?>"/>
                        <label for="high-ivbid">High IV Bid</label>
                        <input type="text" name="highivbid" id="highivbid" value="<?php echo $highIvBid; ?>"/>
		</fieldset>
                <fieldset>
                    <legend>IV Ask</legend>
                        <label for="low-ivask">Low IV Ask</label>
                        <input type="text" name="lowivask" id="lowivask" value="<?php echo $lowIvAsk; ?>"/>
                        <label for="high-ivask">High IV Ask</label>
                        <input type="text" name="highivask" id="highivask" value="<?php echo $highIvAsk; ?>"/>
                </fieldset>
                <fieldset>
                    <legend>Option ID</legend>
                        <input type="text" name="option-id" id="option-id" value="<?php echo $optId; ?>"/>
                </fieldset>
                <fieldset>
                    <legend>Ticker Symbol</legend>
                        <input type="text" name="ticker" id="ticker" value="<?php echo $ticker; ?>"/>
                </fieldset>
        </div>
	<a href="<?php echo basename(__FILE__); ?>" style="display:inline-block">Reset</a>
        <input type="submit" value="Preview" name="submit" />
            </form>
        </div>
        <?php
        //done printing page top.
        
        //Page Body.
        
        //Print from database if the user selected at least one input
        if($optId || $putCall || $lowStrike || $highStrike || $lowIvBid || $highIvBid || $lowIvAsk || $highIvAsk || $optType || $ticker)
        {
            print_download_form();
            
            $query_str .= "LIMIT ".MAX_PREVIEW_ROWS;
            echo '<div id="preview">'."\n";

            $ResultTable = new MysqlResultTable();
	    $ResultTable->executeQuery($query_str);
            $ResultTable->caption = 'Preview';
            $ResultTable->footer = 'Showing up to '.MAX_PREVIEW_ROWS.' rows.';
        
            $ResultTable->set_column_name(0,'Option ID');
    	    $ResultTable->set_column_name(1,'Put/Call');
    	    $ResultTable->set_column_name(2,'Strike');
    	    $ResultTable->set_column_name(3,'ivBid');
    	    $ResultTable->set_column_name(4,'ivAsk');
    	    $ResultTable->set_column_name(5,'ivMid');
        
            $ResultTable->set_column_width(1, '20%');
            
            $ResultTable->print_table_html();
        
            // Print the raw query for debugging.
            echo '<pre>'.$query_str.'</pre>';
        }
        //done printing page body
    
    //done print page bottom
    //
    //nothing yet
    //
    //done printing page bottom
    //
    //
    
           
    //original print_download_form function before the .inc.download.php file was created
    /*function print_download_form()
    {
        global $ticker, $optType, $lowStrike,$highStrike, $lowIvBid,$highIvBid, $lowIvAsk,$highIvAsk, $optId;
        
        echo '<div id="downloadForm">'."\n";    
        echo '<form action="'.basename(__FILE__).'" method="GET">'."\n";
       // echo ' <input type="hidden" name="optionType" value="'.$optionType.'" />'."\n";
        //echo ' <input type="hidden" name="ticker" value="'.ticker.'" />'."\n";
        //echo ' <input type="hidden" name="optType" value="'.$optType.'" />'."\n";
        //echo ' <input type="hidden" name="minioption" value="'.$mini_option.'" />'."\n";
        //echo ' <input type="hidden" name="ivbid" value="'.$ivBid.'" />'."\n";
        //echo ' <input type="hidden" name="ivask" value="'.$ivAsk.'" />'."\n";
        echo ' <input type="hidden" name="optId" value="'.$optId.'" />'."\n";
        echo ' <input type="submit" name="submit" value="Download" />'."\n";
    
        echo "</form>\n";
    
        echo "</div>\n";
    }*/
    //end print_download_form() funtion
    ?>
        
    </body>
</html>
