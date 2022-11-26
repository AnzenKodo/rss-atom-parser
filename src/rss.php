<?php
error_reporting(0);
ini_set('display_errors', 0);

class RSS {
	public static $useragent = "FeedFetcher-Google";

	public static function feed(string|array|object $url): object {
		$data = array();

		// If there are more then 1 $url in object and array type.
		if (is_object($url) or is_array($url))
			$data = self::getFeeds($url);
		// If only 1 $url in string type.
		else if (is_string($url)) {
			$data = self::getFeed($url);
		} else {
			throw new ErrorException("Can't find datatype of {$url}");
		}

		return $data;
	}

	// For multiple feeds in array and object type only.
	public static function getFeeds(array|object $urls): object {
		$data = array();

		foreach ($urls as $url) {
			$data[] = self::feed($url);
		}

		// Sort feed by time
		$ord = array();
		foreach ($data as $key){
		    $ord[] = strtotime($key->item[0]->date);
		}
		array_multisort($ord, SORT_DESC, $data);
		
		return (object)$data;
	}

	// For single feed in string type.
	public static function getFeed(string $url): object {
		ini_set('user_agent', self::$useragent);

		// Checks if $url content type is html. If it is HTML then finds the Feed
		// url and changes $url to feed url.
		$its_html = self::checkContentType($url, "html");
		if($its_html) {
			$content_html = self::getContent($url);
			$url = self::getFeedUrl($content_html);
		}

		$content = self::getContent($url);
		
		try {
			$xml = new SimpleXmlElement($content, true);
		} catch(Exception $e) {
			return (object)array();
		}

		$xml = new SimpleXmlElement($content, true);
		$data = (object)array();
		if ($xml->channel) {					// If feed is xml vesrion 1.0
			$data = self::getRSS($xml, $url);
		}	else if($xml->entry) {			// If feed is xml version 2.0
			$data = self::getAtom($xml, $url);
		} else {
		}

		return (object)$data;
	}

	private static function isHttp(string $str, string $url, bool $boolean = false) {
		if (preg_match("/^(https|http):\/\//", $str)) return $str;

		if ($str[0] == "/") return preg_replace('/(?<=(\w|\d))\/.*$/', $str, $url);

		if ($boolean) return false;

		return $url;
	}
	
	private static function getAtom(SimpleXMLElement $xml, string $url): array {
		$title = $xml->id ? $xml->id : $url;
		$title = $xml->title != "" ? $xml->title : $title;

		// Find proper name
		$replace = "";
		if (isset($xml->link)) {
			for ($x = 0; $x < 2; $x++) {
				if ($xml->link[$x]["rel"] == "alternate") {
					$replace = $xml->link[$x]["href"];
					break;
				} else {
					$replace = $url;
				}
			}
		} else if (isset($xml->author->uri)) {
			$replace = $xml->author->uri;
		} else {
			$replace = $url;
		}

		$data = [
			"title" => $title,
			"description" => "$xml->subtitle",
			"date" => self::timeFormat($xml->updated, "d-M-Y"),
			"time" => self::timeFormat($xml->updated, "H:i"),
			"link" => self::isHttp($replace, $url, true) ? self::isHttp($replace, $url) : self::isHttp($xml->id, $url),
			"feed" => "$url",
			"type" => "atom",
			"item" => array()
		];

		foreach ($xml->entry as $item) {
			$time =	$item->updated ? $item->updated : $item->published;

			$data_item = [
				"title" => "$item->title",
				"link" => self::isHttp($item->link["href"], $url),
				"date" => self::timeFormat($time, "d-M-Y"),
				"time" => self::timeFormat($time, "H:i")
			];

			array_push($data["item"], (object)$data_item);
		}

		return $data;
	}

	private static function getRSS(SimpleXMLElement $xml, string $url): array {
		$xml = $xml->channel;
		$time =	$xml->lastBuildDate ? $xml->lastBuildDate : $xml->pubDate;

		$data = [
			"title" => $xml->title != "" ? $xml->title : $xml->link,
			"description" => "$xml->description",
			"date" => self::timeFormat($time, "d-M-Y"),
			"time" => self::timeFormat($time, "H:i"),
			"link" => $xml->link != "" ? self::isHttp($xml->link, $url) : $url,
			"feed" => $url,
			"type" => "rss",
			"item" => array()
		];

		foreach ($xml->item as $item) {
			$data_item = [
				"title" => $item->title != "" ? $item->title : $item->link,
				"link" => $item->link ? self::isHttp($item->link, $url) : $item->enclosure["url"],
				"date" => self::timeFormat($item->pubDate, "d-M-Y"),
				"time" => self::timeFormat($item->pubDate, "H:i")
			];

			array_push($data["item"], (object)$data_item);
		}

		return $data;
	}

	private static function timeFormat(object|string $time, string $format =
		"d-M-Y H:i"): string {

			// Convert to time format
			$timestamp = strtotime("$time");

			$time = date($format, $timestamp);

			return $time;
	}

	// Returns given $url page content
	private static function getContent(string $url): string {
		$curl = curl_init($url);

		// Return the transfer as a string.
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// The maximum number of seconds to allow cURL functions to execute.
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		// Changes curl useragent
		curl_setopt($curl, CURLOPT_USERAGENT, self::$useragent);
		// To make cURL follow a redirect
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Given $url content
		$content = curl_exec($curl);

		//Close the curl session
		curl_close($curl);
		return $content;
	}

	// Finds Feed url from HTML.
	private static function getFeedUrl(string $content): string {
		// Suppresses DOMDocuments errors
		libxml_use_internal_errors(true);

		$dom = new DOMDocument();
		$dom->loadHTML($content);

		// Finds Link tag in document
		$link_tags = $dom->getElementsByTagName('link');
		$url = "";

		foreach ($link_tags as $link_tag) {
			// Link tag attributes type & href
			$link_attr_type = $link_tag->getAttribute('type');
			$link_attr_href = $link_tag->getAttribute('href');

			if ($link_attr_type === "application/rss+xml" or
				$link_attr_type === "application/atom+xml")
				$url = $link_attr_href;
		}

		// Returns feed link
		return $url;
	}

	// Checks given $content_type_name value matches given $url header content
	// type.
	private static function checkContentType(string $url, string
		$type_name): bool {
		// Get $url header

		stream_context_set_default( [
		    'ssl' => [
		        'verify_peer' => false,
		        'verify_peer_name' => false,
		    ],
		]);

		$header = "";
		$content_type = "";

		$validhost = filter_var(gethostbyname(parse_url($url,PHP_URL_HOST)), FILTER_VALIDATE_IP);
		if ($validhost) {
			$header = get_headers($url, true);
		} else {
			return false;
		}

		// Find $type_name in header
		if (isset($header["Content-Type"]))
			$content_type = $header["Content-Type"];
		else
			$content_type = $header["content-type"];

		if (gettype($content_type) == "array")
			$content_type = $content_type[0];
		
		if (strpos($content_type, $type_name))
			$checked_header = true;
		else {
			$checked_header = false;
		}

		return $checked_header;
	}
}
