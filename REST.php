<?php

/***************************************************************************************/
/* REST class for use with pulling Rest API data                           			   */
/* This requires the libcurl library www.php.net/manual/en/book.curl.php   			   */
/*                                                                                     */
/* This was specifically written to work with ConnectWise but has been            	   */
/* setup for other uses such as Cisco APIs.                                            */
/*																		   			   */
/* You may edit this to fit your needs                                     			   */
/* Updated by David Blair (3/23/2020)                                      			   */
/* Modified by Aimee Blair (3/20/2022)                                                 */
/***************************************************************************************/

class REST {
	protected $CURL;	
	protected $BASEURL;
	protected $HTTPHEADER;
	protected $ERROR_MESSAGE;
		
	//constructor//
	function __construct($BASE_URL='', $CONTENT_TYPE='', $AUTHORIZATION_TYPE='', $PUBLIC_KEY='', $PRIVATE_KEY='', $API_VERSION='') {
		$this->CURL = curl_init();
		$this->HTTPHEADER = array();
		
		$this->BASEURL = $BASE_URL;
		
		$this->setAuthentication($AUTHORIZATION_TYPE, $PUBLIC_KEY, $PRIVATE_KEY);
		$this->setContentType($CONTENT_TYPE, $API_VERSION);
		
		curl_setopt($this->CURL, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->CURL, CURLOPT_HTTPHEADER, $this->HTTPHEADER);
		curl_setopt($this->CURL, CURLOPT_CONNECTTIMEOUT, 5);
	}
	
	//destructor//
	function __destruct() {
		curl_close($this->CURL);
	}
	
	//factory//
	//ex: $CW = REST::Create('CW');
	public static function create($TYPE, $TOKEN='') {
		$TYPE = strtolower($TYPE);
		switch($TYPE) {
			case 'ff14':
				return new REST('https://xivapi.com', 'JSON', 'BASIC', '', '', '', '');
		}
		
		return null;
	}
	
	function addHeader($ITEM) {
		$this->HTTPHEADER[] = $ITEM;
		curl_setopt($this->CURL, CURLOPT_HTTPHEADER, $this->HTTPHEADER);
	}
	
	function disableSSLVerify() {
		curl_setopt($this->CURL, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->CURL, CURLOPT_SSL_VERIFYHOST, false);
	}
		
	function setAuthentication($HTTPAUTH, $PUBLICKEY, $PRIVATEKEY) {
		switch($HTTPAUTH){
			case 'BASIC':
				curl_setopt($this->CURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				$AUTH = 'Authorization: Basic';
				if($PRIVATEKEY != '') {
					$AUTH .= ' ' . base64_encode($PUBLICKEY . ':' . $PRIVATEKEY);
				}
				$this->HTTPHEADER[] = $AUTH;
				break;
			case 'BEARER':
				$this->HTTPHEADER[] = 'Authorization: Bearer ' . $PRIVATEKEY;
				break;
			default:
				curl_setopt($this->CURL, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		}
	}
	
	function setContentType($CONTENTTYPE, $VERSION) {
		switch($CONTENTTYPE) {
			case 'XML':
				$this->HTTPHEADER[] = 'Accept: application/xml';
				$this->HTTPHEADER[] = 'Content-Type: application/xml';
				break;
			case 'URL':
				$this->HTTPHEADER[] = 'Content-Type: application/x-www-form-urlencoded';
				break;
			case 'JSON':
			default:
				$V = '';
				if(trim($VERSION != '')) {
					$V = '; version=' . $VERSION;
				}
				$this->HTTPHEADER[] = 'Accept: application/json' . $V;
				$this->HTTPHEADER[] = 'Content-Type: application/json';
				break;
		}
	}
	
	function sendFile($URL, $POST_ARRAY) {
		$BOUNDARY = uniqid();
		$DELIMITER = '-------------' . $BOUNDARY;
		
		//convert post array into data//
		$DATA = '';
		$EOL = "\r\n";	
		foreach($POST_ARRAY as $KEY => $CONTENT) {
			$DATA .= "--" . $DELIMITER . $EOL;
			//if $CONTENT is an array then it's a file//
			if(is_array($CONTENT)) {
				//array of file parts - title, data, encoding//
				$DATA .= 'Content-Disposition: form-data; name="' . $KEY . '"; filename="' . $CONTENT[0] . '"' . $EOL;
				$DATA .= 'Content-Type: ' . $CONTENT[2] . $EOL;
				$DATA .= 'Content-Transfer-Encoding: binary' . $EOL;
				$DATA .= $EOL;
				$DATA .= $CONTENT[1] . $EOL;
			}
			else {
				//key => value pairings//
				$DATA .= 'Content-Disposition: form-data; name="' . $KEY . "\"" . $EOL . $EOL . $CONTENT . $EOL;
			}
		}
		$DATA .= "--" . $DELIMITER . "--" . $EOL;
		
		//header swap - we need to pull the current content type and replace it with multipart/form-data and content length//
		$HEADER = $this->HTTPHEADER;
		array_pop($HEADER);
		$HEADER[] = "Content-Type: multipart/form-data; boundary=" . $DELIMITER;
		$HEADER[] = "Content-Length: " . strlen($DATA);
		curl_setopt($this->CURL, CURLOPT_HTTPHEADER, $HEADER);
					   
		//finish setting up the post parameters and send the data//		 	  
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, $DATA);
		curl_setopt($this->CURL, CURLOPT_POST, true);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, false);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, 'POST');
		$RESPONSE = $this->execute();
		
