<?php
/***************************************************************************

FeedCreator class v1.6
originally (c) Kai Blankenhorn
www.bitfolge.de
kaib@bitfolge.de
v1.3 work by Scott Reynen (scott@randomchaos.com) and Kai Blankenhorn
v1.5 OPML support by Dirk Clemens

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details: <http://www.gnu.org/licenses/gpl.txt>

****************************************************************************


Changelog:

Modifications for WebSVN:
   The main description link wasn't put through htmlspecialcharacters
   Output encoding now defined by $config
   Remove hardcoded time zone

v1.6    05-10-04
    added stylesheet to RSS 1.0 feeds
    fixed generator comment (thanks Kevin L. Papendick and Tanguy Pruvot)
    fixed RFC822 date bug (thanks Tanguy Pruvot)
    added TimeZone customization for RFC8601 (thanks Tanguy Pruvot)
    fixed Content-type could be empty (thanks Tanguy Pruvot)
    fixed author/creator in RSS1.0 (thanks Tanguy Pruvot)


v1.6 beta    02-28-04
    added Atom 0.3 support (not all features, though)
    improved OPML 1.0 support (hopefully - added more elements)
    added support for arbitrary additional elements (use with caution)
    code beautification :-)
    considered beta due to some internal changes

v1.5.1    01-27-04
    fixed some RSS 1.0 glitches (thanks to St�phane Vanpoperynghe)
    fixed some inconsistencies between documentation and code (thanks to Timothy Martin)

v1.5    01-06-04
    added support for OPML 1.0
    added more documentation

v1.4    11-11-03
    optional feed saving and caching
    improved documentation
    minor improvements

v1.3    10-02-03
    renamed to FeedCreator, as it not only creates RSS anymore
    added support for mbox
    tentative support for echo/necho/atom/pie/???
        
v1.2    07-20-03
    intelligent auto-truncating of RSS 0.91 attributes
    don't create some attributes when they're not set
    documentation improved
    fixed a real and a possible bug with date conversions
    code cleanup

v1.1    06-29-03
    added images to feeds
    now includes most RSS 0.91 attributes
    added RSS 2.0 feeds

v1.0    06-24-03
    initial release



***************************************************************************/

/*** GENERAL USAGE *********************************************************

include("feedcreator.class.php");

$rss = new UniversalFeedCreator();
$rss->useCached(); // use cached version if age<1 hour
$rss->title = "PHP news";
$rss->description = "daily news from the PHP scripting world";
$rss->link = "http://www.dailyphp.net/news";
$rss->syndicationURL = "http://www.dailyphp.net/".$_SERVER["PHP_SELF"];

$image = new FeedImage();
$image->title = "dailyphp.net logo";
$image->url = "http://www.dailyphp.net/images/logo.gif";
$image->link = "http://www.dailyphp.net";
$image->description = "Feed provided by dailyphp.net. Click to visit.";
$rss->image = $image;

// get your news items from somewhere, e.g. your database:
mysql_select_db($dbHost, $dbUser, $dbPass);
$res = mysql_query("SELECT * FROM news ORDER BY newsdate DESC");
while ($data = mysql_fetch_object($res)) {
    $item = new FeedItem();
    $item->title = $data->title;
    $item->link = $data->url;
    $item->description = $data->short;
    $item->date = $data->newsdate;
    $item->source = "http://www.dailyphp.net";
    $item->author = "John Doe";
     
    $rss->addItem($item);
}

// valid format strings are: RSS0.91, RSS1.0, RSS2.0, PIE0.1 (deprecated),
// MBOX, OPML, ATOM0.3
echo $rss->saveFeed("RSS1.0", "news/feed.xml");

# }}}

***************************************************************************
*          A little setup                                                 *
**************************************************************************/

/**
* Version string.
**/
define("FEEDCREATOR_VERSION", "FeedCreator 1.6");



/**
* A FeedItem is a part of a FeedCreator feed.
*
* @author Kai Blankenhorn <kaib@bitfolge.de>
* @since 1.3
*/
class FeedItem {
    # {{{ Properties

    /**
     * Mandatory attributes of an item.
     */
    var $title, $description, $link;
    
    /**
     * Optional attributes of an item.
     */
    var $author, $authorEmail, $image, $category, $comments, $guid, $source, $creator;
    
    /**
     * Publishing date of an item. May be in one of the following formats:
     *
     *    RFC 822:
     *    "Mon, 20 Jan 03 18:05:41 +0400"
     *    "20 Jan 03 18:05:41 +0000"
     *
     *    ISO 8601:
     *    "2003-01-20T18:05:41+04:00"
     *
     *    Unix:
     *    1043082341
     */
    var $date;
    
    /**
     * Any additional elements to include as an assiciated array. All $key => $value pairs
     * will be included unencoded in the feed item in the form
     *     <$key>$value</$key>
     * Again: No encoding will be used! This means you can invalidate or enhance the feed
     * if $value contains markup. This may be abused to embed tags not implemented by
     * the FeedCreator class used.
     */
    var $additionalElements = Array();

    // on hold
    // var $source;

    # }}}
}



