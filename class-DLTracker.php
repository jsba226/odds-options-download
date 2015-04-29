<?php
/**
 * class-DLTracker.php
 * 
 * This class allows us to track the number of downloads per SESSION user.
 * If a user has multiple sessions open simultaneously, this won't slow them
 * down.
 * 
 * Pre-Conditions: session_start() must be called before we construct an instance
 * of this class.
 * 
 * Author: Matthew Denninghoff
 * Date: 4/28/2015.
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

class DLTracker
{
    /**
     * How many seconds are in one time period that we track.
     */
    const TIMEPERIOD_SEC = 60;
    
    /**
     * Name to use in SESSION for flagging an account.
     */
    const FLAGNAME = '_flagged';
    
    /**
     * The name of the SESSION variable under which this instance of DLTracker
     * reads and writes data.
     *
     * @var string 
     */
    private $trackerName;
    
    /**
     * Limit number of downloads per minute.
     *
     * @var int
     */
    private $limit;
    
    /**
     * Constructor.
     * 
     * Pre-Conditions: session_start() must be called.
     * 
     * @param string $trackerName 
     *      Name of session variable to read/write to.
     * @param int $periodLimit
     *      Limit downloads to this number per time period (usu. 1 minute).
     */
    public function __construct($trackerName, $periodLimit = 50)
    {
        $this->trackerName = $trackerName;
        $this->limit = $periodLimit;
        
        if(! isset($_SESSION[$this->trackerName]) || !is_array($_SESSION[$this->trackerName]))
        {
            $_SESSION[$this->trackerName] = array();
        }
        
        // Set up the variable that shows if an account is flagged.
        if( ! isset($_SESSION[$this->trackerName.self::FLAGNAME]))
        {
            $_SESSION[$this->trackerName.self::FLAGNAME] = 0;
        }
    }
    // end constructor.
    
    /**
     * Clear any session data for downloads older than the specified number
     * of seconds. If we don't clear old data, then the _SESSION variable
     * will eventually get filled.
     * 
     * @param int $seconds
     * 
     */
    public function clearOld()
    {
        $now = time();
        
        // Look at each recorded timestamp.
        foreach( $_SESSION[$this->trackerName] as $key => $timestamp )
        {
            // If the difference between the current timestamp and the
            // timestamp of this record is greater than our time period, then
            // clear the recorded timestamp.
            if( $now - $timestamp > self::TIMEPERIOD_SEC )
            {
                unset($_SESSION[$this->trackerName][$key]);
            }
        }
        // done looking at each recorded timestamp.
    }
    // end clearOlderThan().
    
    /**
     * Returns true if the user hasn't downloaded up to their limit.
     * Returns false if the user has downloaded >= this->limit.
     * 
     * Pre-Conditions: this->clearOld() should be called before this, or else
     *      old timestamps are included in the count.
     * 
     * @return boolean
     */
    public function underLimit()
    {
        return (count($_SESSION[$this->trackerName]) < $this->limit);
    }
    // end underLimit().
    
    /**
     * Record the timestamp of a new download in the session data.
     */
    public function recordNew()
    {
        $_SESSION[$this->trackerName][] = time();
    }
    // end recordNew().
    
    /**
     * Mark in the session data that an account is flagged.
     * Downloads should be forbidden when this happens.
     * 
     * @TODO: Record account flag in database in future.
     */
    public function flagAccount()
    {
        $_SESSION[$this->trackerName.self::FLAGNAME] = 1;
    }
    
    /**
     * Returns true if the currently logged-in user's account has been flagged
     * for downloading too much.
     * 
     * @return boolean
     */
    public function isAccountFlagged()
    {
        return ($_SESSION[$this->trackerName.self::FLAGNAME] != 0);
    }
    
}
// end class DLTracker.