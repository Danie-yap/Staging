<?php
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error message to be escaped when caught and printed.

/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * PHP version 5
 *
 * @category  Microsoft
 * @package   WindowsAzure\Common\Internal
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      https://github.com/windowsazure/azure-sdk-for-php
 */
 
namespace WindowsAzure\Common\Internal;

/**
 * Helper methods for parsing connection strings. The rules for formatting connection
 * strings are defined here:
 * www.connectionstrings.com/articles/show/important-rules-for-connection-strings
 *
 * @category  Microsoft
 * @package   WindowsAzure\Common\Internal
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version   Release: 0.4.1_2015-03
 * @link      https://github.com/windowsazure/azure-sdk-for-php
 */
class ConnectionStringParser
{
    /**
     * @var string
     */
    private $_argumentName;
    
    /**
     * @var string
     */
    private $_value;
    
    /**
     * @var integer
     */
    private $_pos;
    
    /**
     * @var string
     */
    private $_state;
    
    /**
     * Parses the connection string into a collection of key/value pairs.
     * 
     * @param string $argumentName     Name of the argument to be used in error 
     * messages.
     * @param string $connectionString Connection string.
     * 
     * @return array
     * 
     * @static
     */
    public static function parseConnectionString($argumentName, $connectionString)
    {
        Validate::isString($argumentName, 'argumentName');
        Validate::notNullOrEmpty($argumentName, 'argumentName');
        Validate::isString($connectionString, 'connectionString');
        Validate::notNullOrEmpty($connectionString, 'connectionString');
        
        $parser = new ConnectionStringParser($argumentName, $connectionString);
        return $parser->_parse();
    }
    
    /**
     * Initializes the object.
     * 
     * @param string $argumentName Name of the argument to be used in error 
     * messages.
     * @param string $value        Connection string.
     */
    private function __construct($argumentName, $value)
    {
        $this->_argumentName = $argumentName;
        $this->_value        = $value;
        $this->_pos          = 0;
        $this->_state        = ParserState::EXPECT_KEY;
    }
    
    /**
     * Parses the connection string.
     * 
     * @return array
     * 
     * @throws \RuntimeException
     */
    private function _parse()
    {
        $key                    = null;
        $value                  = null;
        $connectionStringValues = array();
        
        while (true) {
            $this->_skipWhiteSpaces();
            
            if (   $this->_pos == strlen($this->_value)
                && $this->_state != ParserState::EXPECT_VALUE
            ) {
                // Not stopping after the end has been reached and a value is 
                // expected results in creating an empty value, which we expect.
                break;
            }
            
            switch ($this->_state) {
            case ParserState::EXPECT_KEY:
                $key          = $this->_extractKey();
                $this->_state = ParserState::EXPECT_ASSIGNMENT;
                break;
            
            case ParserState::EXPECT_ASSIGNMENT:
                $this->_skipOperator('=');
                $this->_state = ParserState::EXPECT_VALUE;
                break;
            
            case ParserState::EXPECT_VALUE:
                $value                        = $this->_extractValue();
                $this->_state                 = ParserState::EXPECT_SEPARATOR;
                $connectionStringValues[$key] = $value;
                $key                          = null;
                $value                        = null;
                break;
            
            default:
                $this->_skipOperator(';');
                $this->_state = ParserState::EXPECT_KEY;
                break;
            }
        }
        
        // Must end parsing in the valid state (expected key or separator)
        if ($this->_state == ParserState::EXPECT_ASSIGNMENT) {
            throw $this->_createException(
                $this->_pos,
                Resources::MISSING_CONNECTION_STRING_CHAR,
                '='
            );
        }
        
        return $connectionStringValues;
    }
    
    /**
     *Generates an invalid connection string exception with the detailed error 
     * message.
     * 
     * @param integer $position    The position of the error.
     * @param string  $errorString The short error formatting string.
     * 
     * @return \RuntimeException
     */
    private function _createException($position, $errorString)
    {
        $arguments = func_get_args();
        
        // Remove first argument (position)
        unset($arguments[0]);
        
        // Create a short error message.
        $errorString = sprintf($errorString, $arguments);
        
        // Add position.
        $errorString = sprintf(
            Resources::ERROR_PARSING_STRING,
            $errorString,
            $position
        );
        
        // Create final error message.
        $errorString = sprintf(
            Resources::INVALID_CONNECTION_STRING,
            $this->_argumentName,
            $errorString
        );
        
        return new \RuntimeException($errorString);
    }
    