/**
* An FeedImage may be added to a FeedCreator feed.
* @author Kai Blankenhorn <kaib@bitfolge.de>
* @since 1.3
*/
class FeedImage {
    # {{{ Properties

    /**
     * Mandatory attributes of an image.
     */
    var $title, $url, $link;
    
    /**
     * Optional attributes of an image.
     */
    var $width, $height, $description;

    # }}}
}


/**
* UniversalFeedCreator lets you choose during runtime which
* format to build.
* For general usage of a feed class, see the FeedCreator class
* below or the example above.
*
* @since 1.3
* @author Kai Blankenhorn <kaib@bitfolge.de>
*/
class UniversalFeedCreator extends FeedCreator {
    var $_feed;
    
    # {{{ _setFormat
    function _setFormat($format) {
        switch (strtoupper($format)) {
            
            case "2.0":
                // fall through
            case "RSS2.0":
                $this->_feed = new RSSCreator20();
                break;
            
            case "1.0":
                // fall through
            case "RSS1.0":
                $this->_feed = new RSSCreator10();
                break;
            
            case "0.91":
                // fall through
            case "RSS0.91":
                $this->_feed = new RSSCreator091();
                break;
            
            case "PIE0.1":
                $this->_feed = new PIECreator01();
                break;
            
            case "MBOX":
                $this->_feed = new MBOXCreator();
                break;
            
            case "OPML":
                $this->_feed = new OPMLCreator();
                break;
                
            case "ATOM0.3":
                $this->_feed = new AtomCreator03();
                break;
            
            default:
                $this->_feed = new RSSCreator091();
                break;
        }
        
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if ($key!="feed") {
                $this->_feed->{$key} = $this->{$key};
            }
        }
    }
    # }}}
    
    # {{{ createFeed
    /**
     * Creates a syndication feed based on the items previously added.
     *
     * @see        FeedCreator::addItem()
     * @param    string    format    format the feed should comply to. Valid values are:
     *            "PIE0.1", "mbox", "RSS0.91", "RSS1.0", "RSS2.0", "OPML".
     * @return    string    the contents of the feed.
     */
    function createFeed($format = "RSS0.91") {
        $this->_setFormat($format);
        return $this->_feed->createFeed();
    }
    # }}}

    # {{{ saveFeed
    /**
     * Saves this feed as a file on the local disk. After the file is saved, an HTTP redirect
     * header may be sent to redirect the use to the newly created file.
     * @since 1.4
     *
     * @param    string    format    format the feed should comply to. Valid values are:
     *            "PIE0.1" (deprecated), "mbox", "RSS0.91", "RSS1.0", "RSS2.0", "OPML", "ATOM0.3".
     * @param    string    filename    optional    the filename where a recent version of the feed is saved. If not specified, the filename is $_SERVER["PHP_SELF"] with the extension changed to .xml (see _generateFilename()).
     * @param    boolean    displayContents    optional    send the content of the file or not. If true, the file will be sent in the body of the response.
     */
    function saveFeed($format="RSS0.91", $filename="", $displayContents=true) {
        $this->_setFormat($format);
        $this->_feed->saveFeed($filename, $displayContents);
    }
    # }}}

}


/**
* FeedCreator is the abstract base implementation for concrete
* implementations that implement a specific format of syndication.
*
* @abstract
* @author Kai Blankenhorn <kaib@bitfolge.de>
* @since 1.4
*/
class FeedCreator {
    # {{{ Properties

    /**
     * Mandatory attributes of a feed.
     */
    var $title, $description, $link;


    /**
     * Optional attributes of a feed.
     */
    var $syndicationURL, $image, $language, $copyright, $pubDate, $lastBuildDate, $editor, $editorEmail, $webmaster, $category, $docs, $ttl, $rating, $skipHours, $skipDays;


    /**
     * @access private
     */
    var $items = Array();


    /**
     * This feed's MIME content type.
     * @since 1.4
     * @access private
     */
    var $contentType = "text/xml";


    /**
     * Any additional elements to include as an assiciated array. All $key => $value pairs
     * will be included unencoded in the feed in the form
     *     <$key>$value</$key>
     * Again: No encoding will be used! This means you can invalidate or enhance the feed
     * if $value contains markup. This may be abused to embed tags not implemented by
     * the FeedCreator class used.
     */
    var $additionalElements = Array();

    # }}}
    
    # {{{ addItem
    /**
     * Adds an FeedItem to the feed.
     *
     * @param object FeedItem $item The FeedItem to add to the feed.
     * @access public
     */
    function addItem($item) {
        $this->items[] = $item;
    }
    # }}}

