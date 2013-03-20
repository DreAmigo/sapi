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
    const ENDPOINT_TEST = "http://api.sensis.com.au/ob-20110511/test/";
    
    /*
     * Live endpoint
     */
    const ENDPOINT_LIVE = "http://api.sensis.com.au/ob-20110511/prod/";
    
    
    /**
    * Whether or not SAPI is in test mode.
    *
    * @return type
    */
    public static function isTesting() {
        return Symphony::Configuration()->get('gateway-mode', 'sapi') == 'sandbox';
    }
    
    /*
     * API Key
     * 
     * @see http://developers.sensis.com.au/
     */
    public static function getApiKey() {
        return Symphony::Configuration()->get('api-key', 'sapi');
    }
    
    /**
     * 
     * Function to retrieve a data from SAPI by Listing ID and report a "Reporting Usage Events".
     * Every time that you retrieve a data from SAPI, you must report to them the appearance.
     * 
     * @see http://developers.sensis.com.au/docs/endpoint_reference/Get_by_Listing_ID
     * @see http://developers.sensis.com.au/docs/read/using_endpoints/Reporting_Usage_Events
     */
    public function getByListingId($id, $typeReport = "appearance") {
        
        if(self::isTesting()) {
            $endpoint = self::ENDPOINT_TEST . "getByListingId?key=". $this->getApiKey() ."&query=". $id ."";
        }
        else {
            $endpoint = self::ENDPOINT_LIVE . "getByListingId?key=". $this->getApiKey() ."&query=". $id ."";
        }
        
        # Return the Listing Details.
        $response = file_get_contents($endpoint); 
        $result = json_decode($response, true);
               
        # Setup a Report Event.  
        $reportDetails = $typeReport . "?key=". $this->getApiKey() ."&userIp=". $_SERVER['REMOTE_ADDR'] ."&userAgent=". urlencode($_SERVER['HTTP_USER_AGENT']) ."&id=" . $result['results'][0]['reportingId'];
        
        if(self::isTesting()) {
            $sendReport = self::ENDPOINT_TEST . "report/" . $reportDetails;
        }
        else {
            $sendReport = self::ENDPOINT_LIVE . "report/" . $reportDetails;
        }

        // Send the Report to SAPI.
        $responseReport = file_get_contents($sendReport); 
        
        // Append to data.
        $data = $result['results'][0];
        
        // Look for email and phone number.
        $phone = array();
        $email = array();
        if(isset($data['primaryContacts'])){
            foreach($data['primaryContacts'] as $key => $contacts) {
                switch ($contacts['type']) {
                    case "EMAIL":
                        $email[] = $contacts['value'];
                    break;
                    case "PHONE":
                        $phone[] = $contacts['value'];
                    break;
                }
            }
        }
        
        # Return an array with quick usable data and a raw data as well.
        $sapiData = array(
            'name' => $data['name'],
            'address' => $data['primaryAddress']['addressLine'] . ", " . $data['primaryAddress']['suburb'] . ", " . $data['primaryAddress']['state'] . ", " . $data['primaryAddress']['postcode'],
            'phone' =>  $phone[0],
            'email' =>  $email[0],
            'raw' => $result
        );
        
        return $sapiData;
        
    }
        
    
    /**
     * Do a request to SAPI API.
     * 
     * @param type $currentPage
     * @return string
     * @throws Exception
     */
    public function request($currentPage = 1) {
        
        /*
         * Search for specific Categories.
         * 
         * @see http://developers.sensis.com.au/page/category_explorer
         */
        $searchOnCategories = Symphony::Configuration()->get('api-category', 'sapi');

        /*
         * Query for the API.
         * 
         * @see http://developers.sensis.com.au/docs/read/using_endpoints/Category_Filtering
         */
        $query = Symphony::Configuration()->get('api-keyword', 'sapi');        
        
        if(self::isTesting()) {
            $endpoint = self::ENDPOINT_TEST;
        }
        else {
            $endpoint = self::ENDPOINT_LIVE;
        }
        
        $url = $endpoint . "search?rows=50&page=". $currentPage ."&query=". $query ."&key=" . $this->getApiKey() . "&" . trim($searchOnCategories, "&");
        
        # Get a response from SAPI.
        $response = file_get_contents($url); 
        
        // Check the response.
        if (!$response) {
            throw new Exception("Error calling API ($http_response_header[0])");
        }

        // Convert to json.
        $result = json_decode($response, true);
 
        # Response code
        $code = $result["code"];

        # Test response
        if ($code == 200) { # All good :)
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
                
                // There are a few contacts returning from the API.
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
     * Do a search in the SAPI API.
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
