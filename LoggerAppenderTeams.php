<?php
/**
 * log4php is a PHP port of the log4j java logging package.
 * 
 * <p>This framework is based on log4j (see {@link http://jakarta.apache.org/log4j log4j} for details).</p>
 * <p>Design, strategies and part of the methods documentation are developed by log4j team 
 * (Ceki G�lc� as log4j project founder and 
 * {@link http://jakarta.apache.org/log4j/docs/contributors.html contributors}).</p>
 *
 * <p>PHP port, extensions and modifications by VxR. All rights reserved.<br>
 * For more information, please see {@link http://www.vxr.it/log4php/}.</p>
 *
 * <p>This software is published under the terms of the LGPL License
 * a copy of which has been included with this distribution in the LICENSE file.</p>
 * 
 * @package log4php
 * @subpackage appenders
 */
require_once("libraries/HTTP_Session2/HTTP/Session2.php");

/**
 * @ignore 
 */
if (!defined('LOG4PHP_DIR')) define('LOG4PHP_DIR', dirname(__FILE__) . '/..');

/**
 */
require_once(LOG4PHP_DIR . '/LoggerAppenderSkeleton.php');
require_once(LOG4PHP_DIR . '/LoggerLog.php');

/**
 * Log events to an email address. It will be created an email for each event. 
 *
 * <p>Parameters are 
 * {@link $smtpHost} (optional), 
 * {@link $port} (optional), 
 * {@link $from} (optional), 
 * {@link $to}, 
 * {@link $subject} (optional).</p>
 * <p>A layout is required.</p>
 *
 * @author Domenico Lordi <lordi@interfree.it>
 * @author VxR <vxr@vxr.it>
 * @version $Revision: 1.10 $
 * @package log4php
 * @subpackage appenders
 */
class LoggerAppenderTeams extends LoggerAppenderSkeleton {

    /**
     * @var string 'subject' field
     */
    var $subject        = '';

    /**
     * @access private
     */
    var $requiresLayout = true;

	var $url = 'https://teams.microsoft.com/l/channel/19%3a697072e40655404b8564096eb5ecab0b%40thread.skype/CRM%2520LIVE?groupId=20b65459-8fa5-4516-b6c6-d1965d42ac24&tenantId=ff483cd5-a97f-41cf-9a04-ac3ca1a30f55'; 
	
    /**
     * Constructor.
     *
     * @param string $name appender name
     */
    function LoggerAppenderTeams($name) { $this->LoggerAppenderSkeleton($name); }

    function activateOptions() { $this->closed = false; }
    
    function close() { $this->closed = true; }

	function setSubject($subject) { $this->subject = $subject; }

    function getSubject() { return $this->subject; }
	
    function append($event){
		global $current_user;
		
		include_once 'include/Webservices/Utils.php';

		$backtrace = debug_backtrace(2,15); 
		foreach ($backtrace as $row) { 
			if ( strpos($row['file'], 'libraries/log4php.debug') === false ) { 
				$backtraceAttachment[] = [
					'name' => str_replace($GLOBALS['root_directory'], '',$row['file']) , 
					'value' => ' line('.$row['line'].')'.' function('.$row['function'].')'
									.' args('.implode(', ', $row['args']).')' 
				]; 
			}
		}
		
		$rows = debug_backtrace(2,6); 
		$backtrace = end($rows); 
		
		// create card
		$data = [
        "@type" => "MessageCard",
        "@context" => "http://schema.org/extensions",
        "summary" => "Forge Card",
        "themeColor" => 'FF0000',
        "title" => $this->layout->format($event),
        "sections" => [
            [
                "activityTitle" => "User",
				'startGroup' => true, 
                "facts" => [
                    ["name" => "username",
                     "value" => !empty($current_user->user_name) ? 
								$current_user->user_name : $GLOBALS['current_user']->user_name],
                    ["name" => "id",
                     "value" => !empty($GLOBALS['current_user']->id) ? 
								$GLOBALS['current_user']->id : HTTP_Session2::get('authenticatedUserId')],
					["name" => "roleid",
                        "value" => !empty($current_user->roleid) ? 
								$current_user->roleid : $GLOBALS['current_user']->roleid],
            ],[
				"activityTitle" => "App",
				'startGroup' => true, 
                "facts" => [
                    ["name" => "Current module",
                     "value" => $GLOBALS['currentModule']],
                    ["name" => "View",
                     "value" => $_REQUEST['view']],
					["name" => "php_errormsg",
                     "value" => !$GLOBALS['php_errormsg']],
                    ["name" => "Language",
                     "value" => $GLOBALS['current_language']],
					['name' => 'Referer', 
					 'value' => $_SERVER['HTTP_REFERER']],	
					['name' => 'URL', 
					 'value' => $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']], 
					['name' => 'File', 
					 'value' => $backtrace['file']], 
					['name' => 'Line',
					 'value' => $backtrace['line']]],
			],[
				"activityTitle" => "Backtrace",
				'startGroup' => true, 
                "facts" => $backtraceAttachment
			],
        ]
    ];
		
		$operation = vtws_getParameter($_REQUEST, "operation");
		
		if (!empty($operation)){ 
			// ------------------------
			$value = ''; 
    
			if (!empty($_REQUEST['elementType']) ) { 
				$value .= 'Module: '. $_REQUEST['elementType'].' ';  
			}
			if ( !empty($_REQUEST['element']) ) { 

				if ( is_array( $_REQUEST['element'] ) ){ 
					$value .= print_r($_REQUEST['element'], True).' ';
				}else {
					$value .= $_REQUEST['element'].' ';
				}
			} else if ( !empty($_REQUEST['query']) ) { 
				$value .= $_REQUEST['query']; 
			}
			$data['sections'][] = [
				'activityTitle' => 'Webservice', 
				'facts' => [
					['name' => 'Operation', 
					 'value' => $operation],
					['name' => 'Query', 
					 'value' => $value]
				]]; 
		}
		
		if ( !empty($GLOBALS['sql_errors_log']) ) { 
			
			foreach($GLOBALS['sql_errors_log'] as $error){ 
				$fields[] = array( 'name' => 'Error', 
								   'value' => print_r($error, true));  
			}
			$data['sections'][] = [
				'activityTitle' => 'Webservice', 
				'facts' => $fields
			];
		}
	
		$result = $this->send($data);
    }
	
	
	public function send($data)
    {
        $json = json_encode($data);

        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);
        return curl_exec($ch);
    }
}
?>