    # {{{ iTrunc
    /**
     * Truncates a string to a certain length at the most sensible point.
     * First, if there's a '.' character near the end of the string, the string is truncated after this character.
     * If there is no '.', the string is truncated after the last ' ' character.
     * If the string is truncated, " ..." is appended.
     * If the string is already shorter than $length, it is returned unchanged.
     *
     * @static
     * @param string    string A string to be truncated.
     * @param int        length the maximum length the string should be truncated to
     * @return string    the truncated string
     */
    function iTrunc($string, $length) {
        if (strlen($string)<=$length) {
            return $string;
        }
        
        $pos = strrpos($string,".");
        if ($pos>=$length-4) {
            $string = substr($string,0,$length-4);
            $pos = strrpos($string,".");
        }
        if ($pos>=$length*0.4) {
            return substr($string,0,$pos+1)." ...";
        }
        
        $pos = strrpos($string," ");
        if ($pos>=$length-4) {
            $string = substr($string,0,$length-4);
            $pos = strrpos($string," ");
        }
        if ($pos>=$length*0.4) {
            return substr($string,0,$pos)." ...";
        }
        
        return substr($string,0,$length-4)." ...";
            
    }
    # }}}

    # {{{ _createGeneratorComment
    /**
     * Creates a comment indicating the generator of this feed.
     * The format of this comment seems to be recognized by
     * Syndic8.com.
     */
    function _createGeneratorComment() {
        return "<!-- generator=\"".FEEDCREATOR_VERSION."\" -->\n";
    }
    # }}}

    # {{{ _createAdditionalElements
    /**
     * Creates a string containing all additional elements specified in
     * $additionalElements.
     * @param    elements    array    an associative array containing key => value pairs
     * @param indentString    string    a string that will be inserted before every generated line
     * @return    string    the XML tags corresponding to $additionalElements
     */
    function _createAdditionalElements($elements, $indentString="") {
        $ae = "";
        if (is_array($elements)) {
            foreach($elements AS $key => $value) {
                $ae.= $indentString."<$key>$value</$key>\n";
            }
        }
        return $ae;
    }
    # }}}

    # {{{ createFeed
    /**
     * Builds the feed's text.
     * @abstract
     * @return    string    the feed's complete text
     */
    function createFeed() {
    }
    # }}}

    # {{{ _generateFilename
    /**
     * Generate a filename for the feed cache file. The result will be $_SERVER["PHP_SELF"] with the extension changed to .xml.
     * For example:
     *
     * echo $_SERVER["PHP_SELF"]."\n";
     * echo FeedCreator::_generateFilename();
     *
     * would produce:
     *
     * /rss/latestnews.php
     * latestnews.xml
     *
     * @return string the feed cache filename
     * @since 1.4
     * @access private
     */
    function _generateFilename() {
        $fileInfo = pathinfo($_SERVER["PHP_SELF"]);
        return substr($fileInfo["basename"],0,-(strlen($fileInfo["extension"])+1)).".xml";
    }
    # }}}

    # {{{ _redirect
    /**
     * @since 1.4
     * @access private
     */
    function _redirect($filename) {
        // attention, heavily-commented-out-area
        
        // maybe use this in addition to file time checking
        //Header("Expires: ".date("r",time()+$this->_timeout));
        
        /* no caching at all, doesn't seem to work as good:
        Header("Cache-Control: no-cache");
        Header("Pragma: no-cache");
        */
        
        // HTTP redirect, some feed readers' simple HTTP implementations don't follow it
        //Header("Location: ".$filename);

        Header("Content-Type: ".$this->contentType."; filename=".basename($filename));
        Header("Content-Disposition: inline; filename=".basename($filename));
        readfile($filename, "r");
        die();
    }
    # }}}

    # {{{ useCached
    /**
     * Turns on caching and checks if there is a recent version of this feed in the cache.
     * If there is, an HTTP redirect header is sent.
     * To effectively use caching, you should create the FeedCreator object and call this method
     * before anything else, especially before you do the time consuming task to build the feed
     * (web fetching, for example).
     * @since 1.4
     * @param filename    string    optional    the filename where a recent version of the feed is saved. If not specified, the filename is $_SERVER["PHP_SELF"] with the extension changed to .xml (see _generateFilename()).
     * @param timeout    int        optional    the timeout in seconds before a cached version is refreshed (defaults to 3600 = 1 hour)
     */
    function useCached($filename="", $timeout=3600) {
        $this->_timeout = $timeout;
        if ($filename=="") {
            $filename = $this->_generateFilename();
        }
        if (file_exists($filename) AND (time()-filemtime($filename) < $timeout)) {
            $this->_redirect($filename);
        }
    }
    # }}}

    # {{{ saveFeed
    /**
     * Saves this feed as a file on the local disk. After the file is saved, a redirect
     * header may be sent to redirect the user to the newly created file.
     * @since 1.4
     *
     * @param filename    string    optional    the filename where a recent version of the feed is saved. If not specified, the filename is $_SERVER["PHP_SELF"] with the extension changed to .xml (see _generateFilename()).
     * @param redirect    boolean    optional    send an HTTP redirect header or not. If true, the user will be automatically redirected to the created file.
     */
    function saveFeed($filename="", $displayContents=true) {
        if ($filename=="") {
            $filename = $this->_generateFilename();
        }
        $feedFile = fopen($filename, "w+");
        if ($feedFile) {
            fputs($feedFile,$this->createFeed());
            fclose($feedFile);
            if ($displayContents) {
                $this->_redirect($filename);
            }
        } else {
            echo "<br /><b>Error creating feed file, please check write permissions.</b><br />";
        }
    }
    # }}}
}


