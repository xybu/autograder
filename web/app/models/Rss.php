<?php
/**
 * A simple RSS wrapper class.
 * This abstraction layer is added in preparation of future needs.
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
	 * Return an array of all items.
	 */
	function get_items() {
		return $this->get_channel_info()->item;
	}
	
	function get_raw() {
		return $this->feed->asXML();
	}
	
	function find_items_by_guid($guid) {
		return $this->get_channel_info()->xpath('item/guid[text()="' . $guid . '"]/..');
	}
	
	function add_item($title, $description, $link, $pubDate, $guid = "") {
		if ($guid == '') {
			// make sure the randomly generated guid is unique in the rss feed
			$test = array();
			do {
				$guid = uniqid() . '-' . uniqid() . '-' . uniqid();
				$test = $this->find_items_by_guid($guid);
			} while (count($test) != 0);
		}
		
		$new_item = $this->get_channel_info()->addChild('item', '');
		$new_item->addChild('title', $title);
		$new_item->addChild('description', $description);
		$new_item->addChild('link', $link);
		$new_item->addChild('pubDate', $pubDate);
		$new_item->addChild('guid', $guid);
	}
	
	function edit_item($title, $description, $link, $pubDate, $guid) {
		// find all items with given guid
		$item_nodes = $this->find_items_by_guid($guid);
		foreach ($item_nodes as &$node) {
			$node->title = $title;
			$node->description = $description;
			$node->link = $link;
			$node->pubDate = $pubDate;
			$node->guid = $guid;
		}
	}
	
	function delete_item($guid) {
		$item_nodes = $this->find_items_by_guid($guid);
		// SimpleXMLElement itself has an [0] index
		foreach ($item_nodes as &$node) unset($node[0]);
	}
	
	function save($file_path = "") {
		// update the metadata
		$this->get_channel_info()->pubDate = date('c');
		$this->get_channel_info()->lastBuildDate = date('c');
		
		if (empty($file_path)) $file_path = $this->path;
		return @file_put_contents($file_path, $this->get_raw(), LOCK_EX);
	}
	
}