		//change the header back for future interactions//
		curl_setopt($this->CURL, CURLOPT_HTTPHEADER, $this->HTTPHEADER);
		
		return $RESPONSE;
	}
	
	function sendPOST($URL, $POST_ARRAY) {
		//do not json encode if the post_array is really a cisco url encoded string//
		if(is_array($POST_ARRAY)) {
			$POST = json_encode($POST_ARRAY);
		}
		else {
			$POST = $POST_ARRAY;
		}
		
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, $POST);
		curl_setopt($this->CURL, CURLOPT_POST, true);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, false);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, 'POST');
		
		return $this->execute();
	}
	
	function sendPUT($URL, $PUT_ARRAY){
		//do not json encode if the post_array is really an url encoded string//
		if(is_array($PUT_ARRAY)) {
			$PUT = json_encode($PUT_ARRAY);
		}
		else {
			$PUT = $PUT_ARRAY;
		}
		
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, $PUT);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, false);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, 'PUT');
		
		return $this->execute();
	}
	
	function sendGET($URL, $PARAMS='') {
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, null);
		curl_setopt($this->CURL, CURLOPT_POST, false);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, true);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL . $PARAMS);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, 'GET');
		
		return $this->execute();
	}
	
	function sendPATCH($URL, $PATCH_ARRAY) {
		$PATCH = "[" . json_encode($PATCH_ARRAY) . "]"; //must wrap in brackets for deserialize error//	
		
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, null);
		curl_setopt($this->CURL, CURLOPT_POST, false);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, false);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL);
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, $PATCH);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, 'PATCH');
		
		return $this->execute();
	}
	
	function sendDELETE($URL) {
		curl_setopt($this->CURL, CURLOPT_POSTFIELDS, null);
		curl_setopt($this->CURL, CURLOPT_POST, false);
		curl_setopt($this->CURL, CURLOPT_HTTPGET, false);
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL);
		curl_setopt($this->CURL, CURLOPT_CUSTOMREQUEST, "DELETE");
		
		return $this->execute();
	}
	
	function execute() {
		$RESPONSE = curl_exec($this->CURL);
		
		if($RESPONSE === false) {
			$this->ERROR_MESSAGE = 'Curl error: ' . curl_error($this->CURL) . ' | Curl error number: ' . curl_errno($this->CURL);
		}
		
		return $RESPONSE;
	}
	
	function getErrorMessage() {
		return $this->ERROR_MESSAGE;
	}
	
	function downloadFile($FILENAME, $URL, $PARAMS=''){
		curl_setopt($this->CURL, CURLOPT_URL, $this->BASEURL . $URL . $PARAMS);
		curl_setopt($this->CURL, CURLOPT_RETURNTRANSFER, 1 );
		
		curl_setopt($this->CURL, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->CURL, CURLOPT_SSL_VERIFYHOST, false);
	
		file_put_contents($FILENAME, $this->execute());
		
	}
}
	
?>