/**
* FeedDate is an internal class that stores a date for a feed or feed item.
* Usually, you won't need to use this.
*/
class FeedDate {
    var $unix;

    # {{{ __construct
    /**
     * Creates a new instance of FeedDate representing a given date.
     * Accepts RFC 822, ISO 8601 date formats as well as unix time stamps.
     * @param mixed $dateString optional the date this FeedDate will represent. If not specified, the current date and time is used.
     */
    function FeedDate($dateString="") {
        if ($dateString=="") $dateString = date("r");
        
        if (is_integer($dateString)) {
            $this->unix = $dateString;
            return;
        }
        if (preg_match("~(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),\\s+)?(\\d{1,2})\\s+([a-zA-Z]{3})\\s+(\\d{4})\\s+(\\d{2}):(\\d{2}):(\\d{2})\\s+(.*)~",$dateString,$matches)) {
            $months = Array("Jan"=>1,"Feb"=>2,"Mar"=>3,"Apr"=>4,"May"=>5,"Jun"=>6,"Jul"=>7,"Aug"=>8,"Sep"=>9,"Oct"=>10,"Nov"=>11,"Dec"=>12);
            $this->unix = mktime($matches[4],$matches[5],$matches[6],$months[$matches[2]],$matches[1],$matches[3]);
            if (substr($matches[7],0,1)=='+' OR substr($matches[7],0,1)=='-') {
                $tzOffset = (substr($matches[7],0,3) * 60 + substr($matches[7],-2)) * 60;
            } else {
                if (strlen($matches[7])==1) {
                    $oneHour = 3600;
                    $ord = ord($matches[7]);
                    if ($ord < ord("M")) {
                        $tzOffset = (ord("A") - $ord - 1) * $oneHour;
                    } elseif ($ord >= ord("M") AND $matches[7]!="Z") {
                        $tzOffset = ($ord - ord("M")) * $oneHour;
                    } elseif ($matches[7]=="Z") {
                        $tzOffset = 0;
                    }
                }
                switch ($matches[7]) {
                    case "UT":
                    case "GMT":    $tzOffset = 0;
                }
            }
            $this->unix += $tzOffset;
            return;
        }
        if (preg_match("~(\\d{4})-(\\d{2})-(\\d{2})T(\\d{2}):(\\d{2}):(\\d{2})(.*)~",$dateString,$matches)) {
            $this->unix = mktime($matches[4],$matches[5],$matches[6],$matches[2],$matches[3],$matches[1]);
            if (substr($matches[7],0,1)=='+' OR substr($matches[7],0,1)=='-') {
                $tzOffset = (substr($matches[7],0,3) * 60 + substr($matches[7],-2)) * 60;
            } else {
                if ($matches[7]=="Z") {
                    $tzOffset = 0;
                }
            }
            $this->unix += $tzOffset;
            return;
        }
        $this->unix = 0;
    }
    # }}}

    # {{{ rfc822
    /**
     * Gets the date stored in this FeedDate as an RFC 822 date.
     *
     * @return a date in RFC 822 format
     */
    function rfc822() {
       return gmdate("r",$this->unix);
    }
    # }}}

    # {{{ iso8601
    /**
     * Gets the date stored in this FeedDate as an ISO 8601 date.
     *
     * @return a date in ISO 8601 format
     */
    function iso8601() {
        $date = gmdate("Y-m-d\TH:i:sO",$this->unix);
        $date = substr($date,0,22) . ':' . substr($date,-2);
        return $date;
    }
    # }}}

    # {{{ unix
    /**
     * Gets the date stored in this FeedDate as unix time stamp.
     *
     * @return a date as a unix time stamp
     */
    function unix() {
        return $this->unix;
    }
    # }}}
}


/**
* RSSCreator10 is a FeedCreator that implements RDF Site Summary (RSS) 1.0.
*
* @see http://www.purl.org/rss/1.0/
* @since 1.3
* @author Kai Blankenhorn <kaib@bitfolge.de>
*/
class RSSCreator10 extends FeedCreator {

