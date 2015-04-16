<?php
/*
 * File: class-MysqlResultTable.php
 * Author: Matthew Denninghoff.
 * 
 * This class gives simple, standard way to print mysql query results as
 * a html table or as CSV text.
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

class MysqlResultTable extends Tableset
{
    /**
     * This holds the mysql query result object.
     *
     * @var mixed
     */
    protected $query_result;
    
    /**
     * If the query failed, this holds the string result of mysql_error().
     *
     * @var string
     */
    protected $error_message;
    
    /**
     * Holds the raw SQL query string that was used.
     *
     * @var string
     */
    protected $query_string;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->query_result = false;
        $this->error_message = null;
        $this->query_string = '';
    }
    // end __construct().
    
    /**
     * Execute a query and store the results as a 2D array in this->data.
     * 
     * Post-Conditions: These fields are modified:
     * data, num_cols, num_rows, column_types, footer, column_names,
     * query_string.
     * 
     * Upon error, false is returned, and error_message is set.
     * 
     * @param string $queryString
     * 
     * @return boolean Returns false if the query failed, true otherwise.
     */
    public function executeQuery($queryString)
    {
        $this->query_string = $queryString;
        
        // Run query.
        $this->query_result = mysql_query($this->query_string);
        
        if( ! $this->query_result )
        {
            $this->error_message = mysql_error();
            return false;
        }
        
        $this->data = array();

        //
        //  Handle good query result.
        //
        $this->num_rows = mysql_num_rows($this->query_result);
        $this->num_cols = mysql_num_fields($this->query_result);

        // Get the field names and types for each column.
        // These values may be overridden later.
        for($i=0; $i < $this->num_cols; $i++)
        {
            $this->column_names[$i] = mysql_field_name($this->query_result, $i);
            $this->column_types[$i] = mysql_field_type($this->query_result, $i);
        }
        
        // Fetch the result data.
        while( $row = mysql_fetch_array($this->query_result))
        {
            $this->data[] = $row;
        }
        
        // Set a default footer string: the number of rows.
        $this->footer = $this->num_rows . ' rows';

        // done handling good query result.
        return true;
    }
    // end executeQuery().
    
    public function get_query_string()
    {
        return $this->query_string;
    }
    
    public function get_error_message()
    {
        return $this->error_message;
    }
}
// end class MysqlResultTable.
