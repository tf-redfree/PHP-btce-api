<?php
/**
 * API-call related functions
 *
 * @author marinu666
 * @license MIT License - https://github.com/marinu666/PHP-btce-api
 */
class BTCeAPI {
    
    const DIRECTION_BUY = 'buy';
    const DIRECTION_SELL = 'sell';
    protected $public_api = 'https://btc-e.com/api/3/';
    
    protected $api_key;
    protected $api_secret;
    protected $noonce;
    protected $RETRY_FLAG = false;
    
    public function __construct($api_key, $api_secret, $base_noonce = false) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        if($base_noonce === false) {
            // Try 1?
            $this->noonce = time();
        } else {
            $this->noonce = $base_noonce;
        }
    }
    
    /**
     * Get the noonce
     * @global type $sql_conx
     * @return type 
     */
    protected function getnoonce() {
        $this->noonce++;
        return array(0.05, $this->noonce);
    }
    
    /**
     * Call the API
     * @staticvar null $ch
     * @param type $method
     * @param type $req
     * @return type
     * @throws Exception 
     */
    public function apiQuery($method, $req = array()) {
        $req['method'] = $method;
        $mt = $this->getnoonce();
        $req['nonce'] = $mt[1];
       
        // generate the POST data string
        $post_data = http_build_query($req, '', '&');
 
        // Generate the keyed hash value to post
        $sign = hash_hmac("sha512", $post_data, $this->api_secret);
 
        // Add to the headers
        $headers = array(
                'Sign: '.$sign,
                'Key: '.$this->api_key,
        );
 
        // Create a CURL Handler for use
        $ch = null;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Marinu666 BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
        curl_setopt($ch, CURLOPT_URL, 'https://btc-e.com/tapi/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 
        // Send API Request
        $res = curl_exec($ch);        
        
        // Check for failure & Clean-up curl handler
        if($res === false) {
            $e = curl_error($ch);
            curl_close($ch);
            throw new BTCeAPIFailureException('Could not get reply: '.$e);
        } else {
            curl_close($ch);
        }
        
        // Decode the JSON
        $result = json_decode($res, true);
        // is it valid JSON?
        if(!$result) {
            throw new BTCeAPIInvalidJSONException('Invalid data received, please make sure connection is working and requested API exists');
        }
        
        // Recover from an incorrect noonce
        if(isset($result['error']) === true) {
            if(strpos($result['error'], 'nonce') > -1 && $this->RETRY_FLAG === false) {
                $matches = array();
                $k = preg_match('/:([0-9]+),/', $result['error'], $matches);
                $this->RETRY_FLAG = true;
                trigger_error("Nonce we sent ({$this->noonce}) is invalid, retrying request with server returned nonce: ({$matches[1]})!");
                $this->noonce = $matches[1];
                return $this->apiQuery($method, $req);
            } else {
                throw new BTCeAPIErrorException('API Error Message: '.$result['error'].". Response: ".print_r($result, true));
            }
        }
        // Cool -> Return
        $this->RETRY_FLAG = false;
        return $result;
    }
    
    /**
     * Retrieve some JSON
     * @param type $URL
     * @return type 
     */
    protected function retrieveJSON($URL) {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'timeout' => 10 
            )
        );
        $context  = stream_context_create($opts);
        $feed = file_get_contents($URL, false, $context);
        $json = json_decode($feed, true);
        return $json;
    }
    
    /**
     * Place an order
     * @param type $amount
     * @param type $pair
     * @param type $direction
     * @param type $price
     * @return type 
     */
    public function makeOrder($amount, $pair, $direction, $price) {
        if($direction == self::DIRECTION_BUY || $direction == self::DIRECTION_SELL) {
            $data = $this->apiQuery("Trade"
                    ,array(
                        'pair' => $pair,
                        'type' => $direction,
                        'rate' => $price,
                        'amount' => $amount
                    )
            );
            return $data; 
        } else {
            throw new BTCeAPIInvalidParameterException('Expected constant from '.__CLASS__.'::DIRECTION_BUY or '.__CLASS__.'::DIRECTION_SELL. Found: '.$direction);
        }
    }
    
    /**
     * Cancel an order
     * @param type $order_id
     * @return type 
     */
    public function cancelOrder($order_id) {
        return $this->apiQuery("CancelOrder"
                    ,array(
                        'order_id' => $order_id
                    )
               );
    }
    
    /**
     * Check an order that is complete (non-active)
     * @param type $orderID
     * @return type
     * @throws Exception 
     */
    public function checkPastOrder($orderID) {
        $data = $this->apiQuery("OrderList"
                ,array(
                    'from_id' => $orderID,
                    'to_id' => $orderID,
                    /*'count' => 15,*/
                    'active' => 0
                ));
        if($data['success'] == "0") {
            throw new BTCeAPIErrorException("Error: ".$data['error']);
        } else {
            return($data);
        }
    }

    /**
     * Public API: Retrieve the Info about active pairs
     * @return array 
     */
    public function getPairsInfo() {
        return $this->retrieveJSON($this->public_api."info");
    }
    
    /**
     * Public API: Retrieve the Fee for a currency pair
     * @param string $pair
     * @return array 
     */
    public function getPairFee($pair) {
        return $this->retrieveJSON($this->public_api."fee/".$pair);
    }
    
    /**
     * Public API: Retrieve the Ticker for a currency pair
     * @param string $pair
     * @return array 
     */
    public function getPairTicker($pair) {
        return $this->retrieveJSON($this->public_api."ticker/".$pair);
    }
    
    /**
     * Public API: Retrieve the Trades for a currency pair
     * @param string $pair
     * @return array 
     */
    public function getPairTrades($pair) {
        return $this->retrieveJSON($this->public_api."trades/".$pair);
    }
    
    /**
     * Public API: Retrieve the Depth for a currency pair
     * @param string $pair
     * @return array 
     */
    public function getPairDepth($pair) {
        return $this->retrieveJSON($this->public_api."depth/".$pair);
    }
}

/**
 * Exceptions
 */
class BTCeAPIException extends Exception {}
class BTCeAPIFailureException extends BTCeAPIException {}
class BTCeAPIInvalidJSONException extends BTCeAPIException {}
class BTCeAPIErrorException extends BTCeAPIException {}
class BTCeAPIInvalidParameterException extends BTCeAPIException {}
?>
