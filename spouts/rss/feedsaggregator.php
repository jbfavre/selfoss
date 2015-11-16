<?PHP 

namespace spouts\rss;

/**
 * Spout for fetching an rss feed
 *
 * @package    spouts
 * @subpackage rss
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class feedsaggregator extends feed {

    /**
     * name of source
     *
     * @var string
     */
    public $name = 'RSS Feeds Aggregator';
    
    
    /**
     * description of this source type
     *
     * @var string
     */
    public $description = 'An default RSS Feeds list as source';
    
    
    /**
     * config params
     * array of arrays with name, type, default value, required, validation type
     *
     * - Values for type: text, password, checkbox
     * - Values for validation: alpha, email, numeric, int, alnum, notempty
     * 
     * e.g.
     * array(
     *   "id" => array(
     *     "title"      => "URL",
     *     "type"       => "text",
     *     "default"    => "",
     *     "required"   => true,
     *     "validation" => array("alnum")
     *   ),
     *   ....
     * )
     *
     * @var bool|mixed
     */
    public $params = array(
        "urls" => array(
            "title"      => "URLs",
            "type"       => "multiline",
            "default"    => "",
            "required"   => true,
            "validation" => array("notempty")
        ),
	"dedup" => array(
	    "title"      => "Deduplication policy",
	    "type"       => "select",
	    "values"     => array(
		0        => "None",
		1        => "Low",
		2        => "Medium",
		3        => "High"
	    ),
	    "default"    => 2,
	    "required"   => true,
	    "validation" => array()
	)
    );

    /**
     * tag for logger
     *
     * @var string
     */
    public $tag = 'aggregator';
    
    
    /**
     * current fetched items
     *
     * @var array|bool
     */
    protected $items = false;

    //
    // Source Methods
    //

    /**
     * loads content for given source
     * I supress all Warnings of SimplePie for ensuring
     * working plugin in PHP Strict mode
     *
     * @return void
     * @param mixed $params the params of this source
     */
    public function load($params) {
	$url_list = preg_split("/\\r\\n|\\r|\\n/", $params['urls']);
	\F3::get('logger')->log($this->tag . ' - Got ' . sizeof($url_list) . ' URLs', \INFO);

        // initialize simplepie feed loader
        $this->feed = @new \SimplePie();
        @$this->feed->set_cache_location(\F3::get('cache'));
        @$this->feed->set_cache_duration(1800);
        @$this->feed->set_feed_url($url_list);
        @$this->feed->set_autodiscovery_level( SIMPLEPIE_LOCATOR_AUTODISCOVERY | SIMPLEPIE_LOCATOR_LOCAL_EXTENSION | SIMPLEPIE_LOCATOR_LOCAL_BODY);
        $this->feed->set_useragent(\helpers\WebClient::getUserAgent(array('SimplePie/'.SIMPLEPIE_VERSION)));

        // fetch items
        @$this->feed->init();

        // on error retry with force_feed
        if(@$this->feed->error()) {
            @$this->feed->set_autodiscovery_level(SIMPLEPIE_LOCATOR_NONE);
            @$this->feed->force_feed(true);
            @$this->feed->init();
        }

        // check for error
        if(@$this->feed->error()) {
            throw new \exception($this->feed->error());
        } else {
            // save fetched items
            $tmp_items = $this->feed->get_items();

            // items deduplication
            $kept_ids = array();
            $kept_titles = array();
            $kept_uris = array();
            foreach($tmp_items as $item) {
                $tmp_score = 3;
                $tmp_link = basename(parse_url($item->get_permalink(), PHP_URL_PATH));
                $tmp_id = $item->get_id();
                $tmp_title = $item->get_title();
                \F3::get('logger')->log($this->tag . ' - deduplicating item: ' . $tmp_title, \INFO);
                \F3::get('logger')->log($this->tag . ' -            item id: ' . $tmp_id, \DEBUG);
                \F3::get('logger')->log($this->tag . ' -          permalink: ' . $tmp_link, \DEBUG);
                \F3::get('logger')->log($this->tag . ' -             policy: ' . $params['dedup'], \DEBUG);
                if (in_array($tmp_id, $kept_ids)) {
                    $tmp_score--;
                }
                if (in_array($tmp_title, $kept_titles)) {
                    $tmp_score--;
                }
                if (in_array($tmp_link, $kept_uris)) {
                    $tmp_score--;
                }
                if ( $tmp_score > $params['dedup'] ) {
                    \F3::get('logger')->log($this->tag . ' -   keeping item: ' . $tmp_score, \DEBUG);
                    // update already seen items lists
                    $kept_ids[] = $tmp_id;
                    $kept_titles[] = $tmp_title;
                    $kept_uris[] = $tmp_link;
                    // add kept item to final list
                    $this->items[] = $item;
                }else{
                    \F3::get('logger')->log($this->tag . ' -  skipping item: ' . $tmp_score, \DEBUG);
                }
            }
        }
        // return html url
        $this->htmlUrl = $this->feed->get_link();
    }


    /**
     * returns the xml feed url for the source
     *
     * @return string url as xml
     * @param mixed $params params for the source
     */
    public function getXmlUrl($params) {
        return isset($params['urls']) ? $params['urls'] : false;
    }


    /**
     * returns the global html url for the source
     *
     * @return string url as html
     */
    public function getHtmlUrl() {
        if(isset($this->htmlUrl))
            return $this->htmlUrl;
        return false;
    }
    
    
    /**
     * returns an unique id for this item
     *
     * @return string id as hash
     */
    public function getId() {
        if($this->items!==false && $this->valid()) {
            $id = @current($this->items)->get_id();
            if(strlen($id)>255)
                $id = md5($id);
            return $id;
        }
        return false;
    }
    
    
    /**
     * returns the current title as string
     *
     * @return string title
     */
    public function getTitle() {
        if($this->items!==false && $this->valid())
            return @current($this->items)->get_title();
        return false;
    }
    
    
    /**
     * returns the content of this item
     *
     * @return string content
     */
    public function getContent() {

        $url = parent::getLink();
	$url = $this->removeTrackersFromUrl($url);

        \F3::get('logger')->log($this->tag . ' - Processing URL ' . $url, \DEBUG);

	$real_autoload = \F3::get('AUTOLOAD');
	\F3::set('AUTOLOAD', '');

	// Saving and clearing context
	$REAL = array();
	foreach( $GLOBALS as $key => $value ) {
	    if( $key != 'GLOBALS' && $key != '_SESSION' && $key != 'HTTP_SESSION_VARS' ) {
	        $GLOBALS[$key] = array();
	        $REAL[$key] = $value;
	    }
	}
	// Saving and clearing session
	if (isset($_SESSION)) {
	    $REAL_SESSION = array();
	    foreach( $_SESSION as $key => $value ) {
	        $REAL_SESSION[$key] = $value;
	        unset($_SESSION[$key]);
	    }
	}

	// Running code in different context
	$scope = function() {
	    extract( func_get_arg(1) );
	    $_GET = $_REQUEST = array(
		"url" => $url,
		"max" => 5,
		"links" => "preserve",
		"exc" => "",
		"format" => "json",
		"submit" => "Create Feed"
	    );
	    ob_start();
	    require func_get_arg(0);
	    $json = ob_get_contents();
	    ob_end_clean();
	    return $json;
        };

	// Silence $scope function to avoid
	// issues with FTRSS when error_reporting is to high
	// FTRSS generates PHP warnings which break output
	$json = @$scope("libs/fulltextrss/makefulltextfeed.php", array("url" => $url));

	// Clearing and restoring context
	foreach ($GLOBALS as $key => $value) {
	    if($key != "GLOBALS" && $key != "_SESSION" ) {
	        unset($GLOBALS[$key]);
	    }
	}
	foreach ($REAL as $key => $value) {
	   $GLOBALS[$key] = $value;
	}
	// Clearing and restoring session
	if (isset($REAL_SESSION)) {
	    foreach($_SESSION as $key => $value) {
	        unset($_SESSION[$key]);
	    }
	    foreach($REAL_SESSION as $key => $value) {
	        $_SESSION[$key] = $value;
	    }
	}

	\F3::set('AUTOLOAD', $real_autoload);

        \F3::get('logger')->log($this->tag . ' - Got JSON', \DEBUG);
	$this->json_output = json_decode($json, true);
        return htmlspecialchars_decode(
            htmlentities($this->json_output['rss']['channel']['item']['description'])
        );
    }
    
    /**
     * remove tarkers from url
     *
     * @author Jean Baptiste Favre
     * @return string url
     * @param string $url
     */
    private function removeTrackersFromUrl($url) {
        $url = parse_url($url);

        // Next, rebuild URL
        $real_url = $url['scheme'] . '://';
        if (isset($url['user']) && isset($url['password']))
            $real_url .= $url['user'] . ':' . $url['password'] . '@';
        $real_url .= $url['host'] . $url['path'];

        // Query string
        if (isset($url['query'])) {
            parse_str($url['query'], $q_array);
            $real_query = array();
            foreach ($q_array as $key => $value) {
                // Remove utm_* parameters
                if(strpos($key, 'utm_')===false)
                    $real_query[]= $key.'='.$value;
            }
            $real_url .= '?' . implode('&', $real_query);
        }
        // Fragment
        if (isset($url['fragment'])) {
            // Remove xtor=RSS anchor
            if (strpos($url['fragment'], 'xtor=RSS')===false)
                $real_url .= '#' . $url['fragment'];
        }
        return $real_url;
    }

    /**
     * destroy the plugin (prevent memory issues)
     */
    public function destroy() {
        //$this->feed->__destruct();
        unset($this->items);
        $this->items = false;
    }
}
