<?php
/**
* @package Springest for Wordpress
*/

class SpringestApi {
    
    # The API url set through WordPress settings
    protected $apiURL;
    
    # The API key set through WordPress settings
    protected $apiKey;
    
    # An array in which all API calls are stored, used by the debug() function
    var $calls = array();
    
    /**
     * Get the Springest API in an object
     *
     * @return object $SpringestApi
     */
    public function __construct() {
    
        global $SpringestDomains;
    
        # Set API key
        $this->apiKey = get_option('springest_api_key');
        
        $extension = get_option('springest_domain');
        $domain = $SpringestDomains[$extension];
    
        # Set API URL
        $this->apiURL = esc_url(trailingslashit($domain['api_url']));
   
        # If none provided, get the default
        if($this->apiURL == "") {
            $this->apiURL = "http://data.eduhub.nl/";
        }
        
        # If WP is in debug mode, display all API calls in the footer
        if(WP_DEBUG) {
            add_filter('wp_footer',  array(&$this, 'debug'));
            add_filter('admin_footer',  array(&$this, 'debug'));
        }
    }

    /**
     * Call a provided API method with the provided parameters
     *
     * @param string $methodName The API method to call
     * @param array $parameters The parameters to send with the API call
     * @return bool|object False on failure, otherwise the result of the call as an object
     */
    private function call($methodName, $parameters = array()) {
        
        $parameterStr = $this->buildParameterString($parameters);
        # If possible, use file_get_contents, since cURL is not installed on all servers
        if(ini_get('allow_url_fopen') == "On") {
            $methodURI = $this->apiURL . $methodName . ".json?" . $parameterStr;
            $this->calls[] = $methodURI;
            if($result = @file_get_contents($methodURI)) {
              return json_decode($result);
            }
        }
        # If allow_url_fopen is set to Off, use cURL
        elseif(in_array('curl', get_loaded_extensions())) {
            $ch = curl_init();
            $requestUrl = $this->apiURL . $methodName . ".json?".$parameterStr;            
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if($result = curl_exec($ch)) {
                return json_decode($result);
            }
        }
		return false;
    }
    
    /**
    * Get a list of trainings based on a search string
    *
    * @param string $searchTerm The search term(s) to match trainings on
    * @return 
    */
    public function search($searchTerm, $offset) {
        $results = $this->call('trainings/search',array('query' => urlencode($searchTerm), 'offset' => $offset));
        if(isset($results->meta->results) && $results->meta->results > 0) {
            return $results;
        }
        return false;
    }

	/**
	 * Display an error message
	 *
	 * @param string $errorStr The text of the error message to display
	 * @return none
	 */
	public function error($errorStr = "") {
	    if(empty($errorStr)) {
	        $errorStr = __('Unknown error', 'springest');
	    }
		echo $errorStr;
	}
    
    /**
     * Build a query string of parameters to be send with the API call
     *
     * @param array $parameters A set of key/value pairs that to be converted into a query string
     * @return string A URL query string
     */
    public function buildParameterString($parameters) {
        # Add API key as parameter
        $parametersPairs = array('api_key='.$this->apiKey);
        if(count($parameters)) {
            foreach($parameters as $key => $value) {
                $parametersPairs[] = "$key=$value";
            }
        }
        return implode("&", $parametersPairs);
    }
    
    /**
    * Test for valid API key
    * 
    * @param string $apiKey The API key to be tested
    * @return bool True if API key is valid, otherwise false
    */
    function testKey($apiKey) {
        $this->apiKey = $apiKey;
        # Perhaps the API has a more lean method for this?
        if($this->call('categories/list')) {
            return true;
        }
        return false;
    }

	/**
	 * Get a list of all categories available on Springest
	 *
	 * @return object The categories in an object
	 */
	public function getCategories() {
		$results = $this->call("categories/list");
		if(!$results) {
		    $this->error(__("No categories found"));
		}
		else {
		    return $results;
		}
	}
	
