<?php
/*
 * File: class-MysqlResultTable.php
 * Author: Matthew Denninghoff.
 * 
 * This class gives simple, standard way to print mysql query results as
 * a html table.
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

class MysqlResultTable
{
    
    protected $query_result;
    
    protected $num_rows;
    protected $num_cols;
    
    /**
     * Array to hold column names. Array index corresponds to column numbers.
     * With a successful query, each column will have a name value in this array.
     *
     * @var array
     */
    protected $column_names;
    
    /**
     * Array to hold column types. Array index corresponds to column numbers.
     * With a successful query, each column will have a name value in this array.
     *
     * @var array
     */
    protected $column_types;
    
    // Specify width value for the td tag. Not every index is set.
    protected $column_widths;
    
    // Swap values in the query result with these. Not every index is set.
    protected $column_value_map;
    
    protected $error_message;
    public $query_string;
    
    // String to print inside the table caption tag.
    // The default value of null prevents the caption tag from being output.
    public $caption;
    
    // String to print inside the tfoot.
    // The default value of null prevents table footer from being output.
    public $footer;
    
    /**
     * Flag. When true, a column with row numbers is output along with the
     * query results.
     *
     * @var boolean
     */
    protected $show_row_numbers;
    
    // String names of the different cell data types this class recognizes.
    // Used for css formatting.
    const TYPE_STRING = 'string';
    const TYPE_INT    = 'int';
    const TYPE_REAL   = 'real';
    
    /**
     * Class constructor. The query is executed in this function.
     */
    public function __construct( $queryString )
    {
        $this->num_cols = 0;
        $this->num_rows = 0;
        $this->query_result = false;
        $this->caption = null;
        $this->footer = null;
        $this->column_names = array();
        $this->column_types = array();
        $this->column_widths = array();
        $this->column_value_map = array();
        $this->column_widths = array();
        $this->show_row_numbers = true;
        
        $this->query_string = $queryString;

        // Run query.
        $this->query_result = mysql_query($queryString);
        
        if( ! $this->query_result )
        {
            $this->error_message = mysql_error();
        }
        else
        {
            $this->num_rows = mysql_num_rows($this->query_result);
            $this->num_cols = mysql_num_fields($this->query_result);

            // Get the field names and types for each column.
            // These values may be overridden.
            for($i=0; $i < $this->num_cols; $i++)
            {
                $this->column_names[$i] = mysql_field_name($this->query_result, $i);
                $this->column_types[$i] = mysql_field_type($this->query_result, $i);

                // Default to null. Set this 
                $this->column_value_map[$i] = null;
            }
        }
        // done handling good query result.
    }
    // end __construct().
    
    /**
     * Pre-Conditions: The query string should be a valid SELECT query.
     * 
     * 
     * @param string $queryString
     * @return boolean
     * Returns false if the query failed, otherwise returns true.
     */
