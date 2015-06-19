<?php

require_once __DIR__ . '/vendor/autoload.php';
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/vendor/google/apiclient/src');
require_once __DIR__ . '/settings.php';

ini_set('max_execution_time',0);

function _is_numeric($str) {
	$is_numeric = true;
	for($i = 0;$i < strlen($str);$i++) {
		if(!is_numeric(substr($str,$i,1))) {
			$is_numeric = false;
			break;
		}
	}
	return $is_numeric;
}

$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope('https://mail.google.com');

$service = new Google_Service_Gmail($client);

$session_key = 'login';
session_start();

if (isset($_REQUEST['logout'])) {
	unset($_SESSION[$session_key]);
}
if (isset($_GET['code'])) {
	$client->authenticate($_GET['code']);
	$_SESSION[$session_key] = $client->getAccessToken();
	$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
	header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}
if (isset($_SESSION[$session_key]) && $_SESSION[$session_key]) {
	$client->setAccessToken($_SESSION[$session_key]);
	if ($client->isAccessTokenExpired()) {
		unset($_SESSION[$session_key]);
	}
} else {
	$authUrl = $client->createAuthUrl();
?>
<a href='<?php echo addslashes($authUrl); ?>'>Login!</a>
<?php
}

try {
	if ($client->getAccessToken()) {
		$optParams = array();
		$optParams['maxResults'] = 200;
		$optParams['labelIds'] = 'SPAM';
		$t1 = microtime(true);
		$messages = $service->users_messages->listUsersMessages('me',$optParams);
		$t2 = microtime(true);
		$list = $messages->getMessages();

		header('Content-type:text/plain');

		foreach($list as $_list) {
			$messageId = $_list->getId();
			$optParamsGet = array();
			$optParamsGet['format'] = 'full';
			$message = $service->users_messages->get('me',$messageId,$optParamsGet);

			$messagePayload = $message->getPayload();
			$headers = $message->getPayload()->getHeaders();
			$parts = $message->getPayload()->getParts();

			$do_trash = false;
			echo $messageId;
			foreach($headers as $header) {
				if($header->name == 'Subject') {
					echo "|{$header->name}|{$header->value}";
					// check if subject ends with reference number
					$explode = explode(' ',$header->value);
					$last = $explode[count($explode) - 1];
					if(_is_numeric($last) && $last > 0 && ($last < (date('Y') - 3) || $tmp > (date('Y') + 5))) {
						$do_trash = true;
						break;
					}
					$possible_prefixes = array('#','--','Ref.','-','No.');
					foreach($possible_prefixes as $possible_prefix) {
						$tmp = substr($last,strlen($possible_prefix));
						if(_is_numeric($tmp) && $tmp > 0 && ($tmp < (date('Y') - 3) || $tmp > (date('Y') + 5))) {
							$do_trash = true;
							break 2;
						}
					}
					$back_index = 1;
					for(;$back_index < min(strlen($last),5) && _is_numeric(substr($last,-$back_index));$back_index++) {
					}
					if(_is_numeric(substr($last,-$back_index)) && substr($last,-$back_index) > 0 && (substr($last,-$back_index) < (date('Y') - 3) || substr($last,-$back_index) > (date('Y') + 5))) {
						$do_trash = true;
						break;
					}
				}
			}

		    if($do_trash) {
				echo "|trashed\n";
		    	$service->users_messages->trash('me', $messageId);
		    } else {
		    	echo "|skipped\n";
		    }
		    flush();
		}
	}
} catch(Exception $e) {
	$authUrl = $client->createAuthUrl();
?>
<a href='<?php echo addslashes($authUrl); ?>'>Login!</a>
<?php
}
