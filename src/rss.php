<?php

class RSS {
	public static function feed(string|array|object $url): array {
		$data = array();

		// If there are more then 1 $url in object and array type.
		if (is_object($url) or is_array($url))
			$data = self::multi_feed_data($url);
		// If only 1 $url in string type.
		else if (is_string($url))
			$data = self::feed_data($url);
		else {
		}

		return $data;
	}

	// For multiple feeds in array and object type only.
	public static function multi_feed_data(array|object $urls): array {
		$data = array();

		foreach ($urls as $url) {
			$data[] = self::feed($url);
		}
		// Sort feed by time
		usort($data, function($t1, $t2) {
			$time1 = $t1['date']." ".$t1['time'];
			$time2 = $t2['date']." ".$t2['time'];

			return strtotime($time1) + strtotime($time2);
		});

		return $data;
	}

	// For single feed in string type.
	public static function feed_data(string $url): array {
		// Checks if $url content type is html. If it is HTML then finds the Feed
		// url and changes $url to feed url.

		$its_html = self::check_content_type($url, "html");
		if($its_html) { $url = self::get_feed_url($content); }

		$content = self::get_content($url);

		$xml = self::get_feed($content);

		return self::format_data($xml);
	}

	// Simplifies the feed data.
	private static function format_data(object $xml): array {
		// Formats time and returns $type of time format.
		$time_format = function(object|string $time, string $format = "d-M-Y H:i"):
			string {

			// Convert to time format
			$timestamp = strtotime("$time");

			$time = date($format, $timestamp);

			return $time;
		};

		// Formats the xml data
		$data = [
			"title" => "$xml->title",
			"description" => "$xml->description",
			"date" => $time_format($xml->lastBuildDate, "d-M-Y"),
			"time" => $time_format($xml->lastBuildDate, "H:i"),
			"link" => "$xml->link",
			"item" => array()
		];

		// Formats the xml items data
		foreach ($xml->item as $item) {
			$data_item = [
				"title" => "$item->title",
				"link" => "$item->link",
				"date" => $time_format($item->pubDate, "d-M-Y"),
				"time" => $time_format($item->pubDate, "H:i")
			];

			array_push($data["item"], $data_item);
		}

		return $data;
	}

	// Checks feed xml version and returns content as per feed
	private static function get_feed(string $content): object {
		// Converts $content to object
		$xml_data = new SimpleXmlElement($content, LIBXML_NOCDATA);

		$xml = (object)array();				// Convert array to object

		if ($xml_data->channel)				// If feed is xml vesrion 1.0
			$xml = $xml_data->channel;
		else if($xml_data->entry)			// If feed is xml version 2.0
			$xml = $xml_data;
		else {
			/* self::console_log("$url: Invalid content type"); */
		}

		return $xml;
	}

	// Returns given $url page content
	private static function get_content(string $url, $useragent = "FeedFetcher-Google"): string {
		$curl = curl_init($url);

		// Return the transfer as a string.
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// The maximum number of seconds to allow cURL functions to execute.
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		// Changes curl useragent
		curl_setopt($curl, CURLOPT_USERAGENT, $useragent);

		// Given $url content
		$content = curl_exec($curl);

		//Close the curl session
		curl_close($curl);
		return $content;
	}

	// Finds Feed url from HTML.
	private static function get_feed_url(string $content): string {
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
	private static function check_content_type(string $url, string
		$type_name): bool {
		// Get $url header
		$header = get_headers($url, true);

		$content_type = "Content-Type";
		// Contet type in lower case
		$content_type_lower = strtolower($content_type);

		// Find $type_name in header
		$content_pos = strpos($header[$content_type], $type_name);
		$content_pos_lower = strpos($header[$content_type_lower], $type_name);

		if ($content_pos)
			$checked_header = true;
		else if ($content_pos_lower)
			$checked_header = false;
		else {}

		return $checked_header;
	}
}
