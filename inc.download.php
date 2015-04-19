<?php
/* 
 * Author: Matthew Denninghoff
 * Date: 4/18/2015
 * 
 * Define commonly used constants and functions here. Those commonly used are
 * for the downloads project; e.g. Download Volatility Surface Data, Download
 * Issuer Data, and Download Individual Option Data.
 * 
 * Also attempts database connection.
 * 
 * Post-Conditions: Constants are set, Error reporting is set, database is
 * connected or script exited, default timezone is set, tableset classes
 * are included, and print_download_form() is defined.
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

// For debugging, show all errors.
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// The limit on the number of database rows to display in a result.
const MAX_PREVIEW_ROWS = 100;

// The limit on the number of database rows to display in a download.
const MAX_DOWNLOAD_ROWS = 5000;

// Connect to the database or die. MD.
require('./dbconnect.php');

// Library for displaying nice tables.
require('./class-Tableset.php');
require('./class-TablesetDefaultCSS.php');
require('./class-MysqlResultTable.php');

// (Certain installations of PHP print warnings if default timezone is not set.)
date_default_timezone_set(TIMEZONE_DEFAULT);


/**
 * Reprint the submitted form as another form with hidden values selected.
 * This makes the downloaded data consistent with whatever data is previewed
 * in the browser.
 * 
 * 
 * @global int $dataType
 * @global array $volTypes
 * @global string $startDate
 * @global string $endDate
 * @global int $moneyness
 * @global string $ticker
 * @global int $eqID The Equity ID chosen.
 */
function print_download_form()
{
    global $dataType, $volTypes, $startDate, $endDate, $moneyness, $ticker, $eqID;
    
    echo '<div id="downloadForm">'."\n";
    
    echo '<form action="'.basename($_SERVER['SCRIPT_NAME']).'" method="GET">'."\n";
    
    echo ' <input type="hidden" name="dataType" value="'.$dataType.'" />'."\n";
    
    if( isset($volTypes))
    {
        foreach($volTypes as $val)
        {
            echo ' <input type="hidden" name="volType[]" value="'.$val.'" />'."\n";
        }
    }
    
    echo ' <input type="hidden" name="startDate" value="'.$startDate.'" />'."\n";
    echo ' <input type="hidden" name="endDate" value="'.$endDate.'" />'."\n";
    
    if( isset($moneyness))
        echo ' <input type="hidden" name="moneyness" value="'.$moneyness.'" />'."\n";
    
    if( isset($eqID))
        echo ' <input type="hidden" name="eqID" value="'.$eqID.'" />'."\n";
    
    echo ' <input type="hidden" name="ticker" value="'.$ticker.'" />'."\n";

    echo ' <input type="submit" name="submit" value="Download" />'."\n";
    
    echo "</form>\n";
    
    echo "</div>\n";
}
// end print_download_form().

/**
 * Remove anything that isn't alphanumeric, period, $, /, space, or dash.
 * Also, make string uppercase.
 * 
 * This function can be used for use as a callback for filter_input.
 * 
 * Example for filter_input callback on $_GET['ticker']:
 * $ticker = filter_input(INPUT_GET, 'ticker', FILTER_CALLBACK,
 *                        array('options' => 'ticker_sanitize') );
 * 
 * @param string $value
 * @return string
 */
function ticker_sanitize($value)
{
    // Remove anything that isn't alphanumeric, period, $, /, space, or dash.
    return strtoupper( preg_replace('@[^a-z0-9\.\$\/ \-!]@i', '', $value) );
}

/**
 * Validate that user-submitted date value is a date, and replace $date with
 * a formatted date string if so.
 * 
 * If there is no _GET or _POST variable set, then nothing happens.
 * 
 * Requires PHP >= 5.2.0 for filter_input functions.
 * 
 * @param int $type Determines whether to check _GET or _POST for the date value.
 * Use either INPUT_GET or INPUT_POST.
 * 
 * @param string $keyName A string array key to reference either a _GET or a 
 * _POST value.
 * 
 * @param string $date Passed by reference, a variable containing a date string.
 *   If the user-submitted date, _GET[$keyName] or _POST[$keyName], is valid,
 *   then that value overwrites whatever is in $date.
 * 
 * @param string[] $errors A reference to an array of strings. The date value
 * was invalid, then $errors gets a new entry describing the error.
 */
function parse_date_entry($type, $keyName,  &$date, &$errors )
{
    // Get either _GET[$keyName] or _POST[$keyName], depending on $type.
    $val = filter_input($type, $keyName);
    
    // See if the specified _GET or _POST value existed.
    if( $val !== null )
    {
        // See if the date string matches a certain format.
        if(preg_match(PREG_DATE_YYYYMMDD, $val))
        {
            // Only use the date if it was a real date.
            if(strtotime($val) !== false)
            {
                $date = $val;
            }else{
                $errors[] = 'The '.$keyName.' you submitted, '.$val.', is not a real date.';
            }
        }
        else
        {
            $errors[] = 'The endDate submitted must be in the format YYYY-mm-dd.';
        }
    }
    // done parsing endDate.
}
// end parse_date_entry().