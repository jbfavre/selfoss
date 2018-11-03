<?php

namespace spouts\rss;

use Graby\Graby;
use helpers\WebClient;

/**
 * Plugin for fetching the news with fivefilters Full-Text RSS
 *
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (https://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class fulltextrss extends feed {
    /** @var string name of spout */
    public $name = 'RSS Feed (with FullTextRss)';

    /** @var string description of this source type */
    public $description = 'This feed extracts full text article from webpages with an embedded version of Full-Text RSS';

    /** @var array config params */
    public $params = [
        'url' => [
            'title' => 'URL',
            'type' => 'url',
            'default' => '',
            'required' => true,
            'validation' => ['notempty']
        ],
    ];

    /** @var string tag for logger */
    private static $loggerTag = 'selfoss.graby';

    /** @var Graby */
    private $graby;

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
        $url = $this->getLink();

        if (!isset($this->graby)) {
            $this->graby = new Graby([
                'extractor' => [
                    'config_builder' => [
                        'site_config' => [\F3::get('FTRSS_CUSTOM_DATA_DIR')],
                    ],
                ],
            ], WebClient::getHttpClient());
            $logger = \F3::get('logger')->withName(self::$loggerTag);
            $this->graby->setLogger($logger);
        }

        \F3::get('logger')->info('Extracting content for page: ' . $url);

        $response = $this->graby->fetchContent($url);

        if ($response['status'] !== 200) {
            \F3::get('logger')->error('Failed loading page');

            return '<p><strong>Failed to get web page</strong></p>' . parent::getContent();
        }

        $content = $response['html'];

        return $content;
    }

    /**
     * returns the link of this item
     *
     * @return string link
     */
    public function getLink() {
        $url = parent::getLink();

        return self::removeTrackersFromUrl($url);

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
     * remove trackers from url
     *
     * @author Jean Baptiste Favre
     *
     * @param string $url
     *
     * @return string url
     */
    private static function removeTrackersFromUrl($url) {
        $url = parse_url($url);

        // Next, rebuild URL
        $real_url = $url['scheme'] . '://';
        if (isset($url['user']) && isset($url['password'])) {
            $real_url .= $url['user'] . ':' . $url['password'] . '@';
        }
        $real_url .= $url['host'] . $url['path'];

        // Query string
        if (isset($url['query'])) {
            parse_str($url['query'], $q_array);
            $real_query = [];
            foreach ($q_array as $key => $value) {
                // Remove utm_* parameters
                if (strpos($key, 'utm_') === false) {
                    $real_query[] = $key . '=' . $value;
                }
            }
            $real_url .= '?' . implode('&', $real_query);
        }
        // Fragment
        if (isset($url['fragment'])) {
            // Remove xtor=RSS anchor
            if (strpos($url['fragment'], 'xtor=RSS') === false) {
                $real_url .= '#' . $url['fragment'];
            }
        }

        return $real_url;
    }
}