//    public function execute($queryString )
//    {
//        $this->query_string = $queryString;
//
//        // Run query.
//        $this->query_result = mysql_query($queryString);
//        
//        if( ! $this->query_result )
//        {
//            $this->error_message = mysql_error();
//            return false;
//        }
//        
//        $this->num_rows = mysql_num_rows($this->query_result);
//        $this->num_cols = mysql_num_fields($this->query_result);
//        
//        // Get the field names and types for each column.
//        // These values may be overridden.
//        for($i=0; $i < $this->num_cols; $i++)
//        {
//            $this->column_names[$i] = mysql_field_name($this->query_result, $i);
//            $this->column_types[$i] = mysql_field_type($this->query_result, $i);
//            
//            // Default to null. Set this 
//            $this->column_value_map[$i] = null;
//        }
//        
//        return true;
//    }
    // end execute().
    
    public function print_table_string()
    {
        // Only print if the query succeeded.
        if ($this->query_result)
        {
            echo '<table class="resultSet">'."\n"
            . ($this->caption ? ' <caption>'.$this->caption.'</caption>' . "\n" : '')
            . " <thead><tr>\n";
            
            // Print the column heading for row numbers.
            if( $this->show_row_numbers)
            {
                echo "  <th>&nbsp;</th>\n";
            }

            // Print column headers for each column.
            for($col=0; $col < $this->num_cols; $col++)
            {
                echo '  <th';
                if( isset($this->column_widths[$col]))
                {
                    echo ' width="'.$this->column_widths[$col].'"';
                }
                echo '>'.$this->column_names[$col]."</th>\n";
            }
            // done printing column headers.

            echo " </tr></thead>\n";
            
            // Print the table footer.
            if( $this->footer )
            {
                $span = $this->num_cols;
                if( $this->show_row_numbers ) $span += 1;
                echo ' <tfoot><tr><td colspan="'.$span.'">'.$this->footer.'</td></tr></thead>'."\n";
            }
            
            echo " <tbody>\n";

            // Fetch each result row and print it.
            $rowCnt = 1;
            while ($row = mysql_fetch_array($this->query_result))
            {
                echo "  <tr>\n";
                
                // Print the row number and increment the counter.
                if( $this->show_row_numbers)
                {
                    echo '   <td class="rowNo">'. $rowCnt++ . "</td>\n";
                }
                
                // Fetch each column value in this row.
                for($col=0; $col < $this->num_cols; $col++)
                {
                    $val = $row[$col];
                    
                    // See if there exists a mapping to swap out the query
                    // value with a more descriptive value.
                    if( is_array($this->column_value_map[$col]) && isset($this->column_value_map[$col][$val]) )
                    {
                        $val = $this->column_value_map[$col][$val];
                    }
                    
                    echo '   <td class="'. $this->column_types[$col] . '">'.$val . "</td>\n";
                }
                // done printing each column in this row.
                
                echo "  </tr>\n";
            }
            // done fetching each result row.
            
            echo "</tbody></table>\n";
        }
        // end if resultset was good.
        // otherwise, print the error message.
        else
        {
            echo '<p class="resultSet">' . $this->error_message . "</p>\n";
        }
    }
    // end print_table_string().
    
    /**
     * Sets a column name. Returns false if the column number was out of bounds.
     * 
     * @param int $colNo
     * @param string $name
     * @return boolean
     */
    public function set_column_name($colNo, $name)
    {
        if( ! $this->column_exists($colNo))
            return false;
        
        $this->column_names[$colNo] = $name;
        
        return true;
    }
    // end set_column_name().
    
    /**
     * Sets a column type. Returns false if the column number was out of bounds.
     * Type should be 
     * 
     * @param int $colNo
     * @param string $type
     * @return boolean
     */
    public function set_column_type($colNo, $type)
    {
        if( ! $this->column_exists($colNo))
            return false;
        
        $this->column_types[$colNo] = $type;
        
        return true;
    }
    // end set_column_name().
    
    /**
     * Sets a column width. Returns false if the column number was out of bounds.
     * Width goes into the TH tag and should be of the form "10%" or "200px".
     * 
     * @param int $colNo
     * @param string $val
     * @return boolean
     */
    public function set_column_width($colNo, $val)
    {
        if( ! $this->column_exists($colNo))
            return false;
        
        $this->column_widths[$colNo] = $val;
        
        return true;
    }
    // end set_column_name().
    
    /**
     * Sets a column value map, which should be an associative array.
     * The array keys should match some value in a result cell, and the
     * array values are printed instead of the original cell's data.
     * 
     * Returns false if the column number was out of bounds or if $val
     * was no an array.
     * 
     * @param int $colNo
     * @param string $val
     * @return boolean
     */
    public function set_column_value_map($colNo, $val)
    {
        if( ! $this->column_exists($colNo))
            return false;
       
        if(! is_array($val))
            return false;
        
        $this->column_value_map[$colNo] = $val;
        
        return true;
    }
    // end set_column_name().
    
    /**
     * Returns false if the column number was out of bounds; true otherwise.
     * 
     * @param int $colNo
     * @return boolean
     */
    protected function column_exists($colNo)
    {
        if( $colNo < 0 || $colNo >= $this->num_cols)
            return false;
        return true;
    }
    
    /**
     * Set the flag to show or hide the column containing row numbers.
     * 
     * @param boolean $show
     */
    public function showhide_row_numbers($show)
    {
        if( $show )
        {
            $this->show_row_numbers = true;
        }
        else
        {
            $this->show_row_numbers = false;
        }
    }
    // end showhide_row_numbers().

}
// end class MysqlResultTable.