    # {{{ createFeed
    /**
     * Builds the RSS feed's text. The feed will be compliant to RDF Site Summary (RSS) 1.0.
     * The feed will contain all items previously added in the same order.
     * @return    string    the feed's complete text
     */
    function createFeed() {
        global $config;     
        $feed = "<?xml version=\"1.0\" encoding=\"".$config->outputEnc."\"?>\n";
        $feed.= "<?xml-stylesheet href=\"http://www.w3.org/2000/08/w3c-synd/style.css\" type=\"text/css\"?>\n";
        $feed.= $this->_createGeneratorComment();
        $feed.= "<rdf:RDF\n";
        $feed.= "    xmlns=\"http://purl.org/rss/1.0/\"\n";
        $feed.= "    xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
        $feed.= "    xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
        $feed.= "    <channel rdf:about=\"".htmlspecialchars($this->syndicationURL)."\">\n";
        $feed.= "        <title>".htmlspecialchars($this->title)."</title>\n";
        $feed.= "        <description>".htmlspecialchars($this->description)."</description>\n";
        $feed.= "        <link>".htmlspecialchars($this->link)."</link>\n";
        if ($this->image!=null) {
            $feed.= "        <image rdf:resource=\"".$this->image->url."\" />\n";
        }
        $now = new FeedDate();
        $feed.= "       <dc:date>".htmlspecialchars($now->iso8601())."</dc:date>\n";
        $feed.= "        <items>\n";
        $feed.= "            <rdf:Seq>\n";
        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "                <rdf:li rdf:resource=\"".htmlspecialchars($this->items[$i]->link)."\"/>\n";
        }
        $feed.= "            </rdf:Seq>\n";
        $feed.= "        </items>\n";
        $feed.= "    </channel>\n";
        if ($this->image!=null) {
            $feed.= "    <image rdf:about=\"".$this->image->url."\">\n";
            $feed.= "        <title>".$this->image->title."</title>\n";
            $feed.= "        <link>".$this->image->link."</link>\n";
            $feed.= "        <url>".$this->image->url."</url>\n";
            $feed.= "    </image>\n";
        }
        //$feed.= $this->_createAdditionalElements($this->items[$i]->additionalElements, "    ");
        
        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "    <item rdf:about=\"".htmlspecialchars($this->items[$i]->link)."\">\n";
            //$feed.= "        <dc:type>Posting</dc:type>\n";
            $feed.= "        <dc:format>text/html</dc:format>\n";
            if ($this->items[$i]->date!=null) {
                $itemDate = new FeedDate($this->items[$i]->date);
                $feed.= "        <dc:date>".htmlspecialchars($itemDate->iso8601())."</dc:date>\n";
            }
            if ($this->items[$i]->source!="") {
                $feed.= "        <dc:source>".htmlspecialchars($this->items[$i]->source)."</dc:source>\n";
            }
            if ($this->items[$i]->author!="") {
                $feed.= "        <dc:creator>".htmlspecialchars($this->items[$i]->author)."</dc:creator>\n";
            }
            $feed.= "        <title>".htmlspecialchars(strip_tags(strtr($this->items[$i]->title,"\n\r","  ")))."</title>\n";
            $feed.= "        <link>".htmlspecialchars($this->items[$i]->link)."</link>\n";
            $feed.= "        <description>".htmlspecialchars($this->items[$i]->description)."</description>\n";
            $feed.= $this->_createAdditionalElements($this->items[$i]->additionalElements, "        ");
            $feed.= "    </item>\n";
        }
        $feed.= "</rdf:RDF>\n";
        return $feed;
    }
    # }}}
}



/**
* RSSCreator091 is a FeedCreator that implements RSS 0.91 Spec, revision 3.
*
* @see http://my.netscape.com/publish/formats/rss-spec-0.91.html
* @since 1.3
* @author Kai Blankenhorn <kaib@bitfolge.de>
*/
class RSSCreator091 extends FeedCreator {

    /**
     * Stores this RSS feed's version number.
     * @access private
     */
    var $RSSVersion;

    # {{{ __construct
    function RSSCreator091() {
        $this->_setRSSVersion("0.91");
        $this->contentType = "application/rss+xml";
    }
    # }}}

    # {{{ _setRSSVersion
    /**
     * Sets this RSS feed's version number.
     * @access private
     */
    function _setRSSVersion($version) {
        $this->RSSVersion = $version;
    }
    # }}}

