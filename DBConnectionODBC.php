<?

class DBResult
{
    function __construct($result)
    {
        $this->result = $result;
        $this->num_rows = odbc_num_rows($result);
    }

    function fetch_array()
    {
        return(odbc_fetch_array($this->result));
    }

    function fetch_object()
    {
        return(odbc_fetch_object($this->result));
    }

    function free()
    {
        return(odbc_free_result($this->result));
    }

}


class DBConnection
{
    //----------------------------------------------------------------------------------
    //  DBConnection()
    //
    //   Constructor. Opens the ODBC database connection and checks for errors
    //
    //   NOTE: This connection will automatically be closed when the script that
    //   created it ends
    //
    //  PARAMETERS:
    //    dsn     - The database source name for the connection
    //    user      - database username
    //    pw        - database password
    //
    //  RETURN: none
    //-----------------------------------------------------------------------------------
    function __construct($dsn, $user, $pw)
    {
        // Connect to database and check for errors
        $this->connection = odbc_pconnect($dsn, $user, $pw);
        // check for errors
        if($this->connection == false)
        {
            exit("<br>Error connecting to data.");
        }
    }


    //----------------------------------------------------------------------------------
    //  query()
    //
    //  This function is a wrapper around the odbc_exec() function. It calls
    //  the sqlsrv_query() function and then checks for and reports sql errors
    //
    //  The SQL error message will be sent to the user's browser and/or PHP error log
    //  depending on settings in php.ini. For security reasons, we should *NEVER* send
    //  SQL error messages to the user's browser on a production server. By using this
    //  function to check and report all SQL errors we have a consistent interface that
    //  allows us to send SQL messages to the browser on the development servers and
    //  send SQL messages to the PHP error log on production servers
    //
    //  if $file/$line parameters are missing, they will default to "" and this function
    //  will not check for SQL errors.
    //
    //  PARAMETERS:
    //    sql   - SQL statement
    //    file  - the file where this function was called (use __FILE__)
    //    line  - the line # where this function was called (use __LINE__)
    //
    //  RETURN: TRUE on success or FALSE on failure. For SELECT, SHOW, DESCRIBE or
    //          EXPLAIN query will return an ODBC result object
    //-----------------------------------------------------------------------------------
    function query($sql, $file="", $line="")
    {
        $return = false;
        $this->insert_id = NULL;
        $this->errno = 0;
        $this->error = "";
        // run the query
        $rs = odbc_exec($this->connection, $sql);
        if($rs==false)
        {
            $this->errno = odbc_error($this->connection);
            $this->error = odbc_errormsg($this->connection);
            $return = false;
            if($file!="" && $line!="")
            {
                // query failed, display error message
                trigger_error("SQL Error [" . odbc_error($this->connection) . "] Line $line of " . basename($file) . " : " . odbc_errormsg($this->connection), E_USER_WARNING);
            }
        }
        else
        {
            if(strstr($sql, "INSERT INTO"))
            {
                // This is an insert query, get the identity value (value of key generated by MSSQL)
                if(($idresult = odbc_exec($this->connection, "SET NOCOUNT ON SELECT SCOPE_IDENTITY() AS ID"))==false)
                {
                    trigger_error("Failed to get SCOPE_IDENTITY() on Line $line of " . basename($file), E_USER_ERROR);
                    $return = false;
                }
                else if(($this->insert_id = odbc_result($idresult, "ID"))==NULL)
                {
                    trigger_error("SCOPE_IDENTITY() returned NULL on Line $line of " . basename($file), E_USER_ERROR);
                    $return = false;
                }
                else
                {
                    $return = true;
                }
            }
            else
            {
                // This is a SELECT query, return result object
                $return = new DBResult($rs);
            }
        }
        return $return;
    }


    //----------------------------------------------------------------------------------
    //  DBLookup()
    //
    //  Looks up a value in a table.
    //
    //  PARAMETERS:
    //    field   - field to lookup
    //    table   - table to lookup in
    //    where   - where clause (without the WHERE) to filter results
    //    default - value to return if matching row is found
    //
    //  RETURN: value of "field" in row matching "where" or value of default parameter if
    //          no row matched
    //-----------------------------------------------------------------------------------
    function DBLookup($field, $table, $where, $default="")
    {
        $rs = $this->query("SELECT $field FROM $table WHERE $where", __FILE__, __LINE__);
        if(($record=$rs->fetch_array())==false)
        {
            return($default);
        }
        else
        {
            $rs->free();
            return($record[$field]);
        }
    }


    //----------------------------------------------------------------------------------
    //  DBCount()
    //
    //  Count records in a table.
    //
    //  PARAMETERS:
    //    table   - table to count records
    //    where   - where clause (without the WHERE) to select which records to count
    //
    //  RETURN: count of records
    //-----------------------------------------------------------------------------------
    function DBCount($table, $where)
    {
        $rs = $this->query("SELECT COUNT(*) AS Count FROM $table WHERE $where", __FILE__, __LINE__ );
        if(($record=$rs->fetch_array())==false)
        {
            return(0);
        }
        else
        {
            $rs->free();
            return($record['Count']);
        }
    }



