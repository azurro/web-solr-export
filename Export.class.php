require_once "HTTPClient.class.php";

class Export {

	private $solr_url;
    private $httpclient
	
	public function __construct(){
		parent::__construct();
		$this->solr_url = 'http://127.0.0.1:8983/solr/azurro';
        $this->httpclient = new HTTPClient();
	}

	public function csv() {
        $export_conf = array();
        /*
        * Sample document:
        *  {
        *    "id": "507c7f123ca86cd7994f610e",
        *    "url": "http://prestom.pl/",
        *    "content": "Best shop ever!",
        *  }
        */
        $export_conf['fields'] = array('url' => 'URL', 'content' => 'Description');

        $csv_filename = 'export-'.time().'.csv';
        ini_set('zlib.output_compression','Off');
        header('Content-Type: application/x-download');
        header('Content-Encoding: gzip');
        header('Content-Disposition: attachment; filename='.$csv_filename.'.gz');
        header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
        header('Pragma: no-cache');

        $query_url = $this->solr_url.'/select?q=' . urlencode('*:*') . '&start=0';
        
        $header_line = array('id');
        $query_url .= '&wt=json&sort=id+asc';
        $fields = array('id');
        $query_url .= '&fl=id';
        foreach($export_conf['fields'] as $field => $label) {
            $header_line[] = $label;
            $query_url .= ',' . $field;
            $fields[] = $field;
        }

        $gz_output = fopen("php://output", "wb");
        if ($gz_output !== FALSE) {
            fwrite($gz_output, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF", 10);
            $oname = str_replace("\0", "", $csv_filename);
            fwrite($gz_output, $oname."\0", 1+strlen($oname));
            $fltr = stream_filter_append($gz_output, "zlib.deflate", STREAM_FILTER_WRITE, -1);
            $hctx = hash_init("crc32b");
            if (!ini_get("safe_mode")) set_time_limit(0);

            $cursorMark = '*';
            $rows = 1000; // batch size
            $header_added = FALSE;
            $gz_output_size = 0;
            while($cursorMark !== '') {
                $response = $this->httpclient->get($query_url . '&rows=' . $rows . '&cursorMark=' . $cursorMark);
                $response = json_decode($response['body'], true);
                $docs = $response['response']['docs'];
                if(count($docs) > 0) {
                    $fp = fopen('php://temp', 'r+');
                    if($header_added === FALSE) {
                        fputcsv($fp, $header_line, ',', '"');
                        $header_added = TRUE;
                    }
                    foreach($docs as $doc) {
                        $row = array($doc['id']);
                        foreach($fields as $field) {
                            if(isset($doc[$field])) {
                                $row[] = $doc[$field];
                            } else {
                                $row[] = '';
                            }
                        }
                        
                        fputcsv($fp, $row, ',', '"');
                    }
                    rewind($fp);

                    $con = TRUE;
                    while (($con !== FALSE) && !feof($fp)) {
                        $con = fread($fp, 64 * 1024);
                        if ($con !== FALSE) {
                            hash_update($hctx, $con);
                            $clen = strlen($con);
                            $gz_output_size += $clen;
                            fwrite($gz_output, $con, $clen);
                        }
                    }
                    fclose($fp);
                }
                
                if(isset($response['nextCursorMark']) && $cursorMark !== $response['nextCursorMark']) {
                    $cursorMark = $response['nextCursorMark'];
                } else {
                    $cursorMark = '';
                }
            }

            stream_filter_remove($fltr);
            $crc = hash_final($hctx, TRUE);
            fwrite($gz_output, $crc[3].$crc[2].$crc[1].$crc[0], 4);
            fwrite($gz_output, pack("V", $gz_output_size), 4);
            fclose($gz_output);         
        }
	}
}
