<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'vdh_flickr';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.8.12';
$plugin['author'] = 'Ralph von der Heyden';
$plugin['author_uri'] = 'http://www.rvdh.net/vdh_flickr';
$plugin['description'] = 'Shows your flickr.com pictures in TextPattern.';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/* vdh_flickr Textpattern plugin
*
* Author: Ralph von der Heyden <flickr@rvdh.net>
*         http://www.rvdh.net/vdh_flickr
*
*
* License: GPL
*
*/


/* Here you can translate or adjust things as you like.
*
*/
global $text;
$text = array();
$text['error_message'] = 'Failed to connect to flickr.com!';
$text['latest'] = 'My latest Pictures...';

global $clean_urls;
//$clean_urls = 1;

global $nsid;
// $nsid = '12345678@N00';


class Vdh_Flickr {
	var $api_key, $email, $nsid, $password, $userdata, $xml, $form, $use_articleurl;

	public function Vdh_Flickr($params) {
		$this->api_key = 'c34e40dc707f9bc52736dba56811893f';
		if(isset($params['error_message'])) {
			$GLOBALS['text']['error_message'] = $params['error_message'];
		}
		if(isset($params['clean_urls'])) {
			$GLOBALS['clean_urls'] = $params['clean_urls'];
		}
		$mainversion = strtok (PHP_VERSION,".");
		if(!($mainversion >= 5)) {
			$GLOBALS['use_php4'] = 1;
		}
		if (isset($params['email'])) $this->email = $params['email'];
		(isset($GLOBALS['nsid']))?
		$this->nsid = $GLOBALS['nsid']:
		$this->nsid = $params['nsid'];
		if ($GLOBALS['is_article_list'] === false) $this->use_articleurl = true;
		if (isset($params['use_articleurl']) and $params['use_articleurl'] == 1) $this->use_articleurl = true;
		if (isset($params['use_articleurl']) and $params['use_articleurl'] == 0) $this->use_articleurl = false;
		if (!isset($this->nsid)) $this->getNsid();
		$this->userdata = '&api_key=' . $this->api_key . '&user_id=' . $this->nsid;
		if (isset($params['password'])) {
			$this->password = $params['password'];
			$this->userdata .= '&password=' . $this->password;
		}
		if (isset($this->email)) $this->userdata .= '&email=' . $this->email;
	}

	function getNsid() {
		$method = 'flickr.people.findByEmail&api_key=' . $this->api_key . '&find_email=' . $this->email;
		$this->xml = new Flickr($method);
		if($this->xml->isValid()) {
			$this->nsid = array_shift($this->xml->xpath('/rsp/user/@id'));
		}
	}

	function makeRequest($parameters) {
		$keys = array_keys($parameters);
		if ($GLOBALS['permlink_mode'] == 'messy') {
			($this->use_articleurl)?
			$html = permlinkurl($GLOBALS['thisarticle']):
			$html = hu.'?s='.urlencode($GLOBALS['s']);
			for($i = 0; $i < sizeof($parameters); $i++) {
				$html .= '&amp;' . $keys[$i] . '=' . $parameters[$keys[$i]];
			}
			return $html;
		}
		else if ($GLOBALS['clean_urls']) {
			($this->use_articleurl)?
			$html = permlinkurl($GLOBALS['thisarticle']):
			$html = hu.urlencode($GLOBALS['s']);
			for($i = 0; $i < sizeof($parameters); $i++) {
				$html .= '/' . $keys[$i] . '/' . $parameters[$keys[$i]];
			}
			return $html;
		}
		else {
			($this->use_articleurl)?
			$html = permlinkurl($GLOBALS['thisarticle']) . '/?' . $keys[0] . '=' .$parameters[$keys[0]]:
			$html = hu.urlencode($GLOBALS['s']) . '/?' . $keys[0] . '=' .$parameters[$keys[0]];
			for($i = 1; $i < sizeof($parameters); $i++) {
				$html .= '&amp;' . $keys[$i] . '=' . $parameters[$keys[$i]];
			}
		}
		return $html;
	}

	function getPhotoUrl($photo, $size) {
		$farm = $photo['farm'];
		$server = $photo['server'];
		$id = (isset($photo['primary']))? $photo['primary'] : $photo['id'];
		$secret = $photo['secret'];
		$format = "jpg";
		$imgsize = ("n" != $size)? "_" . $size : "";
		if ("o" == $size)
		{
			$secret = $photo['original_secret'];
			$format = $photo['original_format'];
		}
		$img_url = "http://farm${farm}.static.flickr.com/${server}/${id}_${secret}${imgsize}.${format}";
		return $img_url;
	}

	function __toString() {
		return $this->nsid;
	}
}


class Gallery extends Vdh_Flickr {
	var $xml, $i, $set, $set_preview_size, $mode;
	var $sets_per_page, $page, $previous_page, $next_page, $lastpage, $number_of_sets;
	var $exceptions = array();
	var $sets = array();

	public function Gallery($params) {
		$this->Vdh_Flickr($params);
		if (isset($params['except'])) $this->exceptions = explode(",", $params['except']);
		(isset($params['set_preview_size']))?
		$this->set_preview_size = @$params['set_preview_size']:
		$this->set_preview_size = 'm';
		(isset($_GET['page']))?
		$this->page = $_GET['page']:
		$this->page = 1;
		(!empty($params['mode']))?
		$this->mode = $params['mode']:
		$this->mode = 'all';
		$this->getSets($params);
		if (isset($params['sets_per_page'])) {
			$this->sets_per_page = $params['thumbs_per_page'];
			$this->lastpage = ceil($this->number_of_sets / $this->sets_per_page);
			if ($this->page-1 >= 1) {
				$this->previous_page = $this->page - 1;
			}
			else {
				$this->previous_page = $this->lastpage;
			}
			if ($this->page+1 <= $this->lastpage) {
				$this->next_page = $this->page + 1;
			}
			else {
				$this->next_page =  1;
			}
		}
		if (isset($params['set_form'])) {
			$this->form = fetch('Form','txp_form',"name",$params['set_form']);
		}
		else {
			$this->form = '
			<div class="setpreview">
			<div class="thumbnail">
			<txp:vdh_flickr_set_link title="Proceed to this gallery"><txp:vdh_flickr_set_img title="Proceed to this gallery" /></txp:vdh_flickr_set_link>
			</div>
			<div>
			<h3 class="title"><txp:vdh_flickr_set_link title="Proceed to this gallery"><txp:vdh_flickr_set_title /></txp:vdh_flickr_set_link></h3>
			<h4 class="number_of_photos"><txp:vdh_flickr_set_number_of_photos /> Photos</h4>
			<p class="set_description"><txp:vdh_flickr_set_description /></p>
			</div>
			<div style="clear:both;"></div>
			</div>';
		}
	}

	function getSets($params) {
		$method = 'flickr.photosets.getList' . $this->userdata;
		$this->xml = new Flickr($method);
		if($this->xml->isValid()) {
			$i=1;
			foreach($this->xml->xpath('/rsp/photosets/photoset') as $photoset) {
				$set_id = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@id"));
				if($this->mode=='all') {
					if(!in_array($set_id, $this->exceptions)) {
						$this->add_to_sets($set_id, $i);
					}
				}
				if($this->mode=='none') {
					if(in_array($set_id, $this->exceptions)) {
						$this->add_to_sets($set_id, $i);
					}
				}
				$i++;
			}
		}
	}