    # {{{ createFeed
    /**
     * Builds the RSS feed's text. The feed will be compliant to RDF Site Summary (RSS) 1.0.
     * The feed will contain all items previously added in the same order.
     * @return    string    the feed's complete text
     */
    function createFeed() {
        global $config;     
        $feed = "<?xml version=\"1.0\" encoding=\"".$config->outputEnc."\"?>\n";
        $feed.= $this->_createGeneratorComment();
        $feed.= "<rss version=\"".$this->RSSVersion."\">\n";
        $feed.= "    <channel>\n";
        $feed.= "        <title>".FeedCreator::iTrunc(htmlspecialchars($this->title),100)."</title>\n";
        $feed.= "        <description>".FeedCreator::iTrunc(htmlspecialchars($this->description),500)."</description>\n";
        $feed.= "        <link>".htmlspecialchars($this->link)."</link>\n";
        $now = new FeedDate();
        $feed.= "        <lastBuildDate>".htmlspecialchars($now->rfc822())."</lastBuildDate>\n";
        $feed.= "        <generator>".FEEDCREATOR_VERSION."</generator>\n";

        if ($this->image!=null) {
            $feed.= "        <image>\n";
            $feed.= "            <url>".$this->image->url."</url>\n";
            $feed.= "            <title>".FeedCreator::iTrunc(htmlspecialchars($this->image->title),100)."</title>\n";
            $feed.= "            <link>".$this->image->link."</link>\n";
            if ($this->image->width!="") {
                $feed.= "            <width>".$this->image->width."</width>\n";
            }
            if ($this->image->height!="") {
                $feed.= "            <height>".$this->image->height."</height>\n";
            }
            if ($this->image->description!="") {
                $feed.= "            <description>".htmlspecialchars($this->image->description)."</description>\n";
            }
            $feed.= "        </image>\n";
        }
        if ($this->language!="") {
            $feed.= "        <language>".$this->language."</language>\n";
        }
        if ($this->copyright!="") {
            $feed.= "        <copyright>".FeedCreator::iTrunc(htmlspecialchars($this->copyright),100)."</copyright>\n";
        }
        if ($this->editor!="") {
            $feed.= "        <managingEditor>".FeedCreator::iTrunc(htmlspecialchars($this->editor),100)."</managingEditor>\n";
        }
        if ($this->webmaster!="") {
            $feed.= "        <webMaster>".FeedCreator::iTrunc(htmlspecialchars($this->webmaster),100)."</webMaster>\n";
        }
        if ($this->pubDate!="") {
            $pubDate = new FeedDate($this->pubDate);
            $feed.= "        <pubDate>".htmlspecialchars($pubDate->rfc822())."</pubDate>\n";
        }
        if ($this->category!="") {
            $feed.= "        <category>".htmlspecialchars($this->category)."</category>\n";
        }
        if ($this->docs!="") {
            $feed.= "        <docs>".FeedCreator::iTrunc(htmlspecialchars($this->docs),500)."</docs>\n";
        }
        if ($this->ttl!="") {
            $feed.= "        <ttl>".htmlspecialchars($this->ttl)."</ttl>\n";
        }
        if ($this->rating!="") {
            $feed.= "        <rating>".FeedCreator::iTrunc(htmlspecialchars($this->rating),500)."</rating>\n";
        }
        if ($this->skipHours!="") {
            $feed.= "        <skipHours>".htmlspecialchars($this->skipHours)."</skipHours>\n";
        }
        if ($this->skipDays!="") {
            $feed.= "        <skipDays>".htmlspecialchars($this->skipDays)."</skipDays>\n";
        }
        $feed.= $this->_createAdditionalElements($this->additionalElements, "    ");

        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "        <item>\n";
            $feed.= "            <title>".FeedCreator::iTrunc(htmlspecialchars(strip_tags($this->items[$i]->title)),100)."</title>\n";
            $feed.= "            <link>".htmlspecialchars($this->items[$i]->link)."</link>\n";
            $feed.= "            <description>".htmlspecialchars($this->items[$i]->description)."</description>\n";
            if ($this->items[$i]->author!="") {
                $feed.= "            <author>".htmlspecialchars($this->items[$i]->author)."</author>\n";
            }
            /*
            // on hold
            if ($this->items[$i]->source!="") {
                    $feed.= "            <source>".htmlspecialchars($this->items[$i]->source)."</source>\n";
            }
            */
            if ($this->items[$i]->category!="") {
                $feed.= "            <category>".htmlspecialchars($this->items[$i]->category)."</category>\n";
            }
            if ($this->items[$i]->comments!="") {
                $feed.= "            <comments>".$this->items[$i]->comments."</comments>\n";
            }
            if ($this->items[$i]->date!="") {
            $itemDate = new FeedDate($this->items[$i]->date);
                $feed.= "            <pubDate>".htmlspecialchars($itemDate->rfc822())."</pubDate>\n";
            }
            if ($this->items[$i]->guid!="") {
                $feed.= "            <guid>".$this->items[$i]->guid."</guid>\n";
            }
            $feed.= $this->_createAdditionalElements($this->items[$i]->additionalElements, "        ");
            $feed.= "        </item>\n";
        }
        $feed.= "    </channel>\n";
        $feed.= "</rss>\n";
        return $feed;
    }
    # }}}
}



/**
* RSSCreator20 is a FeedCreator that implements RDF Site Summary (RSS) 2.0.
*
* @see http://backend.userland.com/rss
* @since 1.3
* @author Kai Blankenhorn <kaib@bitfolge.de>
*/
class RSSCreator20 extends RSSCreator091 {

    # {{{ __construct
    function RSSCreator20() {
        parent::_setRSSVersion("2.0");
    }
    # }}}

}


/**
* PIECreator01 is a FeedCreator that implements the emerging PIE specification,
* as in http://intertwingly.net/wiki/pie/Syntax.
*
* @deprecated
* @since 1.3
* @author Scott Reynen <scott@randomchaos.com> and Kai Blankenhorn <kaib@bitfolge.de>
*/
class PIECreator01 extends FeedCreator {

