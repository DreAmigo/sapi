<?php

/**
 * API that will return some data from sensis.com.au
 * 
 * @see http://developers.sensis.com.au/
 * @author Andre Bocati <andre@randb.com.au>
 */

Class SAPI {
    
    /*
     * Sandbox endpoint
     */
    const ENDPOINT_TEST = "http://api.sensis.com.au/ob-20110511/test/search";
    
    /*
     * Live endpoint
     */
    const ENDPOINT_LIVE = "http://api.sensis.com.au/ob-20110511/prod/search";
    

    /**
    * Whether or not SAPI is in test mode.
    *
    * @return type
    */
    public static function isTesting() {
        return Symphony::Configuration()->get('gateway-mode', 'sapi') == 'sandbox';
    }
        
    
    /**
     * Connect to the SAPI API.
     * 
     * @param type $currentPage
     * @return string
     * @throws Exception
     */
    public function request($currentPage = 1) {
        
        /*
         * API KEY
         * @see http://developers.sensis.com.au/
         */
        $apiKey = Symphony::Configuration()->get('api-key', 'sapi');
        
        /*
         * @see http://developers.sensis.com.au/page/category_explorer
         */
        $searchOnCategories = Symphony::Configuration()->get('api-category', 'sapi');
        //

        /*
         * Query for the API.
         */
        $query = Symphony::Configuration()->get('api-keyword', 'sapi');
        
        
        if(self::isTesting()) {
            $endpoint = self::ENDPOINT_TEST;
        }
        else {
            $endpoint = self::ENDPOINT_LIVE;
        }
        
        $url = $endpoint . "?rows=50&page=". $currentPage ."&query=". $query ."&key=" . $apiKey . $searchOnCategories;
        
        # Call the endpoint
        $response = file_get_contents($url); 
        
        // Check the response.
        if (!$response) {
            throw new Exception("Error calling API ($http_response_header[0])");
        }

        // Convert to json.
        $result = json_decode($response, true);
 
        # grab the response code
        $code = $result["code"];

        # ensure successful status code
        if ($code == 200) { # success
            return $result; 
        } else if ($code == 206) { # spell-checker was run
            echo "Note: " . $result["message"] . "\n";
            return result;
        } else {
            throw new Exception("API returned error: " . 
                    $result["message"] . ", code: " . $result["code"]);
        }

    }
    
    /**
     * Build an array of data.
     * 
     * @param type $data A result from the first query that we can receive the total of pages to run in the loop.
     * @return array Return an array of all the data from the search.
     */
    public function buildResult($data) {

        // Get some details from the first request.
        $totalResults = $data["totalResults"];
        $currentPage = $data["currentPage"];
        $totalPages = $data["totalPages"];
        $count = $data["count"];
        
        // Array for all results of all pages.
        $results = array();
        
        // Having the number of pages lets request the results for every single page.
        for ($page = 1; $page <= $totalPages; $page++) {

            // Query results for the $page.
            $query = $this->request($page);
            
            // Go through all results of the $page.
            foreach ($query['results'] as $result) {
               
                $id = $result["id"];
                $name = $result["name"];
                $state = $result['primaryAddress']['state'];
                $type = $result['primaryAddress']['type'];
                $suburb = $result['primaryAddress']['suburb'];
                $postcode = $result['primaryAddress']['postcode'];
                $addressLine = $result['primaryAddress']['addressLine'];                
                $description = $result['mediumDescriptor'];
                
                // There is in the API primary and secondary contacts.
                $email = array();
                $url   = array();
                $phone = array();
                $anotherContact = array();
                
                // Grab the primary contacts.
                if($result['primaryContacts']) {
                    foreach($result['primaryContacts'] as $key => $contacts) {
                        // Look for all types of contacts.
                        switch ($contacts['type']) {
                            case "EMAIL":
                                $email[] = $contacts['value'];
                            break;
                            case "URL":
                                $url[] = $contacts['value'];
                            break;
                            case "PHONE":
                                $phone[] = $contacts['value'];
                            break;
                            default:
                                $anotherContact[] = $contacts['type'] . ": ". $contacts['value'];
                            break;
                        }
                    }
                }
                
                // Grab the secondary contacts.     
                if($result['secondaryContacts']) {
                    foreach($result['secondaryContacts'] as $key => $contacts) {
                        // Look for all types of contacts.
                        switch ($contacts[0]['type']) {
                            case "PHONE":
                                $phone[] = $contacts[0]['value'];
                            break;
                            case "EMAIL":
                                $email[] = $contacts[0]['value'];
                            break;
                            default:
                                $anotherContact[] = $contacts[0]['type'] . ": ". $contacts[0]['value'];
                            break;
                        }
                    }
                }
                
                // Build a result array.
                $results[] = array (
                    'id'    => $id,
                    'name'  => $name,
                    'description' => $description,
                    'address' => $addressLine . ", " . $suburb . ", " . $state . ", " . $postcode,
                    'phone' => $phone,
                    'email' => $email,
                    'anotherContacts' => $anotherContact,
                    'allData' => $result
                );
                
            }
        }
        
        return $results;
        
    }
    
    /**
     * Do a search in teh SAPI API.
     * 
     * @return type
     */
    public function doSearch() {

        // Try to do a first search to find out how many pages and results are in the query.
        $results = $this->request();

        // Build the array of results.
        return $this->buildResult($results);
    }
    
}