    /**
     * Skips whitespaces at the current position.
     * 
     * @return none
     */
    private function _skipWhiteSpaces()
    {
        while (   $this->_pos < strlen($this->_value)
              &&  ctype_space($this->_value[$this->_pos])
        ) {
            $this->_pos++;
        }
    }
    
    /**
     * Extracts the key's value.
     * 
     * @return string 
     */
    private function _extractValue()
    {
        $value = Resources::EMPTY_STRING;
        
        if ($this->_pos < strlen($this->_value)) {
            $ch = $this->_value[$this->_pos];
            
            if ($ch == '"' || $ch == '\'') {
                // Value is contained between double quotes or skipped single quotes.
                $this->_pos++;
                $value = $this->_extractString($ch);
            } else {
                $firstPos = $this->_pos;
                $isFound  = false;
                
                while ($this->_pos < strlen($this->_value) && !$isFound) {
                    $ch = $this->_value[$this->_pos];
                    
                    if ($ch == ';') {
                        $isFound = true;
                    } else {
                        $this->_pos++;
                    }
                }
                
                $value = rtrim(
                    substr($this->_value, $firstPos, $this->_pos - $firstPos)
                );
            }
        }
        
        return $value;
    }
    
    /**
     * Extracts key at the current position.
     * 
     * @return string 
     */
    private function _extractKey()
    {
        $key      = null;
        $firstPos = $this->_pos;
        $ch       = $this->_value[$this->_pos];
        
        if ($ch == '"' || $ch == '\'') {
            $this->_pos++;
            $key = $this->_extractString($ch);
        } else if ($ch == ';' || $ch == '=') {
            // Key name was expected.
            throw $this->_createException(
                $firstPos,
                Resources::ERROR_CONNECTION_STRING_MISSING_KEY
            );
        } else {
            while ($this->_pos < strlen($this->_value)) {
                $ch = $this->_value[$this->_pos];
                
                // At this point we've read the key, break.
                if ($ch == '=') {
                    break;
                }
                
                $this->_pos++;
            }
            $key = rtrim(substr($this->_value, $firstPos, $this->_pos - $firstPos));
        }
        
        if (strlen($key) == 0) {
            // Empty key name.
            throw $this->_createException(
                $firstPos,
                Resources::ERROR_CONNECTION_STRING_EMPTY_KEY
            );
        }
        
        return $key;
    }
    
    /**
     * Extracts the string until the given quotation mark.
     * 
     * @param string $quote The quotation mark terminating the string.
     * 
     * @return string
     */
    private function _extractString($quote)
    {
        $firstPos = $this->_pos;
        
        while (   $this->_pos < strlen($this->_value)
              &&  $this->_value[$this->_pos] != $quote
        ) {
            $this->_pos++;
        }
        
        if ($this->_pos == strlen($this->_value)) {
            // Runaway string.
            throw $this->_createException(
                $this->_pos,
                Resources::ERROR_CONNECTION_STRING_MISSING_CHARACTER,
                $quote
            );
        }
        
        return substr($this->_value, $firstPos, $this->_pos++ - $firstPos);
    }
    
    /**
     * Skips specified operator.
     * 
     * @param string $operatorChar The operator character.
     * 
     * @return none
     * 
     * @throws \RuntimeException
     */
    private function _skipOperator($operatorChar)
    {
        if ($this->_value[$this->_pos] != $operatorChar) {
            // Character was expected.
            throw $this->_createException(
                $this->_pos,
                Resources::MISSING_CONNECTION_STRING_CHAR,
                $operatorChar
            );
        }
        
        $this->_pos++;
    }
}

/**
 * State of the connection string parser.
 *
 * @category  Microsoft
 * @package   WindowsAzure\Common\Internal
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version   Release: 0.4.1_2015-03
 * @link      https://github.com/windowsazure/azure-sdk-for-php
 */
class ParserState
{
    const EXPECT_KEY        = 'ExpectKey';
    const EXPECT_ASSIGNMENT = 'ExpectAssignment';
    const EXPECT_VALUE      = 'ExpectValue';
    const EXPECT_SEPARATOR  = 'ExpectSeparator';
}