    # {{{ createFeed
    function createFeed() {
        $feed = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        $feed.= "<feed version=\"0.1\" xmlns=\"http://example.com/newformat#\">\n";
        $feed.= "    <title>".FeedCreator::iTrunc(htmlspecialchars($this->title),100)."</title>\n";
        $feed.= "    <subtitle>".FeedCreator::iTrunc(htmlspecialchars($this->description),500)."</subtitle>\n";
        $feed.= "    <link>".$this->link."</link>\n";
        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "    <entry>\n";
            $feed.= "        <title>".FeedCreator::iTrunc(htmlspecialchars(strip_tags($this->items[$i]->title)),100)."</title>\n";
            $feed.= "        <link>".htmlspecialchars($this->items[$i]->link)."</link>\n";
            $itemDate = new FeedDate($this->items[$i]->date);
            $feed.= "        <created>".htmlspecialchars($itemDate->iso8601())."</created>\n";
            $feed.= "        <issued>".htmlspecialchars($itemDate->iso8601())."</issued>\n";
            $feed.= "        <modified>".htmlspecialchars($itemDate->iso8601())."</modified>\n";
            $feed.= "        <id>".$this->items[$i]->guid."</id>\n";
            if ($this->items[$i]->author!="") {
                $feed.= "        <author>\n";
                $feed.= "            <name>".htmlspecialchars($this->items[$i]->author)."</name>\n";
                if ($this->items[$i]->authorEmail!="") {
                    $feed.= "            <email>".$this->items[$i]->authorEmail."</email>\n";
                }
                $feed.="        </author>\n";
            }
            $feed.= "        <content type=\"text/html\" xml:lang=\"en-us\">\n";
            $feed.= "            <div xmlns=\"http://www.w3.org/1999/xhtml\">".$this->items[$i]->description."</div>\n";
            $feed.= "        </content>\n";
            $feed.= "    </entry>\n";
        }
        $feed.= "</feed>\n";
        return $feed;
    }
    # }}}
}


/**
* AtomCreator03 is a FeedCreator that implements the atom specification,
* as in http://www.intertwingly.net/wiki/pie/FrontPage.
* Please note that just by using AtomCreator03 you won't automatically
* produce valid atom files. For example, you have to specify either an editor
* for the feed or an author for every single feed item.
*
* Some elements have not been implemented yet. These are (incomplete list):
* author URL, item author's email and URL, item contents, alternate links,
* other link content types than text/html. Some of them may be created with
* AtomCreator03::additionalElements.
*
* @see FeedCreator#additionalElements
* @since 1.6
* @author Kai Blankenhorn <kaib@bitfolge.de>, Scott Reynen <scott@randomchaos.com>
*/
class AtomCreator03 extends FeedCreator {

    # {{{ __construct
    function AtomCreator03() {
        $this->contentType = "application/atom+xml";
    }
    # }}}

    # {{{ createFeed
    function createFeed() {
        $feed = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        $feed.= $this->_createGeneratorComment();
        $feed.= "<feed version=\"0.3\" xmlns=\"http://purl.org/atom/ns#\"";
        if ($this->language!="") {
            $feed.= " xml:lang:\"".$this->language."\"";
        }
        $feed.= ">\n";
        $feed.= "    <title>".htmlspecialchars($this->title)."</title>\n";
        $feed.= "    <tagline>".htmlspecialchars($this->description)."</tagline>\n";
        $feed.= "    <link rel=\"alternate\" type=\"text/html\" href=\"".htmlspecialchars($this->link)."\"/>\n";
        $feed.= "    <id>".$this->link."</id>\n";
        $now = new FeedDate();
        $feed.= "    <modified>".htmlspecialchars($now->iso8601())."</modified>\n";
        if ($this->editor!="") {
            $feed.= "    <author>\n";
            $feed.= "        <name>".$this->editor."</name>\n";
            if ($this->editorEmail!="") {
                $feed.= "        <email>".$this->editorEmail."</email>\n";
            }
            $feed.= "    </author>\n";
        }
        $feed.= "    <generator>".FEEDCREATOR_VERSION."</generator>\n";
        $feed.= $this->_createAdditionalElements($this->additionalElements, "    ");
        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "    <entry>\n";
            $feed.= "        <title>".htmlspecialchars(strip_tags($this->items[$i]->title))."</title>\n";
            $feed.= "        <link rel=\"alternate\" type=\"text/html\" href=\"".htmlspecialchars($this->items[$i]->link)."\"/>\n";
            if ($this->items[$i]->date=="") {
                $this->items[$i]->date = time();
            }
            $itemDate = new FeedDate($this->items[$i]->date);
            $feed.= "        <created>".htmlspecialchars($itemDate->iso8601())."</created>\n";
            $feed.= "        <issued>".htmlspecialchars($itemDate->iso8601())."</issued>\n";
            $feed.= "        <modified>".htmlspecialchars($itemDate->iso8601())."</modified>\n";
            $feed.= "        <id>".$this->items[$i]->link."</id>\n";
            $feed.= $this->_createAdditionalElements($this->items[$i]->additionalElements, "        ");
            if ($this->items[$i]->author!="") {
                $feed.= "        <author>\n";
                $feed.= "            <name>".htmlspecialchars($this->items[$i]->author)."</name>\n";
                $feed.= "        </author>\n";
            }
            if ($this->items[$i]->description!="") {
                $feed.= "        <summary>".htmlspecialchars($this->items[$i]->description)."</summary>\n";
            }
            $feed.= "    </entry>\n";
        }
        $feed.= "</feed>\n";
        return $feed;
    }
    # }}}
}


/**
* MBOXCreator is a FeedCreator that implements the mbox format
* as described in http://www.qmail.org/man/man5/mbox.html
*
* @since 1.3
* @author Kai Blankenhorn <kaib@bitfolge.de>
*/
class MBOXCreator extends FeedCreator {

    # {{{ __construct
    function MBOXCreator() {
        $this->contentType = "text/plain";
    }
    # }}}

