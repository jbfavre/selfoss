<?PHP 

namespace spouts\rss;

if(!function_exists('htmLawed'))
    require('libs/htmLawed.php');

/**
 * Plugin for building an RSS feed from sitemap with fivefilters Full-Text RSS
 *
 * @package    plugins
 * @subpackage rss
 * @copyright  Copyright (c) Jean Baptiste Favre (http://www.jbafvre.org)
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Jean Baptiste Favre <webmaster@jbfavre.org>
 */
class sitemap2rss extends fulltextrss {

    /**
     * name of spout
     *
     * @var string
     */
    public $name = 'RSS Feed (from SiteMap)';

    /**
     * description of this source type
     *
     * @var string
     */
    public $description = 'This feed builds a feed from sitemap and extracts full text article from each URLs with an embedded version of Full-Text RSS';

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
    public $tag = 'sitemap2rss';

    /**
     * Loads remote sitemap XMl file
     * and build our fake RSS feed
     * so that SelfOSS will be able
     * to analyze it
     * 
     * @return string $rssfeed
     * @param string $url
     */
    private function loadSitemap($url) {

        // Build fake RSS feed
        $feed = new \SimpleXMLElement('<rss version="2.0"></rss>');
        $feed->addChild('channel');
        $feed->channel->addChild('title', 'Sitemap2rss feed');
        $feed->channel->addChild('link', $url);
        $feed->channel->addChild('description', 'This feed is dynamically build from a sitemap URL.');
        $feed->channel->addChild('pubDate', date(DATE_RSS)); 

        // Load sitemap file
        $stream_opts = array(
            'http'=>array(
                'timeout' => 5,
                'method'  => "GET",
                'header'  => "Accept-language: en-us,en-gb;q=0.8,en;q=0.6,fr;q=0.4,fr-fr;q=0.2\r\n" .
                             "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                             "User-Agent: SimplePie/1.3.1 (Feed Parser; http://simplepie.org; Allow like Gecko) Build/20121030175911" .
                             "DNT: 1"
          )
        );
        $context = stream_context_create($stream_opts);
        $sitemap = file_get_contents($url, false, $context);
        if ($sitemap===false)
            throw new \exception('Unable to fetch sitemap' . $url);

        // Analyze sitemap file content
        $xml = new \SimpleXMLElement($sitemap);
        foreach ($xml->children() as $key => $article) {
            \F3::get('logger')->log($this->tag . ' - Extracting URL : ' . $article->loc, \INFO);

            $link = $article->loc;
            // xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
            // xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
            // xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
            $news = $article->children('http://www.google.com/schemas/sitemap-news/0.9');
            $meta = $news->children('http://www.google.com/schemas/sitemap-news/0.9');
            if ( $news && $meta ) {
                $title = $meta->title;
                $date  = $meta->publication_date;

                // Insert link as into fake feed
                $item = $feed->channel->addChild('item');
                $item->addChild('title', $title);
                $item->addChild('link', $link);
                $item->addChild('guid', $link);
                $item->addChild('description', 'Sample item form sitemap2rss');
                $item->addChild('pubDate', date(DATE_RSS, strtotime($date))); 
            }else{
                \F3::get('logger')->log($this->tag . ' - No valid meta. Skipping', \DEBUG);
                continue;
            }
        }
        return $feed->asXML();
    }

    /**
     * Loads content for given source
     * I supress all SimplePie Warning to ensure
     * plugin will work in PHP Strict mode
     *
     * @return void
     * @param mixed $params the params of this source
     */
    public function load($params) {

        \F3::get('logger')->log($this->tag . ' - Loading sitemap: ' . $params['url'], \INFO);

        // Load sitemap and build fake RSS feed
        $rawdata = $this->loadSitemap($params['url']);

        // Initialize simplepie feed loader
        $this->feed = @new \SimplePie();
        @$this->feed->set_cache_location(\F3::get('cache'));
        @$this->feed->set_cache_duration(1800);

        // Build RSS feed from rawdata
        @$this->feed->set_raw_data($rawdata);
        @$this->feed->set_autodiscovery_level( SIMPLEPIE_LOCATOR_AUTODISCOVERY | SIMPLEPIE_LOCATOR_LOCAL_EXTENSION | SIMPLEPIE_LOCATOR_LOCAL_BODY);

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
            $this->items = @$this->feed->get_items();
        }

        // return html url
        $this->htmlUrl = @$this->feed->get_link();
    }
}