    /**
    * Get the Springest subjects in a specified category
    * 
    * @param int $categoryId The ID of the category of which to return the Springest subjects
    * @param array $exclude An optional list of ID's of subjects to exclude
    * @return bool|object False if no subjects were returned, otherwise the returned subjects as an object
    * @todo Filter excluded subjects
    */
    public function getSubjects($categoryId, $exclude = array()) {
        // @todo: Filter excluded subjects
        $totalResults = array();
        $results = $this->call("subjects/list", array("size" => 30, "category_id" => $categoryId));
        if(isset($results->meta->results) && $results->meta->results > 0) {
            $resultsNumber = $results->meta->results;
            foreach($results->subjects as $result) {
                $totalResults[] = $result;
            }
            $offset = 0;
            while(count($totalResults) < $resultsNumber) {
                $offset+=30;
                $results = $this->call("subjects/list", array("size" => 30, "offset" => $offset, "category_id" => $categoryId));
                foreach($results->subjects as $subject) {
                    $totalResults[] = $subject;
                }
            }
            return $totalResults;
        }
        return false;
    }
    
    /**
    * Get the data for a specified list of Springest subjects
    * 
    * @param array $subjectIds A mandatory list of ID's of subjects to include
    * @return bool|object False if no subjects were returned, otherwise the returned subjects as an object
    * @todo Filter excluded subjects
    */
    public function getSpecifiedSubjects($subjectIds) {
        $results = $this->call("subjects/show", array("size" => 1000000000, "ids" => implode(",", $subjectIds)));
        if(isset($results->meta->results) && $results->meta->results > 0) {
            return $results;
        }
        return false;
    }
	
	/**
	* Get the trainings in a specified Springest subject
	* 
	* @param int $subjectId The ID of the Springest subject of which to return the trainings
	* @param array $offset The offset for the returned results, defaults to 0 for first page
	* @param array $exclude An optional list of ID's of trainings to exclude
	* @return bool|object False if no trainings were returned, otherwise the returned trainings as an object
	* @todo Filter excluded trainings
	*/
	public function getTrainings($subjectId, $offset = 0, $size = TRAININGS_PER_PAGE) {
	    // @todo: Filter excluded trainings
	    $results = $this->call("trainings/list", array("subject_id" => $subjectId, "offset" => 0, "size" => $size));
	    if(isset($results->meta->results) && $results->meta->results > 0) {
    	    return $results;
    	}
        return false;
	}
	
	/**
	* Get the data for a list of specified trainings
	* 
	* @param array $trainingsIds The ID's of the Springest trainings to retrieve
	* @param array $offset The offset for the returned results, defaults to 0 for first page
	* @return bool|object False if no trainings were returned, otherwise the returned trainings as an object
	* @todo Filter excluded trainings
	*/
	public function getSpecifiedTrainings($trainingIds, $offset = 0) {
	    $results = $this->call("trainings/show", array("ids" => implode(",", $trainingIds), "offset" => $offset, "size" => TRAININGS_PER_PAGE));
	    if(isset($results->meta->results) && $results->meta->results > 0) {
		    return $results;
		}
	    return false;
	}
	
    /**
    * Get a specified Springest subject as an object
    *
    * @param int $subjectId The ID of the subject to retrieve
    * @return bool|object False if the subject was not found, otherwise the required subject as an object
    */
    public function getSubject($subjectId) {
    /*$results = $this->call("subjects/list", array('subject_id' => $subjectId));
    if(!$results) {
        $this->error(__("No subject found"));
    }
    else {
        print_r($results);
    }*/
    }
	
	/**
	* Get a specified training as an object
	* 
	* @param int $trainingId The ID of the required Springest training
	* @return bool|object False if no training was returned, otherwise the returned training as an object
	*/
	public function getTraining($trainingId) { 
	    $results = $this->call("trainings/".$trainingId);
	    if(isset($results->meta->results) && $results->meta->results > 0) {
	        return array_shift($results->trainings);
	    }
	    return false;
	}
	
    /**
    * Get a specified institute as an object
    * 
    * @param int $insituteId The ID of the required institute
    * @return bool|object False if no institute was returned, otherwise the returned institute as an object
    */
	public function getInstitute($insituteId) {
	    $results = $this->call("institutes/".$insituteId);
	    if(isset($results->meta->results) && $results->meta->results > 0) {
    	    return array_shift($results->institutes);
    	}
    	return false;
	}

    
	/**
	* Helper function to display all calls made to the API
	*
	*/
	public function debug() {
	    foreach($this->calls as $call) {
	        echo $call.'<br/>';
	    }
	}
	
}

?>