/**
 * Simple HTTP client
 */
class HTTPClient {

	private $curl_opts = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 1, CURLOPT_HEADER => true);
	private $base_url;
	
	public function __construct($base_url = '') {
		if (! function_exists ( 'curl_init' )) {
			throw new Exception ( 'CURL module not available!' );
		}
		$this->base_url = $base_url;
	}
	
	public function get($url, $headers = array()) {
		$curl_opts = $this->curl_opts;
		$curl_opts[CURLOPT_HTTPHEADER] = $headers;
		$curl = $this->prepRequest($curl_opts, $url);
		return $this->doRequest($curl);
	}
	
	private function prepRequest($opts, $url) {
		if (strncmp ( $url, $this->base_url, strlen ( $this->base_url ) ) != 0) {
			$url = $this->base_url . $url;
		}
		$curl = curl_init ( $url );
		curl_setopt_array ( $curl, $opts );
		return $curl;
	}
	
	private function doRequest($curl) {
		$response = curl_exec($curl);
		$meta = curl_getinfo($curl);
		
		$header_length = $meta['header_size'];
		$raw_header = substr($response, 0, $header_length);
		
		$response_headers = $this->parseRawHeader($raw_header);
		
		$body = null;
		if (strlen($response) > $header_length) {
			$body = substr($response, $header_length);
		}
		
		$response = array (
				'header' => $response_headers,
				'body' => $body,
				'meta' => $meta 
		);
		
		curl_close($curl);
		return $response;
	}
	
	private function parseRawHeader($raw_header) {
		$raw_header_tokens = explode("\n", $raw_header);
		$header = null;
		foreach($raw_header_tokens as $raw_header_token) {
			if (preg_match("/[a-zA-Z_\-0-9]+: [\S ]+/", $raw_header_token) == 1) {
				$header_tokens = explode(": ", $raw_header_token);
				$header[$header_tokens[0]] = $header_tokens[1];
			}
		}
		return $header;
	}
}
?>
