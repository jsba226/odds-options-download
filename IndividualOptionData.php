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
 *  User selects mini-option
 *  User selects IV Bid
 *  User selects IV Ask information (found in "ivlisted" table)
 *  User selects unique Option ID
 * 
 * Output:
 *  Individual option data from the 'optcontract' table and prices from 'optprice' table
 */

//start the session
session_start();
//connect to the database or die
require('./dbconnect.php');
//array of error messages to display to the user.
$errors = array();

//check for errors and parsing _GET requests

$ticker = 'SPY';
if( isset($_GET['ticker']))
{
    //$ticker = $_GET['ticker'];
    //Remove any character that isn't alphanumeric, period, $, /, space, or dash.
    $ticker = preg_replace('@[^a-z0-9\.\$\/ \-!]@i', '',$_GET['ticker']);
}
//From manual, table optContract. optType. varchar(4). Nullable. Two possible types: STAN = Standard, BINY = binary
$optType = 'error'; 
if ( isset($_GET['optType'])) 
{
    $optType = $_GET['optType'];
}
//if multiplier from optContract table is <= 10, than it is a mini option
$mini_option = 'error'; //Figure out what mini options are
if ( isset($_GET['mini_option'])) 
{
    $mini_option = $_GET['mini_option'];
}
//From ivListed table. type real. Nullable. Implied volatility for Bid Price.
$$ivBid = 'error'; 
if ( isset($_GET['ivBid'])) 
{
    $ivBid = $_GET['ivBid'];
}
//From ivListed table. type real. Nullable. Implied volatility for Ask Price
$ivAsk = 'error'; 
if ( isset($_GET['ivAsk'])) 
{
    $ivAsk = $_GET['ivAsk'];
}
//From table optContract. int. Not Nullable. Option Identifer
$optId = 'error'; 
//From unique optionID you can get the opt-price, 1.Type. 2. Strike. 3. Expiration
if ( isset($_GET['optId'])) 
{
    $optId = $_GET['optId'];
}
//from optContract table. putCall. type char(1). Not Nullable. Put or Call Indicator: P=Put, C=Call
////strike. decimal(9,3). Not Nullable. Strike Price.
//done parsing _GET requests

//query. Need to get from James
$query_str;
$query_str = "SELECT optId, putCall, strike, eqId, multiiplier FROM optContract WHERE opId='$optId'";

//Check if we're printing CSV data.
if(isset($_GET['submit']) && $_GET['submit'] == 'Download') {
    $query_str .= "LIMIT ".MAX_DOWNLOAD_ROWS;
    
    $ResultTable = new MysqlResultTable($query_str);
    $ResultTable->set_column_name(0,'Option ID');
    $ResultTable->set_column_name(1,'Put/Call');
    $ResultTable->set_column_name(2,'Strike');
    $ResultTable->set_column_name(3,'eqId');
    $ResultTable->set_column_name(4,'multiplier');

    $ResultTable->print_csv_headers();
    $ResultTable->print_table_csv();
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
        <style type="text/css">
            body {
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

            #downloadForm {position:absolute; left:250px;top:123px; z-index:2;}
            
            a { color: white; padding: 0.55em 11px 0.5em;
                background: linear-gradient(#444, #222) repeat scroll 0 0 #222;
            }
       
            /*.horzspace{display:inline-block;width:20px; border: none;}*/
            .padSmall{padding: 10px;}
            input.ticker {background:linear-gradient(#fffadf, #fff3a5) repeat scroll 0 0 #fff9df;}
            
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
            #preview th, #preview table caption {font-family: arial; background-color: #333;}
            
            #preview td, #preview th { padding: 0px 10px; border-style: solid; border-width: 1px 1px 0px 0px; border-color: #777; }
            #preview td.int, #preview td.real,#preview td.rowNo { text-align: right;  }
            #preview p.rightDim { color: #aaa; margin:6px 10px 30px; text-align: right; width: 69%;}
            
            fieldset { border-color: goldenrod;}
            </style>
        <script type="text/javascript">     
            //function showdata()
    </script>
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
            <form action="<?php echo basename(__FILE__); ?>" method="GET">
                <fieldset>
                    <legend>Put/Call</legend>
                    <?php
                    //Display put/call value
                    echo '<label><input type="radio" name="putcall" value="put"';
                    echo $putCall == put ? ' checked="checked"' : '';
                    echo '>put</label>'."\n";
                    //Display call value
                    echo '<label><input type="radio" name="putcall" value="call"';
                    echo $putCall == call ? ' checked="checked"' : '';
                    echo '>call</label>'."\n";
                    ?>
                </fieldset>
        </div>
        <div class="padSmall">Ticker Sybol: <input class=""ticker" type="text" name="ticker" value="<?php echo $ticker;?>"/></div>
        <a href="<?php echo basename(__FILE__); ?>" style="display:inline-block">Reset</a>
        <input type="submit" value="Preview" name="submit" />
            </form>
        </div>
        <?php
        //done printing page top.
        
        //Page Body.
        
        //Print from database if the user selected at least one input
        if(count($opId)>0)
        {
            print_download_form();
            
            $query_str .= "LIMIT ".MAX_PREVIEW_ROWS;
            echo '<div id="preview">'."\n";
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
        echo "</div>\n";
    }
    //done printing page body
    
    //done print page bottom
    //
    //nothing yet
    //
    //done printing page bottom
    //
    //
    
           
    //function
    function print_download_form()
    {
        global $ticker, $optType, $mini_option, $ivBid, $ivAsk, $optId;
        
         echo '<div id="downloadForm">'."\n";
    
        echo '<form action="'.basename(__FILE__).'" method="GET">'."\n";
    
        echo ' <input type="hidden" name="optionType" value="'.$optionType.'" />'."\n";
        echo ' <input type="hidden" name="ticker" value="'.ticker.'" />'."\n";
        echo ' <input type="hidden" name="optType" value="'.$optType.'" />'."\n";
        echo ' <input type="hidden" name="minioption" value="'.$mini_option.'" />'."\n";
        echo ' <input type="hidden" name="ivbid" value="'.$ivBid.'" />'."\n";
        echo ' <input type="hidden" name="ivask" value="'.$ivAsk.'" />'."\n";
        echo ' <input type="hidden" name="optId" value="'.$optId.'" />'."\n";
        echo ' <input type="submit" name="submit" value="Download" />'."\n";
    
        echo "</form>\n";
    
        echo "</div>\n";
    }
    //end print_download_form() funtion
    ?>
        
    </body>
</html>