    # {{{ qp_enc
    function qp_enc($input = "", $line_max = 76) {
        $hex = array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F');
        $lines = preg_split("/(?:\r\n|\r|\n)/", $input);
        $eol = "\r\n";
        $escape = "=";
        $output = "";
        while( list(, $line) = each($lines) ) {
            //$line = rtrim($line); // remove trailing white space -> no =20\r\n necessary
            $linlen = strlen($line);
            $newline = "";
            for($i = 0; $i < $linlen; $i++) {
                $c = substr($line, $i, 1);
                $dec = ord($c);
                if ( ($dec == 32) && ($i == ($linlen - 1)) ) { // convert space at eol only
                    $c = "=20";
                } elseif ( ($dec == 61) || ($dec < 32 ) || ($dec > 126) ) { // always encode "\t", which is *not* required
                    $h2 = floor($dec/16); $h1 = floor($dec%16);
                    $c = $escape.$hex["$h2"].$hex["$h1"];
                }
                if ( (strlen($newline) + strlen($c)) >= $line_max ) { // CRLF is not counted
                    $output .= $newline.$escape.$eol; // soft line break; " =\r\n" is okay
                    $newline = "";
                }
                $newline .= $c;
            } // end of for
            $output .= $newline.$eol;
        }
        return trim($output);
    }
    # }}}

    # {{{ createFeed
    /**
     * Builds the MBOX contents.
     * @return    string    the feed's complete text
     */
    function createFeed() {
        global $config;
        for ($i=0;$i<count($this->items);$i++) {
            if ($this->items[$i]->author!="") {
                $from = $this->items[$i]->author;
            } else {
                $from = $this->title;
            }
            $itemDate = new FeedDate($this->items[$i]->date);
            $feed.= "From ".strtr(MBOXCreator::qp_enc($from)," ","_")." ".date("D M d H:i:s Y",$itemDate->unix())."\n";
            $feed.= "Content-Type: text/plain;\n";
            $feed.= "    charset=\"".$config->outputEnc."\"\n";
            $feed.= "Content-Transfer-Encoding: quoted-printable\n";
            $feed.= "Content-Type: text/plain\n";
            $feed.= "From: \"".MBOXCreator::qp_enc($from)."\"\n";
            $feed.= "Date: ".$itemDate->rfc822()."\n";
            $feed.= "Subject: ".MBOXCreator::qp_enc(FeedCreator::iTrunc($this->items[$i]->title,100))."\n";
            $feed.= "\n";
            $body = chunk_split(MBOXCreator::qp_enc($this->items[$i]->description));
            $feed.= preg_replace("~\nFrom ([^\n]*)(\n?)~","\n>From $1$2\n",$body);
            $feed.= "\n";
            $feed.= "\n";
        }
        return $feed;
    }
    # }}}

    # {{{ _generateFilename
    /**
     * Generate a filename for the feed cache file. Overridden from FeedCreator to prevent XML data types.
     * @return string the feed cache filename
     * @since 1.4
     * @access private
     */
    function _generateFilename() {
        $fileInfo = pathinfo($_SERVER["PHP_SELF"]);
        return substr($fileInfo["basename"],0,-(strlen($fileInfo["extension"])+1)).".mbox";
    }
    # }}}
}


/**
* OPMLCreator is a FeedCreator that implements OPML 1.0.
*
* @see http://opml.scripting.com/spec
* @author Dirk Clemens, Kai Blankenhorn
* @since 1.5
*/
class OPMLCreator extends FeedCreator {
    
    # {{{ createFeed
    function createFeed() {     
        $feed = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        $feed.= $this->_createGeneratorComment();
        $feed.= "<opml xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
        $feed.= "    <head>\n";
        $feed.= "        <title>".htmlspecialchars($this->title)."</title>\n";
        if ($this->pubDate!="") {
            $date = new FeedDate($this->pubDate);
            $feed.= "         <dateCreated>".$date->rfc822()."</dateCreated>\n";
        }
        if ($this->lastBuildDate!="") {
            $date = new FeedDate($this->lastBuildDate);
            $feed.= "         <dateModified>".$date->rfc822()."</dateModified>\n";
        }
        if ($this->editor!="") {
            $feed.= "         <ownerName>".$this->editor."</ownerName>\n";
        }
        if ($this->editorEmail!="") {
            $feed.= "         <ownerEmail>".$this->editorEmail."</ownerEmail>\n";
        }
        $feed.= "    </head>\n";
        $feed.= "    <body>\n";
        for ($i=0;$i<count($this->items);$i++) {
            $feed.= "    <outline type=\"rss\" ";
            $title = htmlspecialchars(strip_tags(strtr($this->items[$i]->title,"\n\r","  ")));
            $feed.= " title=\"".$title."\"";
            $feed.= " text=\"".$title."\"";
            //$feed.= " description=\"".htmlspecialchars($this->items[$i]->description)."\"";
            $feed.= " url=\"".htmlspecialchars($this->items[$i]->link)."\"";
            $feed.= "/>\n";
        }
        $feed.= "    </body>\n";
        $feed.= "</opml>\n";
        return $feed;
    }
    # }}}
}

?>
