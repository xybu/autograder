<?php
/**
 * A simple RSS wrapper class.
 * This abstraction layer is added in case of future need.
 * 
 * @author	Xiangyu Bu <xybu92@live.com>
 */

namespace models;

class Rss extends \Model {
	
	protected $feed;
	protected $path;
	
	function __construct($file_path = "") {
		parent::__construct();
		if ($file_path != "") {
			$this->load_from_file($file_path);
			$this->path = $file_path;
		}
	}
	
	/**
	 * Load RSS xml from a file path.
	 * @return SimpleXMLElement | false
	 */
	function load_from_file($file_path) {
		$this->feed = simplexml_load_file($file_path);
		$this->path = $file_path;
		return $this->feed;
	}
	
	/** 
	 * Load RSS xml from string.
	 * @return SimpleXMLElement | false
	 */
	function load_from_str($str) {
		$this->feed = simplexml_load_string($str);
		return $this->feed;
	}
	
	/**
	 * Return an SimpleXMLElement obj representing the channel metadata.
	 */
	function get_channel_info() {
		return $this->feed->channel;
	}
	
	/**
	 * Return the loaded feed object.
	 */
	function get_feed() {
		return $this->feed;
	}
	
	/**
	 * Return an array of all items.
	 */
	function get_items() {
		return $this->feed->channel->item;
	}
	
	function get_raw() {
		return $this->feed->asXML();
	}
	
	function add_item($title, $description, $link, $pubDate, $guid) {
		$new_item = new SimpleXMLElement("<item />");
		$new_item->addChild('title', $title);
		$new_item->addChild('description', $description);
		$new_item->addChild('link', $link);
		$new_item->addChild('pubDate', $pubDate);
		$new_item->addChild('guid', $guid);
		$this->feed->channel->appendChild($new_item);
	}
	
	function edit_item() {
	}
	
	function delete_item($guid) {
		
	}
	
	function save($file_path = "") {
		if (empty($file_path)) $file_path = $this->path;
		return file_put_contents($file_path, $this->get_raw());
	}
	
}