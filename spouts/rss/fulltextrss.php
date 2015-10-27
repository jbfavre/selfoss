<?PHP 

namespace spouts\rss;

if(!function_exists('htmLawed'))
    require('libs/htmLawed.php');

/**
 * Plugin for fetching the news with fivefilters Full-Text RSS
 *
 * @package    plugins
 * @subpackage rss
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class fulltextrss extends feed {

    /**
     * name of spout
     *
     * @var string
     */
    public $name = 'RSS Feed (with FullTextRss)';

    /**
     * description of this source type
     *
     * @var string
     */
    public $description = 'This feed extracts full text article from webpages with an embedded version of Full-Text RSS';

    /**
     * config params
     * array of arrays with name, type, default value, required, validation type
     *
     * - Values for type: text, password, checkbox, select
     * - Values for validation: alpha, email, numeric, int, alnum, notempty
     *
     * When type is "select", a new entry "values" must be supplied, holding
     * key/value pairs of internal names (key) and displayed labels (value).
     * See /spouts/rss/heise for an example.
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
        "url" => array(
            "title"      => "URL",
            "type"       => "text",
            "default"    => "",
            "required"   => true,
            "validation" => array("notempty")
        ),
    );

    /**
     * tag for logger
     *
     * @var string
     */
    public $tag = 'ftrss';

    /**
     *
     */
    private $json_output = null;

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

}