	function add_to_sets($set_id, $i) {
		$set = array('id' => $set_id);
		$set['title'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/title/text()"));
		$set['description'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/description/text()"));
		$set['primary'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@primary"));
		$set['farm'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@farm"));
		$set['secret'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@secret"));
		$set['server'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@server"));
		$set['photos'] = array_shift($this->xml->xpath("/rsp/photosets/photoset[$i]/@photos"));
		array_push($this->sets, $set);
	}

	function set_img($params) {
		$img_url = $this->getPhotoUrl($this->set, $this->set_preview_size);
		$img_url = '<img src="' . $img_url . '" alt="' . $this->titles[$this->i];
		(isset($params['title']))? $img_url .= '" title="' . $params['title'] . '" />' : $img_url .= '" />';
		return $img_url;
	}

	function set_link($params, $thing) {
		$what = ' href="'. $this->makeRequest(array('set' => $this->set['id'])) .'"';
		if (isset($params['title'])) $what .= ' title="' . $params['title'] . '"';
		$html = tag($thing,'a',$what);
		return $html;
	}

	function set_title() {
		$html = $this->set['title'];
		return $html;
	}

	function set_number_of_photos() {
		$html = $this->set['photos'];
		return $html;
	}

	function set_description() {
		$html = $this->set['description'];
		return $html;
	}

	function set_list($params) {
		if(!$this->xml->isValid()) {
			return false;
		}
		(isset($params['wraptag']) == true)? $wraptag = $params['wraptag'] : $wraptag = '';
		(isset($params['break']) == true)? $break = $params['break'] : $break = 'br';
		$this->i = 0;
		foreach ($this->sets as $this->set) {
			($_GET['set'] == (float) $this->set['id'])? $param = ' class="current"' : '';
			($break != 'br')?
			$html .= tag($this->set_link('', $this->set_title()), $break, $param) . "\n":
			$html .= $this->set_link('', $this->set_title()) .'<br />' . "\n";
			$this->i++;
			unset($param);
		}
		return (($wraptag != '') == true)? tag($html, $wraptag) : $html;
	}

	function __toString() {
		if(!$this->xml->isValid()) {
			return $GLOBALS['text']['error_message'];
		}
		$this->i = 0;
		$result = '';
		foreach ($this->sets as $this->set) {
			$result .= parse($this->form);
			$this->i++;
		}
		return $result;
	}
}


class Thumbnails extends Vdh_Flickr {
	var $xml, $id, $owner, $primary, $secret, $number_of_photos, $title, $description, $set, $photo, $latest;
	var $thumbnail_size, $img_size, $open, $tag_and, $link, $use_art_id_as_tag;
	var $page, $thumbs_per_page, $start, $end, $lastpage, $previous_page, $next_page;
	var $photos = array(), $tags = array();

	public function Thumbnails($params) {
		$this->Vdh_Flickr($params);
		$this->params = $params;
		(isset($params['thumbnail_size']))?
		$this->thumbnail_size = $params['thumbnail_size']:
		$this->thumbnail_size = 's';
		(isset($params['img_size']))?
		$this->img_size = $params['img_size']:
		$this->img_size = 'n';
		(isset($params['open']))?
		$this->open = $params['open']:
		$this->open = 'self';
		(isset($params['set']))?
		$this->set = $params['set']:
		$this->set = $_GET['set'];
		(isset($_GET['tags']))?
		@$this->tags = $_GET['tags']:
		@$this->tags = $params['tags'];
		if(!empty($this->tags)) unset($this->set);
		(isset($_GET['page']))?
		$this->page = $_GET['page']:
		$this->page = 1;
		if (isset($params['latest'])) $this->latest = $params['latest'];
		(isset($params['linkthumbs']))?
		$this->linkthumbs = $params['linkthumbs']:
		$this->linkthumbs = 1;
		(isset($params['use_art_id_as_tag']))?
		$this->use_art_id_as_tag = $params['use_art_id_as_tag']:
		$this->use_art_id_as_tag = 0;
		if (isset($params['tag_and'])) $this->tag_and = 1;
		if (isset($params['thumbnails_form'])) $this->form = fetch('Form','txp_form',"name",$params['thumbnails_form']);
		else {
			$this->form = '
			<h3><txp:vdh_flickr_thumbnails_title />, <txp:vdh_flickr_thumbnails_number_of_photos /> Photos</h3>
			<p class="flickr_slideshow">
			<txp:vdh_flickr_thumbnails_slideshow>&raquo; Show as slideshow in new window.</txp:vdh_flickr_thumbnails_slideshow>
			</p>
			<txp:vdh_flickr_thumbnails_if_description>
			<p class="flickr_thumbnails_description">
			<txp:vdh_flickr_thumbnails_description />
			</p>
			</txp:vdh_flickr_thumbnails_if_description>
			<div class="flickrset">
			<txp:vdh_flickr_thumbnails_list />
			</div>
			<txp:vdh_flickr_thumbnails_if_multiple_pages>
			<h3 class="pages_nav">pages navigation</h3>
			<p>
			thumbs per page: <txp:vdh_flickr_thumbnails_per_page /><br />
			Showing page <txp:vdh_flickr_thumbnails_current_page /> of <txp:vdh_flickr_thumbnails_total_pages />.<br />
			Showing thumb <txp:vdh_flickr_thumbnails_pages_startthumb /> to <txp:vdh_flickr_thumbnails_pages_endthumb />.<br />
			<txp:vdh_flickr_thumbnails_pages_first>&laquo; first</txp:vdh_flickr_thumbnails_pages_first> |
			<txp:vdh_flickr_thumbnails_pages_previous>&lt; previous</txp:vdh_flickr_thumbnails_pages_previous> |
			<txp:vdh_flickr_thumbnails_pages_next>next &gt;</txp:vdh_flickr_thumbnails_pages_next> |
			<txp:vdh_flickr_thumbnails_pages_last>last &raquo;</txp:vdh_flickr_thumbnails_pages_last>
			</p>
			Go to page number:
			<txp:vdh_flickr_thumbnails_pages_list wraptag="ul" break="li" class ="thumbs_pages" />
			</txp:vdh_flickr_thumbnails_if_multiple_pages>
			<div style="clear:both;"></div>';
		}
		$this->getPhotos();
		$this->thumbs_per_page = 0;
		if (isset($params['thumbs_per_page'])) {
			$this->thumbs_per_page = $params['thumbs_per_page'];
			$this->lastpage = ceil($this->number_of_photos / $this->thumbs_per_page);
			if ($this->page-1 >= 1) {
				$this->previous_page = $this->page - 1;
			}
			else {
				$this->previous_page = $this->lastpage;
			}
			if ($this->page+1 <= $this->lastpage) {
				$this->next_page = $this->page + 1;
			}
			else {
				$this->next_page =  1;
			}
		}
	}

	function getPhotos() {
		if ($this->use_art_id_as_tag == 1) {
			global $thisarticle;
			if(isset($this->tags) and ($this->tags != '')) {
				$this->tags .= ',';
			}
			$this->tags .= 'article'.$thisarticle['thisid'];
		}
		if (isset($this->tags)) {
			$this->title = $this->tags;
			$method = 'flickr.photos.search' . $this->userdata . '&tags=' . $this->tags . '&per_page=500&extras=original_format';
			if (isset($this->tag_and)) {
				$method .= '&tag_mode=all';
			}
			$this->xml = new Flickr($method);
			if($this->xml->isValid()) {
				foreach($this->xml->xpath('/rsp/photos/photo/@id') as $photo_id) {
					$photo = array('id' => $photo_id);
					$photo['title'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@title"));
					$photo['secret'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@secret"));
					$photo['original_secret'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@originalsecret"));
					$photo['original_format'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@originalformat"));
					$photo['server'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@server"));
					$photo['farm'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@farm"));
					array_push($this->photos, $photo);
				}
				$this->number_of_photos = (string) array_shift($this->xml->xpath('/rsp/photos/@total'));
				if (isset($this->params['random'])) $this->randomize($this->params['random']);
			}
		}
		if (isset($this->latest)) {
			$this->title = $GLOBALS['text']['latest'];
			$method = 'flickr.photos.search' . $this->userdata;
			if ($this->thumbs_per_page == 0 or $this->latest <= $this->thumbs_per_page) $method .= '&per_page=' . $this->latest;
			else {
				$method .= '&per_page=' . $this->thumbs_per_page;
			}
			$method .= "&extras=original_format";
			$this->xml = new Flickr($method);
			if($this->xml->isValid()) {
				foreach($this->xml->xpath('/rsp/photos/photo/@id') as $photo_id) {
					$photo = array('id' => $photo_id);
					$photo['title'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@title"));
					$photo['secret'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@secret"));
					$photo['original_secret'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@originalsecret"));
					$photo['original_format'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@originalformat"));
					$photo['server'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@server"));
					$photo['farm'] = array_shift($this->xml->xpath("/rsp/photos/photo[@id=".$photo_id."]/@farm"));
					array_push($this->photos, $photo);
				}
				$this->number_of_photos = (string) $this->latest;
				if (isset($this->params['random'])) $this->randomize($this->params['random']);
			}
		}
		if (isset($this->set)) {
			$method = 'flickr.photosets.getPhotos' . $this->userdata . '&photoset_id=' . $this->set . "&extras=original_format";
			$this->xml = new Flickr($method);
			if($this->xml->isValid()) {
				foreach($this->xml->xpath('/rsp/photoset/photo/@id') as $photo_id) {
					$photo = array('id' => $photo_id);
					$photo['title'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@title"));
					$photo['secret'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@secret"));
					$photo['original_secret'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@originalsecret"));
					$photo['original_format'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@originalformat"));
					$photo['server'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@server"));
					$photo['farm'] = array_shift($this->xml->xpath("/rsp/photoset/photo[@id=".$photo_id."]/@farm"));
					array_push($this->photos, $photo);
				}
				$this->number_of_photos = (string) sizeof($this->photos);
				if (isset($this->params['random'])) $this->randomize($this->params['random']);
			}
		}
	}

	function randomize($randsize) {
		if ($randsize < $this->number_of_photos) {
			shuffle($this->photos);
			$this->photos = array_slice($this->photos, 0, $randsize);
			$this->number_of_photos = $randsize;
		}
	}

	function getInfo() {
		$method = 'flickr.photosets.getInfo' . $this->userdata . '&photoset_id=' . $this->set;
		$this->xml = new Flickr($method);
		if($this->xml->isValid()) {
			$this->id = array_shift($this->xml->xpath('/rsp/photoset/@id'));
			$this->primary = array_shift($this->xml->xpath('/rsp/photoset/@primary'));
			$this->secret = array_shift($this->xml->xpath('/rsp/photoset/@secret'));
			$this->title = array_shift($this->xml->xpath('/rsp/photoset/title/text()'));
			$this->description = array_shift($this->xml->xpath('/rsp/photoset/description/text()'));
			if (empty($this->description)) $this->description = '&nbsp;';
		}
	}

	function thumbnails_title() {
		if (isset($this->title) == false) $this->getInfo();
		return $this->title;
	}

	function thumbnails_description() {
		if ((isset($this->description) == false) and isset($this->set)) {
			$this->getInfo();
		}
		if ($this->description != '&nbsp;' and !empty($this->description)) return $this->description;
		return '';
	}

	function thumbnails_if_description ($thing) {
		if ((isset($this->description) == false) and isset($this->set)) {
			$this->getInfo();
		}
		if ($this->description != '&nbsp;' and !empty($this->description)) $result = parse($thing);
		return $result;
	}

	function thumbnails_slideshow($thing) {
		if(isset($this->tags)) {
			$html = '<a href="http://www.flickr.com/slideShow/index.gne?nsid='.urlencode($this->nsid).'&amp;user_id='.urlencode($this->nsid);
			if(isset($this->tag_and)) {
				$html .= '&amp;tag_mode=all&amp;tags='. urlencode($this->tags);
			}
			else {
				$html .= '&amp;tag_mode=any&amp;tags=' . urlencode($this->tags);
			}
			$html .= '"  onclick="window.open(this.href, \'slideShowWin\', \'width=800,height=600,top=150,left=70,scrollbars=no, status=no, resizable=no\'); ';
			$html .= 'return false;">';
		}
		if(isset($this->set)) {
			$html = '<a href="http://www.flickr.com/slideShow/index.gne?nsid='.urlencode($this->nsid).'&amp;set_id='.$this->set;
			$html .= '"  onclick="window.open(this.href, \'slideShowWin\', \'width=800,height=600,top=150,left=70,scrollbars=no, status=no, resizable=no\'); ';
			$html .= 'return false;">';
		}
		if(isset($this->latest)) {
			$html = '<a href="http://www.flickr.com/slideShow/index.gne?nsid='.urlencode($this->nsid).'&amp;user_id='.urlencode($this->nsid).'&amp;maxThumbs='.$this->latest;
			$html .= '"  onclick="window.open(this.href, \'slideShowWin\', \'width=800,height=600,top=150,left=70,scrollbars=no, status=no, resizable=no\'); ';
			$html .= 'return false;">';
		}
		$html .= $thing.'</a>';
		return $html;
	}

	function thumbnails_img() {
		$img_url = $this->getPhotoUrl($this->photo, $this->thumbnail_size);
		$img_url = '<img src="' . $img_url . '" alt="' . $this->photo['title'] . '" />';
		return $img_url;
	}

	function thumbnails_img_title() {
		return $this->photo['title'];
	}

	function thumbnails_link($img_url, $title ="") {
		if($this->open == 'self') {
			if (isset($this->set)) {
				$parameters['set'] = $this->set;
			}
			$parameters['img'] = $this->photo['id'];
			$html = tag($img_url,'a',' href="'. $this->makeRequest($parameters) .'"')."\n";
		}
		if($this->open == 'flickr') {
			$html = '<a href="http://www.flickr.com/photos/' . urlencode($this->nsid) . '/' . $this->photo['id'] . '/">' . "\n";
			$html .= $img_url;
			$html .= '</a>' . "\n";
		}
		if($this->open == 'window') {
			$html = '<a href="';
			$html .= $this->getPhotoUrl($this->photo, $this->img_size);
			$html .= '" onclick="window.open(this.href, \'popupwindow\', \'resizable\'); return false;">';
			$html .= $img_url;
			$html .= '</a>';
		}
		if($this->open == 'lightbox') {
			$html = '<li><a class="box col2 fancybox" href="';
			$html .= $this->getPhotoUrl($this->photo, $this->img_size);
			$set = $this->thumbnails_title();
			$set = trim($set);
			$html .= '" rel="lightbox['.$set.']"';
			$html .= ' title="'.$title.'">';
			$html .= $img_url;
			$html .= '</a></li>';
		}
		return $html;
	}

	function thumbnails_list($params) {
		if(!$this->xml->isValid()) {
			return false;
		}
		(isset($params['listmode']))?
		$this->listmode = $params['listmode']:
		$this->listmode = 'img';
		(isset($params['wraptag']) == true)? $wraptag = $params['wraptag'] : $wraptag = '';
		(isset($params['break']) == true)? $break = $params['break'] : $break = '';
		if ($this->thumbs_per_page == 0) {
			$this->start = 1;
			$this->end = sizeof($this->photos);
		}
		else {
			$this->start = (float) ($this->thumbs_per_page * ($this->page - 1)) + 1;
			$this->end = min($this->thumbs_per_page * $this->page, sizeof($this->photos));
		}
		$html = '';
		for ($this->i = $this->start - 1; $this->i <= $this->end - 1; $this->i++) {
			$this->photo = $this->photos[$this->i];
			(@$_GET['img'] == (float) $this->photo['id'])? $param = ' class="current"' : '';
			($this->listmode == 'img')? $what = $this->thumbnails_img() : $what = $this->thumbnails_img_title();
			($this->linkthumbs == 1)? $what = $this->thumbnails_link($what, $this->photo["title"]) : '';
			if($break != '') {
				($break != 'br')?
				$html .= tag($what, $break, $param) . "\n":
				$html .= $what .'<br />' . "\n";
			}
			else {
				$html .= $what . "\n";
			}
			unset($param);
		}
		return (($wraptag != '') == true)? tag($html, $wraptag) : $html;
	}

	function thumbnails_number_of_photos () {
		return $this->number_of_photos;
	}

	function thumbnails_per_page () {
		return $this->thumbs_per_page;
	}

	function thumbnails_current_page () {
		return $this->page;
	}

	function thumbnails_total_pages () {
		return $this->lastpage;
	}

	function thumbnails_pages_first ($thing) {
		if (isset($this->set)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('set' => $this->id, 'page' => 1)) .'"');
		if (isset($this->tags)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('tags' => $this->tags, 'page' => 1)) .'"');
		if (isset($this->latest)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('page' => 1)) .'"');
		return $result;
	}

	function thumbnails_pages_last ($thing) {
		if (isset($this->set)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('set' => $this->id, 'page' => $this->lastpage)) .'"');
		if (isset($this->tags)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('tags' => $this->tags, 'page' => $this->lastpage)) .'"');
		if (isset($this->latest)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('page' => $this->lastpage)) .'"');
		return $result;
	}

	function thumbnails_pages_previous ($thing) {
		if (isset($this->set)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('set' => $this->id, 'page' => $this->previous_page)) .'"');
		if (isset($this->tags)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('tags' => $this->tags, 'page' => $this->previous_page)) .'"');
		if (isset($this->latest)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('page' => $this->previous_page)) .'"');
		return $result;
	}

	function thumbnails_pages_next ($thing) {
		if (isset($this->set)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('set' => $this->id, 'page' => $this->next_page)) .'"');
		if (isset($this->tags)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('tags' => $this->tags, 'page' => $this->next_page)) .'"');
		if (isset($this->latest)) $result = tag(parse($thing), 'a', ' href="'. $this->makeRequest(array('page' => $this->next_page)) .'"');
		return $result;
	}

	function thumbnails_pages_startthumb () {
		return $this->start;
	}

	function thumbnails_pages_endthumb () {
		return $this->end;
	}

	function thumbnails_pages_list ($params) {
		$pages_array = range(1, $this->lastpage);
		for ($i = 0; $i <= $this->lastpage - 1; $i++) {
			if (isset($this->set)) $pages_array[$i] = tag($pages_array[$i], 'a', ' href="'. $this->makeRequest(array('set' => $this->id, 'page' => $pages_array[$i])) .'"');
			if (isset($this->tags)) $pages_array[$i] = tag($pages_array[$i], 'a', ' href="'. $this->makeRequest(array('tags' => $this->tags, 'page' => $pages_array[$i])) .'"');
			if (isset($this->latest)) $pages_array[$i] = tag($pages_array[$i], 'a', ' href="'. $this->makeRequest(array('page' => $pages_array[$i])) .'"');
		}
		return doWrap($pages_array, @$params['wraptag'], @$params['break'], @$params['class'], @$params['breakclass'], @$params['atts']);
	}

	function thumbnails_if_multiple_pages ($thing) {
		($this->thumbs_per_page != 0)? $result = parse($thing) : '';
		return $result;
	}

	function __toString() {
		if(!$this->xml->isValid()) {
			return $GLOBALS['text']['error_message'];
		}
		$result = parse($this->form);
		return $result;
	}
}


class Picture extends Vdh_Flickr {
	var $xml, $id, $secret, $server, $date_uploaded, $title, $description, $notes, $set, $link, $img, $context;
	var $img_size, $show_img_nav, $show_img_title, $method, $set_title, $comments, $context_mode;
	var $date_posted, $date_taken;
	var $previous = array(), $next = array(), $tags = array(), $raw_tags = array(), $tag_ids = array();

	public function Picture($params) {
		$this->Vdh_Flickr($params);
		(isset($params['img_size']))?
		$this->img_size = $params['img_size']:
		$this->img_size = 'n';
		(isset($params['original_size']))?
		$this->original_size = $params['original_size']:
		$this->original_size = 'o';
		(isset($params['set']))?
		$this->set = $params['set']:
		$this->set = $_GET['set'];
		(isset($params['img']))?
		$this->img = $params['img']:
		$this->img = $_GET['img'];
		$method = 'flickr.photos.getInfo' . $this->userdata . '&photo_id=' . $this->img;
		$this->xml = new Flickr($method);
		if($this->xml->isValid()) {
			$this->id = array_shift($this->xml->xpath('/rsp/photo/@id'));
			$this->secret = array_shift($this->xml->xpath('/rsp/photo/@secret'));
			$this->original_secret = array_shift($this->xml->xpath('/rsp/photo/@originalsecret'));
			$this->original_format = array_shift($this->xml->xpath('/rsp/photo/@originalformat'));
			$this->farm = array_shift($this->xml->xpath('/rsp/photo/@farm'));
			$this->server = array_shift($this->xml->xpath('/rsp/photo/@server'));
			$this->date_posted = array_shift($this->xml->xpath('/rsp/photo/dates/@posted'));
			$this->date_taken = array_shift($this->xml->xpath('/rsp/photo/dates/@taken'));
			$this->title = array_shift($this->xml->xpath('/rsp/photo/title/text()'));
			$this->description = array_shift($this->xml->xpath('/rsp/photo/description/text()'));
			$this->comments = array_shift($this->xml->xpath('/rsp/photo/comments/text()'));
			$this->raw_tags = $this->xml->xpath('/rsp/photo/tags/tag/@raw');
			$this->tags = $this->xml->xpath('/rsp/photo/tags/tag/text()');
			$this->link = @$params['link'];
		}
		if (isset($params['img_form'])) {
			$this->form = fetch('Form','txp_form',"name",$params['img_form']);
		}
		else {
			$this->form = '
			<div class="individual"><div class="image">
			<h2 class="title"><txp:vdh_flickr_img_title /></h2>
			<txp:vdh_flickr_img_link><txp:vdh_flickr_img_naked /></txp:vdh_flickr_img_link>
			<div class="flickrsetnav">
			<txp:vdh_flickr_img_previous label="previous&nbsp;:&nbsp;">&larr;</txp:vdh_flickr_img_previous>
			<h2 class="setname"><txp:vdh_flickr_img_set_link><txp:vdh_flickr_img_set_title /></txp:vdh_flickr_img_set_link></h2>
			<txp:vdh_flickr_img_next label="next&nbsp;:&nbsp;">&rarr;</txp:vdh_flickr_img_next>
			</div>
			<div class="flickr_tag_list">
			<txp:vdh_flickr_img_tags separator=" | " />
			</div>
			<div class="flickr_comments">
			<txp:vdh_flickr_img_number_of_comments /> Comments.
			<txp:vdh_flickr_img_comments_invite>Show and post comments!</txp:vdh_flickr_img_comments_invite><br />
			Posted <txp:vdh_flickr_img_date_posted />.<br />
			Taken <txp:vdh_flickr_img_date_taken />.<br />
			</div>
			<txp:vdh_flickr_img_if_description>
			<p class="img_description">
			<txp:vdh_flickr_img_description />
			</p>
			</txp:vdh_flickr_img_if_description>
			</div></div><div style="clear:both;"></div>';
		}
	}

	function getContext() {
		if (isset($this->set)) {
			$method = 'flickr.photosets.getContext' . $this->userdata . '&photo_id=' . $this->img . '&photoset_id=' . $this->set;
		}
		else {
			$method = 'flickr.photos.getContext' . $this->userdata . '&photo_id=' . $this->img;
		}
		$this->xml = new Flickr($method);
		$this->previous['id'] = array_shift($this->xml->xpath('/rsp/prevphoto/@id'));
		$this->previous['title'] = array_shift($this->xml->xpath('/rsp/prevphoto/@title'));
		$this->previous['thumb'] = array_shift($this->xml->xpath('/rsp/prevphoto/@thumb'));
		$this->next['id'] = array_shift($this->xml->xpath('/rsp/nextphoto/@id'));
		$this->next['title'] = array_shift($this->xml->xpath('/rsp/nextphoto/@title'));
		$this->next['thumb'] = array_shift($this->xml->xpath('/rsp/nextphoto/@thumb'));
		$this->context = true;
	}

	function img_naked() {
		$photo = array("farm" => $this->farm, "server" => $this->server, "id" => $this->id, "secret" => $this->secret, "original_secret" => $this->original_secret, "original_format" => $this->original_format);
		$img_url = $this->getPhotoUrl($photo, $this->img_size);
		$img_url = '<img src="' . $img_url . '" alt="' . $this->title . '" />';
		return $img_url;
	}

	function img_link($img_url) {
		if (isset($this->link)){
			switch($this->link) {
				case "flickr":
				$link_target = 'http://www.flickr.com/photos/' . urlencode($this->nsid) . '/' . $this->id . '/';
				break;
				case "original_size" || "lightbox":
				$photo = array("farm" => $this->farm, "server" => $this->server, "id" => $this->id, "secret" => $this->secret, "original_secret" => $this->original_secret, "original_format" => $this->original_format);
				$link_target = $this->getPhotoUrl($photo, $this->original_size);
				break;
				case "img_information":
				$link_target = 'http://www.flickr.com/photo_exif.gne?id=' . $this->id;
				break;
			}
			$link_attributes = ' href="' . $link_target . '"';
			if ($this->link == "lightbox") {
				$link_attributes .= ' rel="lightbox"';
			}
			$img_url = tag($img_url, 'a', $link_attributes);
			$img_url .= "\n";
		}
		return $img_url;
	}

	function img_previous($label, $thing) {
		if (!isset($this->context)) $this->getContext();
		$this->previous['id'] = (float) ($this->previous['id']);
		if ($this->previous['id'] != 0) {
			$title =  $this->previous['title'];
			$end = '" title="'. $label . $title;
			if (isset($this->set)) $html = tag($thing,'a',' href="'. $this->makeRequest(array('set' => $this->set, 'img' => $this->previous['id'])) .$end.'"')."\n";
			else $html = tag($thing,'a',' href="'. $this->makeRequest(array('img' => $this->previous['id'])) .$end.'"')."\n";
		}
		return $html;
	}

	function img_previous_thumbnail() {
		if (!isset($this->context)) $this->getContext();
		$this->previous['id'] = (float) ($this->previous['id']);
		if ($this->previous['id'] != 0) {
			$result = '<img src="' . $this->previous['thumb'] . '" alt="' . $title . '" />';
		}
		return $result;
	}

	function img_next($label, $thing) {
		if (!isset($this->context)) $this->getContext();
		$this->next['id'] = (float) ($this->next['id']);
		if ($this->next['id'] != 0) {
			$title =  $this->next['title'];
			$end = '" title="'. $label . $title;
			if (isset($this->set)) $html = tag($thing,'a',' href="'. $this->makeRequest(array('set' => $this->set, 'img' => $this->next['id'])) .$end.'"')."\n";
			else $html = tag($thing,'a',' href="'. $this->makeRequest(array('img' => $this->next['id'])) .$end.'"')."\n";
		}
		return $html;
	}

	function img_next_thumbnail() {
		if (!isset($this->context)) $this->getContext();
		$this->next['id'] = (float) ($this->next['id']);
		if ($this->next['id'] != 0) {
			$result = '<img src="' . $this->next['thumb'] . '" alt="' . $title . '" />';
		}
		return $result;
	}

	function img_set_title() {
		if (isset($this->set)) {
			$method = 'flickr.photosets.getInfo' . $this->userdata . '&photoset_id=' . $this->set;
			$xml = new Flickr($method);
			$this->set_title = array_shift($xml->xpath('/rsp/photoset/title/text()'));
			return $this->set_title;
		}
		return '';
	}

	function img_set_link($thing) {
		if (isset($this->set)) {
			return tag($thing, 'a', ' href="'. $this->makeRequest(array('set' => $this->set)) .'"');
		}
		return '';
	}

	function img_nav() {
		$html .= $this->img_previous();
		$html .= '<h2 class="setname">' . $this->img_set_title() . '</h2>' . "\n";
		$html .= $this->img_next();
		return $html;
	}

	function img_title() {
		return $this->title;
	}

	function img_description() {
		$description_string = (string) $this->description;
		if ($description_string != 'Array') return $description_string;
	}

	function img_if_description ($thing) {
		if ($this->description != '&nbsp;' and !empty($this->description)) $result = parse($thing);
		else $result = '';
		return $result;
	}

	function img_tags($separator) {
		$html = tag($this->raw_tags[0],'a',' href="' . $this->makeRequest(array('tags' => $this->tags[0])) . '"')."\n";
		for($i = 1; $i < sizeof($this->tags); $i++) {
			$html .= $separator;
			$html .= tag($this->raw_tags[$i],'a',' href="' . $this->makeRequest(array('tags' => $this->tags[$i])) . '"')."\n";
		}
		return $html;
	}

	function img_number_of_comments() {
		return $this->comments;
	}

	function img_comments_invite($invitetext) {
		return tag($invitetext,'a',' href="' . 'http://www.flickr.com/photos/' . urlencode($this->nsid) . '/' . $this->id . '/"');
	}

	function img_date_posted($params) {
		if (isset($params['format'])) return safe_strftime($params['format'], $this->date_posted);
		global $archive_dateformat;
		return safe_strftime($archive_dateformat, $this->date_posted);
	}

	function img_date_taken($params) {
		if (isset($params['format'])) return safe_strftime($params['format'], strtotime($this->date_taken));
		global $archive_dateformat;
		return safe_strftime($archive_dateformat, strtotime($this->date_taken));
	}

	function __toString() {
		if(!$this->xml->isValid()) {
			return $GLOBALS['text']['error_message'];
		}
		$result = parse($this->form);
		return $result;
	}
}


class Taglist extends Vdh_Flickr {
	var $xml, $taglist, $count, $source_tag;

	public function Taglist($params) {
		$this->Vdh_Flickr($params);
	}

	function list_all($params) {
		$method = 'flickr.tags.getListUser' . $this->userdata;
		return $this->generateList($method, $params);
	}

	function list_popular($params) {
		isset($params['count'])? $this->count = $params['count'] : $this->count = 10;
		$method = 'flickr.tags.getListUserPopular' . $this->userdata . '&count=' . $this->count;
		return $this->generateList($method, $params);
	}

	function list_related($params) {
		isset($params['source_tag'])? $this->source_tag = $params['source_tag'] : $this->source_tag = '';
		$method = 'flickr.tags.getRelated' . $this->userdata . '&tag=' . $this->source_tag;
		return $this->generateList($method, $params);
	}

	function generateList ($method, $params) {
		$this->xml = new Flickr($method);
		if($this->xml->isValid()) {
			$this->taglist = $this->xml->xpath('//tags/tag/text()');
		}
		for ($i = 0; $i <= sizeof($this->taglist) - 1; $i++) {
			$this->taglist[$i] = tag($this->taglist[$i],'a',' href="' . $this->makeRequest(array('tags' => $this->taglist[$i])) . '"');
		}
		return doWrap($this->taglist, $params['wraptag'], $params['break'], $params['class'], $params['breakclass'], $params['atts']);
	}

	function __toString() {
		return $this->list_all;
	}
}


class Flickr {
	var $xmlurl = 'http://www.flickr.com/services/rest/?method=';
	var $xml;

	public function Flickr($method) {
		//$time_start = microtime(true);
		$this->xmlurl .= $method;
		if(isset($GLOBALS['use_php4'])) {
			if (function_exists('curl_init')) {
				$ch = @curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->xmlurl);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$resp = curl_exec($ch);
				curl_close($ch);
				if($dom = @domxml_open_mem($resp)) {
					$this->xml = xpath_new_context($dom);
				}
			}
			else if ($resp = @file_get_contents($this->xmlurl)) {
				if($dom = @domxml_open_mem($resp)) {
					$this->xml = xpath_new_context($dom);
				}
			}
			else if (function_exists('domxml_open_file')){
				$dom = @domxml_open_file($this->xmlurl);
				$this->xml = xpath_new_context($dom);
			}
		}
		else {
			if(!$this->xml = @simplexml_load_file($this->xmlurl)) {
				unset($this->xml);
			}
		}
		//echo n,comment(('runtime for ' . $method. ': ' . (microtime(true) - $time_start) . "<br />\n"));
	}

	function isValid() {
		if(isset($this->xml)) {
			$res = array_shift($this->xpath('/rsp/@stat'));
			if ($res == 'fail') {
				return false;
			}
			return true;
		}
		else {
			return false;
		}
	}

	function xpath($path) {
		if(!isset($this->xml)) {
			return NULL;
		}
		if(isset($GLOBALS['use_php4'])) {
			$result = xpath_eval_expression($this->xml, $path);
			$result = $result->nodeset;
			// Convert to String:
			for($iterator = 0; $iterator < count($result); $iterator++) {
				$result[$iterator] = $result[$iterator]->get_content();
			}
			return $result;
		}
		else {
			$result = $this->xml->xpath($path);
			// Convert to String:
			for($iterator = 0; $iterator < count($result); $iterator++) {
				$result[$iterator] = (string) $result[$iterator];
			}
			return $result;
		}
	}
}

function vdh_flickr($params) {
	if(isset($_GET['img'])) {
		return vdh_flickr_img($params);
	}
	if(isset($_GET['tags'])) {
		return vdh_flickr_thumbnails($params);
	}
	if(isset($_GET['set'])) {
		return vdh_flickr_thumbnails($params);
	}
	global $gal;
	//((isset ($gal))==false)? $gal = new Gallery($params) : '';
	if (!empty($params)) $gal = new Gallery($params);
	return $gal->__toString();
}

function vdh_flickr_thumbnails($params) {
	if(isset($_GET['img'])) {
		return vdh_flickr_img($params);
	}
	global $thumbs;
	//((isset ($thumbs))==false)? $thumbs = new Thumbnails($params) : '';
	if (!empty($params)) $thumbs = new Thumbnails($params);
	return $thumbs->__toString();
}

function vdh_flickr_img($params) {
	global $singleimg;
	//((isset ($singleimg))==false)? $singleimg = new Picture($params) : '';
	if (!empty($params)) $singleimg = new Picture($params);
	return $singleimg->__toString();
}

//neue txp tags

global $vdh_flickr;

function vdh_flickr_set_img ($params) {
	global $gal;
	return $gal->set_img($params);
}

function vdh_flickr_set_title ($params) {
	global $gal;
	return $gal->set_title($params);
}

function vdh_flickr_set_description () {
	global $gal;
	return $gal->set_description();
}

function vdh_flickr_set_number_of_photos () {
	global $gal;
	return $gal->set_number_of_photos();
}

function vdh_flickr_set_link ($params, $thing) {
	$result = parse($thing);
	global $gal;
	return $gal->set_link($params, $result);
}

function vdh_flickr_set_list ($params) {
	global $gal;
	((isset ($gal))==false)? $gal = new Gallery($params) : '';
	return $gal->set_list($params);
}

function vdh_flickr_tag_list_all ($params) {
	$taglalala = new Taglist($params);
	return $taglalala->list_all($params);
}

function vdh_flickr_tag_list_popular ($params) {
	$taglalala = new Taglist($params);
	return $taglalala->list_popular($params);
}

function vdh_flickr_tag_list_related ($params) {
	$taglalala = new Taglist($params);
	return $taglalala->list_related($params);
}

function vdh_flickr_thumbnails_title () {
	global $thumbs;
	return $thumbs->thumbnails_title();
}

function vdh_flickr_thumbnails_description () {
	global $thumbs;
	return $thumbs->thumbnails_description();
}

function vdh_flickr_thumbnails_if_description ($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_if_description($thing);
}

function vdh_flickr_thumbnails_slideshow ($params, $thing) {
	$result = parse($thing);
	global $thumbs;
	return $thumbs->thumbnails_slideshow($result);
}

function vdh_flickr_thumbnails_img () {
	global $thumbs;
	return $thumbs->thumbnails_img();
}

function vdh_flickr_thumbnails_img_title () {
	global $thumbs;
	return $thumbs->thumbnails_img_title();
}

function vdh_flickr_thumbnails_link ($params, $thing) {
	$result = parse($thing);
	global $thumbs;
	return $thumbs->thumbnails_link($result);
}

function vdh_flickr_thumbnails_list ($params) {
	global $thumbs;
	if (isset($_GET['set']) or isset($_GET['tags']) or isset($params['set']) or isset($params['tags']) or isset($params['latest'])) {
		if ((isset ($thumbs))==false) $thumbs = new Thumbnails($params);
	}
	if (isset($_GET['set']) or isset($_GET['tags']) or isset($thumbs->set) or isset($thumbs->tags) or isset($thumbs->latest)) {
		return $thumbs->thumbnails_list($params);
	}
	return false;
}

function vdh_flickr_thumbnails_number_of_photos() {
	global $thumbs;
	return $thumbs->thumbnails_number_of_photos();
}

function vdh_flickr_thumbnails_per_page() {
	global $thumbs;
	return $thumbs->thumbnails_per_page();
}

function vdh_flickr_thumbnails_current_page() {
	global $thumbs;
	return $thumbs->thumbnails_current_page();
}

function vdh_flickr_thumbnails_total_pages() {
	global $thumbs;
	return $thumbs->thumbnails_total_pages();
}

function vdh_flickr_thumbnails_pages_first($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_pages_first($thing);
}

function vdh_flickr_thumbnails_pages_last($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_pages_last($thing);
}

function vdh_flickr_thumbnails_pages_previous($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_pages_previous($thing);
}

function vdh_flickr_thumbnails_pages_next($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_pages_next($thing);
}

function vdh_flickr_thumbnails_pages_list($params) {
	global $thumbs;
	return $thumbs->thumbnails_pages_list($params);
}

function vdh_flickr_thumbnails_pages_startthumb() {
	global $thumbs;
	return $thumbs->thumbnails_pages_startthumb();
}

function vdh_flickr_thumbnails_pages_endthumb() {
	global $thumbs;
	return $thumbs->thumbnails_pages_endthumb();
}

function vdh_flickr_thumbnails_if_multiple_pages($params, $thing) {
	global $thumbs;
	return $thumbs->thumbnails_if_multiple_pages($thing);
}

function vdh_flickr_img_title () {
	global $singleimg;
	return $singleimg->img_title();
}

function vdh_flickr_img_description () {
	global $singleimg;
	return $singleimg->img_description();
}

function vdh_flickr_img_if_description ($params, $thing) {
	global $singleimg;
	return $singleimg->img_if_description($thing);
}

function vdh_flickr_img_naked () {
	global $singleimg;
	return $singleimg->img_naked();
}

function vdh_flickr_img_link ($params, $thing) {
	$result = parse($thing);
	global $singleimg;
	return $singleimg->img_link($result);
}

function vdh_flickr_img_previous ($params, $thing) {
	$result = parse($thing);
	global $singleimg;
	return $singleimg->img_previous($params['label'], $result);
}

function vdh_flickr_img_previous_thumbnail () {
	global $singleimg;
	return $singleimg->img_previous_thumbnail();
}

function vdh_flickr_img_next ($params, $thing) {
	$result = parse($thing);
	global $singleimg;
	return $singleimg->img_next($params['label'], $result);
}

function vdh_flickr_img_next_thumbnail () {
	global $singleimg;
	return $singleimg->img_next_thumbnail();
}

function vdh_flickr_img_set_title () {
	global $singleimg;
	return $singleimg->img_set_title();
}

function vdh_flickr_img_set_link ($params, $thing) {
	$result = parse($thing);
	global $singleimg;
	return $singleimg->img_set_link($result);
}

function vdh_flickr_img_tags ($params) {
	global $singleimg;
	return $singleimg->img_tags($params['separator']);
}

function vdh_flickr_img_number_of_comments () {
	global $singleimg;
	return $singleimg->img_number_of_comments();
}

function vdh_flickr_img_comments_invite ($params, $thing) {
	$result = parse($thing);
	global $singleimg;
	return $singleimg->img_comments_invite($result);
}

function vdh_flickr_img_date_posted ($params) {
	global $singleimg;
	return $singleimg->img_date_posted($params);
}

function vdh_flickr_img_date_taken ($params) {
	global $singleimg;
	return $singleimg->img_date_taken($params);
}

function vdh_flickr_env($params) {
	global $vdh_flickr;
	if(isset($_GET['img'])) {
		global $singleimg;
		$vdh_flickr['is_img'] = 1;
		$singleimg = new Picture($params);
		return '';
	}
	if(isset($_GET['tags'])) {
		global $thumbs;
		$vdh_flickr['is_thumbnails'] = 1;
		$vdh_flickr['is_tags'] = 1;
		$thumbs = new Thumbnails($params);
		return '';
	}
	if(isset($_GET['set'])) {
		global $thumbs;
		$vdh_flickr['is_thumbnails'] = 1;
		$vdh_flickr['is_set'] = 1;
		$thumbs = new Thumbnails($params);
		return '';
	}
	global $gal;
	((isset ($gal))==false)? $gal = new Gallery($params) : '';
	$vdh_flickr['is_preview'] = 1;
	return '';
}

function vdh_flickr_if_preview($params, $thing) {
	global $vdh_flickr;
	return (@$vdh_flickr['is_preview'] == true) ? parse($thing) : '';
}

function vdh_flickr_if_thumbnails($params, $thing) {
	global $vdh_flickr;
	return (@$vdh_flickr['is_thumbnails'] == true) ? parse($thing) : '';
}

function vdh_flickr_if_set($params, $thing) {
	global $vdh_flickr;
	return (@$vdh_flickr['is_set'] == true) ? parse($thing) : '';
}

function vdh_flickr_if_tags($params, $thing) {
	global $vdh_flickr;
	return (@$vdh_flickr['is_tags'] == true) ? parse($thing) : '';
}

function vdh_flickr_if_img($params, $thing) {
	global $vdh_flickr;
	return (@$vdh_flickr['is_img'] == true) ? parse($thing) : '';
}

function vdh_flickr_show_nsid($params) {
	$nsidcheck = new Vdh_flickr($params);
	return $nsidcheck->__toString();
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<p>
h1. vdh_flickr documentation</p>

	<h2 id="contents">Contents</h2>

	<ul>
		<li><a href="#features">Features</a></li>
		<li><a href="#requirements">Requirements</a></li>
		<li><a href="#quickstart">Quickstart</a></li>
		<li><a href="#tags">Main tags and tag attributes</a></li>
		<li><a href="#additional_tags">Additional Tags</a></li>
		<li><a href="#picture_sizes">Picture sizes</a></li>
		<li><a href="#example_css">Example CSS</a></li>
		<li><a href="#clean_urls">Clean URLs</a></li>
	</ul>
	<ul>
		<li><a href="#forms">Customising with forms</a></li>
	</ul>

	<h2 id="features">Features</h2>

	<p>vdh_flickr is a full-featured gallery plugin for textpattern, using a classical three-step system: First, you are shown a set (=album) preview page, then a thumbnail page, and an image page afterwards. The <a href="http://www.rvdh.net/vdh_flickr/Demo">Demo</a> shows the default configuration.<br />
But you can leave out any step, so you can for example leave out the set preview or both the set preview and thumbnails to show an individual image.</p>

	<p>Furthermore, these are other features you may be interested in:
	<ul>
		<li>sets:
	<ul>
		<li>show all sets except of a list of sets</li>
		<li>show no sets except of a list of sets</li>
	</ul></li>
	<ul>
		<li>show set title, description, number of photos</li>
		<li>tags:
		<li>show a list of thumbnails that are associated with a certain tag, or a list of tags (<span class="caps">AND</span> / OR combination possible)</li>
		<li>show a list of all your tags</li>
	</ul></li>
	<ul>
		<li>show a list of your X most popular tags (where X is a number)</li>
		<li>thumbnails:
		<li>show a list of thumbnails that are associated with a set</li>
		<li>show a list of thumbnails of your latest X pics</li>
		<li>show a list of thumbnails that are associated with a certain tag, or a list of tags (<span class="caps">AND</span> / OR combination possible)</li>
		<li>show a random selection of X pics of a set / a list of tags / your Y latest pics (handy for badges)</li>
		<li>a click on a thumbnail can open the individual image in the same window / a new window / in flickr</li>
	</ul></li>
	<ul>
		<li>flash slideshow of current thumbnails</li>
		<li>individual image:
		<li>show image title and description</li>
		<li>show date posted and date taken</li>
		<li>link back to the set or tag list</li>
		<li>link to the previous and next image of a set / the photostream</li>
		<li>show a thumb of the previous and next image</li>
		<li>show a list of tags associated with the current image</li>
	</ul></li>
	<ul>
		<li>when an individual image is clicked, link it to a specific url / the original sized image / the image on flickr.com / the image information page on flickr.com</li>
		<li>pagination:
		<li>pagination for all kind of thumbnail lists</li>
		<li>show current page</li>
		<li>link to previous/next/first/last page</li>
		<li>show list of pages</li>
		<li>show total number of images</li>
	</ul></li>
	<ul>
		<li>show range of images that is displayed</li>
		<li>general:
		<li>specify the sizes for set preview, thumbnail and individual image</li>
		<li>show private pics, too</li>
		<li>clean urls (sgb_url_handler and .htaccess tweak needed)</li>
		<li>valid <span class="caps">XHTML</span> 1.0 Strict in default configuration</li>
	</ul></li>
	</ul>
	<ul>
		<li>tweakable <span class="caps">XHTML</span> via forms</li>
	</ul></li></p>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="requirements">Requirements</h2>

	<p>In order to run vdh_flickr, you must fit these requirements on your server:</p>

	<ul>
		<li>Textpattern version 4 or newer</li>
		<li><span class="caps">PHP</span> 5 with SimpleXML enabled.</li>
	</ul>
	<ul>
		<li><span class="caps">PHP</span> 4.3.X with <span class="caps">DOM-XML</span> enabled.</li>
	</ul>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="quickstart">Quickstart</h2>

	<ul>
		<li>Create a new section which will later hold your Gallery.</li>
		<li>Create a new article with only the following text in it: <br />
 <code>&#60;txp:vdh_flickr nsid=&#34;12345678@N00&#34; /&#62;</code> <br />
 Of course you have to replace the nsid value by your own data from flickr.com. You can use <a href="http://idgettr.com/">idgettr</a> in order to determine your nsid. <br />
 Also notice that you can put two spaces before the txp-tag to prevent textpattern from wrapping the gallery in p-tags. <br />
 <strong>Note:</strong> It is also possible to specify nsid in the vdh_flickr source code. This makes sense if you are using several galleries or vdh_flickr-tags on your site.</li>
		<li>Save the article in the section you created in the first step.</li>
		<li>Point your browser to http://yourdomain.com/gallerysection (or http://yourdomain.com/?s=gallerysection). <br />
 You should see a preview pic for all your sets. If you click on one of the pics, you should see a thumbnail overview of the selected set. If you click on one of the thumbnails, you should see the pic in 640×480.</li>
	</ul>
	<ul>
		<li>Download <a href="#example_css">this css</a> to make your photo page look better.</li>
	</ul>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="tags">Main tags and tag attributes</h2>

	<p>These tags can either go into an article or a page template.</p>

	<h3>Step 1: Attributes concerning the set preview (vdh_flickr).</h3>

	<p>Shows the preview image, title and description of specific sets, or all sets.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<ul>
		<li><strong>set_preview_size=&#8220;m&#8221;</strong> Possible image sizes are described below.</li>
		<li><strong>mode=&#8220;all|none&#8221;</strong> vdh_flickr basically supports two modes for selecting flickr sets: all and none. Default is all.</li>
		<li><strong>except=&#8220;162XXX,163XXX&#8221;</strong> Furthermore you can specify a list of exceptions exceptions. <br />
 Examples:
		<li><strong>email=&#8220;you@there.com&#8221; password=&#8220;yourpassword&#8221;</strong> If you enter your eMail and password (the one you use to login to flickr), vdh_flickr will show your private pics, too.
	<ul>
		<li><code>&#60;txp:vdh_flickr nsid=&#34;12345678@N00&#34; mode=&#34;none&#34; except=&#34;162XXX&#34; /&#62;</code> <br />
 This will show only the set number 162XXX.</li>
	</ul></li>
	<ul>
		<li><code>&#60;txp:vdh_flickr nsid=&#34;12345678@N00&#34; mode=&#34;all&#34; except=&#34;162XXX,163XXX&#34; /&#62;</code> <br />
 This will show all sets except numbers 162XXX and 163XXX. Multiple exceptions must be comma-seperated with no spaces in between.</li>
	</ul></li>
	</ul>
	<ul>
		<li><strong>use_articleurl=&#8220;0|1&#8221;</strong> Include the article id and/or title in the url (depending on your url scheme), when linking to the thumbnails and individual images. Default is 0, when vdh_flickr was loaded from an article list, and 1 if vdh_flickr was loaded from an individual article.</li>
	</ul>

	<h3>Step 2: Attributes concerning the thumbnail view (vdh_flickr_thumbnails).</h3>

	<p>Shows all images in a specific flickr set, all the images associated with a list of tags or the X latest images.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>
	<ul>
		<li><strong>set=&#8220;1234567&#8221;</strong> The number of the set you want to show. To determine this number, log into flickr, click on the set and look at the url. The set number is the last part of the url, usually a six-digits number. <br />
 Example: <code>http://www.flickr.com/photos/12345678@N00/sets/135789/</code> <br />
 In this case the set number is 135789. <br />
 OR: <br />
 <strong>tags=&#8220;tag1,tag2&#8221;</strong> List of tags that should be displayed. Multiple tags must be comma-seperated with no spaces in between. By default, all images containing at least one of these tags is shown. <br />
 OR: <br />
 <strong>latest=&#8220;20&#8221;</strong> Show the latest 20 pics.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<p>	<ul>
		<li><strong>thumbnail_size=&#8220;t&#8221;</strong> Possible image sizes are described below. Don&#8217;t be confused by the name thumbnail_size. You can select any size.</li>
		<li><strong>random=&#8220;10&#8221;</strong> Show a random selection of 10 of the specified set / list of tags / latest pics.</li>
		<li><strong>tag_and=&#8220;0|1&#8221;</strong> Change the mode how multiple tags are handled. If switched to &#8220;1&#8221;, vdh_flickr will only show the pictures that contain <span class="caps">ALL</span> of the tags specified. Default is off (0).
		<li><strong>open=&#8220;self|window|flickr&#8221;</strong>
	<ul>
		<li>self (default): opens images within the website</li>
		<li>window: opens image in a new window</li>
	</ul></li>
	</ul>
	<ul>
		<li>flickr: opens image within the flickr.com website</li>
	</ul></li></p>

	<h3>Step 3: Attributes concerning imdividual images (vdh_flickr_img).</h3>

	<p>Shows a single image from flickr.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>
	<ul>
		<li><strong>img=&#8220;1234567&#8221;</strong> The number of the image you want to show. To determine this number, log into flickr, click on the image and look at the url. The image number is the last part of the url, usually a seven-digits number. Example: <br />
 http://www.flickr.com/photos/12345678%40N00/7654321/ <br />
 In this case the image number is 7654321.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<ul>
		<li><strong>img_size=&#8220;n&#8221;</strong> Possible image sizes are described below.</li>
		<li><strong>original_size=&#8220;o&#8221;</strong> Size of the linked image if link=&#8220;original_size&#8221; is used.</li>
		<li><strong>link=&#8220;http://www.google.com|original_size|flickr|img_information&#8221;</strong> Link the image to a specific url, the original sized image, the image on flickr.com or on the image information page on flickr.com.</li>
	</ul>
	<ul>
		<li><strong>contextmode=&#8220;text|img&#8221;</strong> Show next and previous image as text (editable via text_next_img_link_text) or as small preview image. Default is text.</li>
	</ul>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="additional_tags">Additional Tags</h2>

	<p>These Tags are independant of the 3step functionality and can be used for any of the three steps. Good for navigation bars that should be always visible.</p>

	<h3>vdh_flickr_set_list.</h3>

	<p>Shows a list of all your flickr sets.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<ul>
		<li><strong>wraptag=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
		<li><strong>break=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
	</ul>
	<ul>
		<li><strong>email=&#8220;you@there.com&#8221; password=&#8220;yourpassword&#8221;</strong> If you enter your eMail and password (the ones you use to login to flickr), vdh_flickr will show your private sets, too.</li>
	</ul>

	<h3>vdh_flickr_tag_list_all</h3>

	<p>Shows a list of all your flickr tags.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<ul>
		<li><strong>wraptag=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
		<li><strong>break=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
	</ul>
	<ul>
		<li><strong>email=&#8220;you@there.com&#8221; password=&#8220;yourpassword&#8221;</strong> If you enter your eMail and password (the ones you use to login to flickr), vdh_flickr will show your private tags, too.</li>
	</ul>

	<h3>vdh_flickr_tag_list_popular</h3>

	<p>Shows a list of your X most popular flickr tags. X is a number.</p>

	<h4>Mandatory Attributes:</h4>

	<ul>
		<li><strong>nsid</strong>, if not specified in the source code.</li>
	</ul>

	<h4>Possible Attributes:</h4>

	<ul>
		<li><strong>count=&#8220;10&#8221;</strong>, the number of tags to display. Defaults to 10.</li>
		<li><strong>wraptag=&#8221;...&#8221;</strong> See <span class="caps">TXP</span> documentation.</li>
		<li><strong>break=&#8221;...&#8221;</strong> See <span class="caps">TXP</span> documentation.</li>
	</ul>
	<ul>
		<li><strong>email=&#8220;you@there.com&#8221; password=&#8220;yourpassword&#8221;</strong> If you enter your eMail and password (the ones you use to login to flickr), vdh_flickr will show your private tags, too.</li>
	</ul>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="picture_sizes">Picture sizes</h2>

	<p>The picture sizes are determined by flickr and cannot be changed to an individual value. Flickr offers the following sizes:</p>

	<ul>
		<li><strong>s</strong>   small square 75&#215;75</li>
		<li><strong>t</strong>   thumbnail, 100 on longest side</li>
		<li><strong>m</strong>   small, 240 on longest side</li>
		<li><strong>n</strong>   medium, 500 on longest side</li>
		<li><strong>b</strong>   large, 1024 on longest side (only exists for very large original images)</li>
	</ul>
	<ul>
		<li><strong>o</strong>   original image, either a jpg, gif or png, depending on source format</li>
	</ul>

	<p>You can select sizes as follows:</p>

	<ul>
		<li><code>&#60;txp:vdh_flickr set_preview_size=&#39;m&#39; thumbnail_size=&#39;s&#39; img_size=&#39;n&#39; /&#62;</code></li>
	</ul>

	<p>Default for set_preview_size is m, for thumbnail_size is s and for img_size is n.</p>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="clean_urls">Achieving clean urls</h2>

	<p>There are several steps needed to enable clean urls. But don&#8217;t be afraid, it won&#8217;t take longer than 15 minutes and is definitely worth the trouble.</p>

	<p>1. Install and activate <a href="http://forum.textpattern.com/viewtopic.php?id=6810&#38;p=1">sgb_url_handler and sgb_error_documents</a>. Then edit the source of sgb_error_documents and add this line to the other schemes (you most probably won&#8217;t need all of them, but if you paste them all, you&#8217;re done.):</p>

<pre><code>$schemes[&#39;flickr_title_set&#39;] = &#39;/%section%/%title%/set/%string%&#39;;
$schemes[&#39;flickr_title_set_img&#39;] = &#39;/%section%/%title%/set/%string%/img/%string%&#39;;
$schemes[&#39;flickr_title_tags&#39;] = &#39;/%section%/%title%/tags/%string%&#39;;
$schemes[&#39;flickr_title_tags_page&#39;] = &#39;/%section%/%title%/tags/%string%/page/%string%&#39;;
$schemes[&#39;flickr_title_set_page&#39;] = &#39;/%section%/%title%/set/%string%/page/%string%&#39;;
$schemes[&#39;flickr_set&#39;] = &#39;/%section%/set/%string%&#39;;
$schemes[&#39;flickr_set_img&#39;] = &#39;/%section%/set/%string%/img/%string%&#39;;
$schemes[&#39;flickr_tags&#39;] = &#39;/%section%/tags/%string%&#39;;
$schemes[&#39;flickr_tags_page&#39;] = &#39;/%section%/tags/%string%/page/%string%&#39;;
$schemes[&#39;flickr_set_page&#39;] = &#39;/%section%/set/%string%/page/%string%&#39;;
</code></pre>

	<p>2. Then you have to add these lines to your .htaccess to make clean urls work. Here is my complete .htaccess file so that you can see how the lines have to be added:</p>

<pre><code>RewriteBase /
&#60;IfModule mod_rewrite.c&#62;
RewriteEngine On
RewriteRule set/([0-9]+) index.php?set=$1
RewriteRule img/([0-9]+) index.php?img=$1
RewriteRule set/([0-9]+)/img/([0-9]+) index.php?set=$1&#38;img=$2
RewriteRule set/([0-9]+)/page/([0-9]+) index.php?set=$1&#38;page=$2
RewriteRule tags/(.+) index.php?tags=$1
RewriteRule tags/(.+)/page/([0-9]+) index.php?tags=$1&#38;page=$2
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^(.+) - [PT,L]
RewriteRule ^(.*) index.php
&#60;/IfModule&#62;
</code></pre>

	<p>3. The last step is to add this attribute to your vdh_flickr tag: <br />
 <strong>clean_urls=&#8220;1&#8221;</strong></p>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="example_css">Example <span class="caps">CSS</span></h2>

	<p>Add this <span class="caps">CSS</span> to your gallery section CSS-file to make your gallery look better.</p>

<pre><code>.setpreview {
	margin: 0 2em 1em 2em;
}
	.setpreview .thumbnail {
		float: left;
		margin-right: 1em;
	}
.flickrset a {
	border: solid #F67733 2px;
	width: 75px;
	height: 75px;
	background: #fff0d6;
	float: left;
	margin: 10px;
	padding: 5px;
}
.flickrset a:hover {
	background: #F67733;
}
.flickrset img {
	border: 0;
}
.pages_nav {
	clear: both;
}
.individual {
	padding: 0 2em;
}
	.individual .image {
		margin: 0.5em auto;
		text-align: center;
	}
	.individual .image img {
	}
	.individual .flickrsetnav {
		text-align: center;
	}
		.individual .flickrsetnav a,
		.individual .flickrsetnav a:link,
		.individual .flickrsetnav a:visited {
			text-decoration: none;
		}
	.individual .setname {
		display: inline;
	}
</code></pre>

	<p><a href="#contents">&uarr; top</a></p>

	<h2 id="forms">Forms</h2>

	<h3>What are forms good for?</h3>

	<p>If you are completely satisfied with the look and functionalitiy of your gallery, read no more. Otherwise, the vdh_flickr form mode gives you possibilities to tweak nearly every line of html that is generated by vdh_flickr.<br />
In this workshop, we will first create three forms representing the classical functionality of this plugin. This means we will have one form for the set preview page, one form for the page that shows the thumbnails of a set and a form that determines how individual images look like. These will be exactly the same as the example forms included in the plugin source code. But after creating these forms, you will be able to remove or change elements, so that the look of your gallery is independant of future changes in the default forms.</p>

	<h3>Creating forms</h3>

	<h4><strong>preview</strong>, a form for the set preview page</h4>

	<p>Log into Textpattern and go to presentation &rarr; forms. Click &#8220;create a new form&#8221;, enter the form name &#8220;preview&#8221; and form type &#8220;misc&#8221;. Then paste the following code into the textbox:</p>

<pre><code>&#60;div class=&#34;setpreview&#34;&#62;
&#60;div class=&#34;thumbnail&#34;&#62;
&#60;txp:vdh_flickr_set_link title=&#34;Proceed to this gallery&#34;&#62;&#60;txp:vdh_flickr_set_img title=&#34;Proceed to this gallery&#34; /&#62;&#60;/txp:vdh_flickr_set_link&#62;
&#60;/div&#62;
&#60;div&#62;
&#60;h3 class=&#34;title&#34;&#62;&#60;txp:vdh_flickr_set_link title=&#34;Proceed to this gallery&#34;&#62;&#60;txp:vdh_flickr_set_title /&#62;&#60;/txp:vdh_flickr_set_link&#62;&#60;/h3&#62;
&#60;h4 class=&#34;number_of_photos&#34;&#62;&#60;txp:vdh_flickr_set_number_of_photos /&#62; Photos&#60;/h4&#62;
&#60;p class=&#34;set_description&#34;&#62;&#60;txp:vdh_flickr_set_description /&#62;&#60;/p&#62;
&#60;/div&#62;
&#60;div style=&#34;clear:both;&#34;&#62;&#60;/div&#62;
&#60;/div&#62;
</code></pre>

	<h4><strong>thumbnails</strong>, a form for the thumbnails page</h4>

	<p>Create this form as described above.</p>

<pre><code>&#60;h3&#62;&#60;txp:vdh_flickr_thumbnails_title /&#62;, &#60;txp:vdh_flickr_thumbnails_number_of_photos /&#62; Photos&#60;/h3&#62;
&#60;p class=&#34;flickr_slideshow&#34;&#62;
&#60;txp:vdh_flickr_thumbnails_slideshow&#62;&#38;raquo; Show as slideshow in new window.&#60;/txp:vdh_flickr_thumbnails_slideshow&#62;
&#60;/p&#62;
&#60;txp:vdh_flickr_thumbnails_if_description&#62;
&#60;p class=&#34;flickr_thumbnails_description&#34;&#62;
&#60;txp:vdh_flickr_thumbnails_description /&#62;
&#60;/p&#62;
&#60;/txp:vdh_flickr_thumbnails_if_description&#62;
&#60;div class=&#34;flickrset&#34;&#62;
&#60;txp:vdh_flickr_thumbnails_list /&#62;
&#60;/div&#62;
&#60;txp:vdh_flickr_thumbnails_if_multiple_pages&#62;
&#60;h3 class=&#34;pages_nav&#34;&#62;pages navigation&#60;/h3&#62;
&#60;p&#62;
thumbs per page: &#60;txp:vdh_flickr_thumbnails_per_page /&#62;&#60;br /&#62;
Showing page &#60;txp:vdh_flickr_thumbnails_current_page /&#62; of &#60;txp:vdh_flickr_thumbnails_total_pages /&#62;.&#60;br /&#62;
Showing thumb &#60;txp:vdh_flickr_thumbnails_pages_startthumb /&#62; to &#60;txp:vdh_flickr_thumbnails_pages_endthumb /&#62;.&#60;br /&#62;
&#60;txp:vdh_flickr_thumbnails_pages_first&#62;&#38;laquo; first&#60;/txp:vdh_flickr_thumbnails_pages_first&#62; |
&#60;txp:vdh_flickr_thumbnails_pages_previous&#62;&#38;lt; previous&#60;/txp:vdh_flickr_thumbnails_pages_previous&#62; |
&#60;txp:vdh_flickr_thumbnails_pages_next&#62;next &#38;gt;&#60;/txp:vdh_flickr_thumbnails_pages_next&#62; |
&#60;txp:vdh_flickr_thumbnails_pages_last&#62;last &#38;raquo;&#60;/txp:vdh_flickr_thumbnails_pages_last&#62;
&#60;/p&#62;
Go to page number:
&#60;txp:vdh_flickr_thumbnails_pages_list wraptag=&#34;ul&#34; break=&#34;li&#34; class =&#34;thumbs_pages&#34; /&#62;
&#60;/txp:vdh_flickr_thumbnails_if_multiple_pages&#62;
&#60;div style=&#34;clear:both;&#34;&#62;&#60;/div&#62;
</code></pre>

	<h4><strong>image</strong>, a form for the image page</h4>

	<p>Create this form as described above.</p>

<pre><code>&#60;div class=&#34;individual&#34;&#62;&#60;div class=&#34;image&#34;&#62;
&#60;h2 class=&#34;title&#34;&#62;&#60;txp:vdh_flickr_img_title /&#62;&#60;/h2&#62;
&#60;txp:vdh_flickr_img_link&#62;&#60;txp:vdh_flickr_img_naked /&#62;&#60;/txp:vdh_flickr_img_link&#62;
&#60;div class=&#34;flickrsetnav&#34;&#62;
&#60;txp:vdh_flickr_img_previous label=&#34;previous&#38;nbsp;:&#38;nbsp;&#34;&#62;&#38;larr;&#60;/txp:vdh_flickr_img_previous&#62;
&#60;h2 class=&#34;setname&#34;&#62;&#60;txp:vdh_flickr_img_set_link&#62;&#60;txp:vdh_flickr_img_set_title /&#62;&#60;/txp:vdh_flickr_img_set_link&#62;&#60;/h2&#62;
&#60;txp:vdh_flickr_img_next label=&#34;next&#38;nbsp;:&#38;nbsp;&#34;&#62;&#38;rarr;&#60;/txp:vdh_flickr_img_next&#62;
&#60;/div&#62;
&#60;div class=&#34;flickr_tag_list&#34;&#62;
&#60;txp:vdh_flickr_img_tags separator=&#34; | &#34; /&#62;
&#60;/div&#62;
&#60;div class=&#34;flickr_comments&#34;&#62;
&#60;txp:vdh_flickr_img_number_of_comments /&#62; Comments.
&#60;txp:vdh_flickr_img_comments_invite&#62;Show and post comments!&#60;/txp:vdh_flickr_img_comments_invite&#62;&#60;br /&#62;
Posted &#60;txp:vdh_flickr_img_date_posted /&#62;.&#60;br /&#62;
Taken &#60;txp:vdh_flickr_img_date_taken /&#62;.&#60;br /&#62;
&#60;/div&#62;
&#60;txp:vdh_flickr_img_if_description&#62;
&#60;p class=&#34;img_description&#34;&#62;
&#60;txp:vdh_flickr_img_description /&#62;
&#60;/p&#62;
&#60;/txp:vdh_flickr_img_if_description&#62;
&#60;/div&#62;&#60;/div&#62;&#60;div style=&#34;clear:both;&#34;&#62;&#60;/div&#62;
</code></pre>

	<h3>Using the created forms</h3>

	<p>Take the following code as an example to enable using your freshly created forms:</p>

<pre><code>&#60;txp:vdh_flickr nsid=&#34;43973976@N00&#34; set_form=&#34;preview&#34; thumbnails_form=&#34;thumbnails&#34; img_form=&#34;image&#34; /&#62;
</code></pre>

	<p>Now you can begin customizing your forms.<br />
Maybe you want to rename the divs on the set preview page. So open your preview form and do so. Maybe you don&#8217;t want navigation underneath the images. So delete all the vdh_flickr tags concerning navigation in the image form. Or maybe you just want all the text to appear in your language &#8211; no problem any more.</p>

	<h3>Other form tags</h3>

	<p>You can use these tags to show a thumbnail of the previous or next image instead of the arrows (<code>&#38;larr;</code> &larr; respective <code>&#38;rarr;</code> &rarr;) in the image form.</p>

	<ul>
		<li><code>&#60;txp:vdh_flickr_img_previous_thumbnail /&#62;</code></li>
	</ul>
	<ul>
		<li><code>&#60;txp:vdh_flickr_img_next_thumbnail /&#62;</code></li>
	</ul>

	<h3>Form tag attributes, all not mandatory</h3>

	<h4>for <code>&#60;txp:vdh_flickr_thumbnails_list /&#62;</code></h4>

	<ul>
		<li><strong>listmode=&#8220;img|text&#8221;</strong> Show the thumbnail list as images (default) or as a text list.</li>
		<li><strong>wraptag=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
	</ul>
	<ul>
		<li><strong>break=&#8221;...&#8221;</strong> See <span class="caps">TXP</span>.</li>
	</ul>

	<p><a href="#contents">&uarr; top</a></p>
# --- END PLUGIN HELP ---
-->
<?php
}
?>