    //----------------------------------------------------------------------------------
    //  DumpToJSArray()
    //
    //  This is used to build javascript arrays used for Ext.ComboBox lookup. The
    //  columns of the recordset will be put into a n x m javascript array
    //  The first column should be the key, the second column should be the string which
    //  will appear in the combo box. Any additional columns are optional. The output
    //  array looks like this:
    //
    //   [[1, 'Apples'],
    //    [2, 'Oranges'],
    //    [3, 'Peaches'],
    //    [4, 'Plums']];
    //
    //  PARAMETERS:
    //    String sql  - SQL statement that will produce an m-column dataset
    //
    //  RETURN: none (output will be echoed directly to HTML)
    //-----------------------------------------------------------------------------------
    function DumpToJSArray($sql)
    {
        $rs = $this->query($sql, __FILE__, __LINE__);
        $result = "[";
    // --- Loop through rows
        while($row = $rs->fetch_array())
        {
        // --- Loop through columns in the row and add each column
            $result .= "[";
            foreach($row as $col)
            {
            // --- Add column value. Surround strings with quotes, leave numbers as is
                if(is_numeric($col))
                {
                    $result .= "$col, ";
                }
                else
                {
                    $col = str_replace("\"", "'", $col);                // replace double quotes with single quotes
                    $col = preg_replace("/(\r\n|[\r\n])/", " ", $col);  // change linebreaks to spaces
                    $result .= "\"$col\", ";                            // surround string with double quotes
                }
            }
            $result = substr($result, 0, -2);   // trim last ", " from string
            $result .= "],\n";
        }
        if(strlen($result) > 1)
        {
            $result = substr($result, 0, -2);   // trim last ",\n" from string
        }
        $result .= "];\n";
        $rs->free();
        Echo $result;
    }


    //----------------------------------------------------------------------------------
    //  RecordActivity()
    //
    //  This function records activity into the activity table.
    //
    //  PARAMETERS:
    //    description   - description of activity (up to 150 characters)
    //    referenceID   - optional reference ID (ex: ID of patient record that was modified)
    //    siteID        - ID of Hospital, Plant, etc logged in.
    //
    //  RETURN: none
    //-----------------------------------------------------------------------------------
    function RecordActivity($description, $referenceID = 0, $siteID = 0)
    {
        if(strlen($description) > 150)
        {
        // limit description to 150 characters. The description field in the activity table is a varchar 150
        // If description is longer than 150 characters MySQL will throw a 'Data too long for column' error.
            $description = substr($description, 0, 150);
        }
        $this->query("INSERT INTO activity (Date, LoginTableID, SiteID, Description, ReferenceID, IPAddress) VALUES (
                      CURRENT_TIMESTAMP, " . $_SESSION['loginTableID'] . ", $siteID, '$description', $referenceID, '{$_SERVER['REMOTE_ADDR']}')",
                      __FILE__, __LINE__);
    }


    //----------------------------------------------------------------------------------
    //  RecordActivityIfOK()
    //
    //  This function records activity into the activity table if there are no database
    //  errors (i.e. the last database operation did not have errors)
    //
    //  PARAMETERS:
    //    description   - description of activity (up to 150 characters)
    //    referenceID   - optional reference ID (ex: ID of patient record that was modified)
    //    siteID        - ID of Hospital, Plant, etc logged in.
    //
    //  RETURN: none
    //-----------------------------------------------------------------------------------
    function RecordActivityIfOK($description, $referenceID = 0, $siteID = 0)
    {
        if($this->errno==0)
        {
            $this->RecordActivity($description, $referenceID, $siteID);
        }
    }

}


//----------------------------------------------------------------------------------
//   PrepareDateForSQL()
//
//   This function takes a date and prepares it to be used in a SQL statement.
//   Date is reformatted as YYYY-MM-DD and surrounded in double-quotes
//   If the date is empty or invalid then NULL is returned.
//
//  PARAMETERS:
//     date   - date value to be fixed up for use in a SQL statement
//
//  RETURN: fixed up date value
//-----------------------------------------------------------------------------------
function PrepareDateForSQL($date)
{
    if(strtoupper($date)=="NULL" || $date=="")
    {
        return("NULL");
    }
    else
    {
        if(strtotime($date)==false)
        {
            return("NULL");
        }
        else
        {
            return("'" . date("Y-m-d", strtotime($date)) . "'");
        }
    }
}


//----------------------------------------------------------------------------------
//   PrepareStringForSQL()
//
//   This function takes a string and prepares it to be used in a SQL statement.
//   Reserved characters are escaped with backslashes and the string is surrounded
//   in double-quotes. If the string is empty then NULL is returned.
//
//  PARAMETERS:
//     str   - string value to be fixed up for use in a SQL statement
//     oDB   - (not used) needed to match signature in DBConnection.php
//
//  RETURN: fixed up string value
//-----------------------------------------------------------------------------------
function PrepareStringForSQL($str, $oDB=null)
{
    if(strtoupper($str)=="NULL" || $str=="")
    {
        return("NULL");
    }
    else
    {
    // --- add backslash prefix to all characters such as single quote, double quote,
    // --- and backslash that need to be escaped in SQL queries
        $str = addslashes($str);
    // --- delimit string with single quotes.
        return("'" . trim($str) . "'");
    }
}
?>