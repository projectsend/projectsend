<?php

/*
 * Copyright (c) 2012 The University of Queensland
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/*
 * Written by David Gwynne <dlg@uq.edu.au> as part of the IT
 * Infrastructure Group in the Faculty of Engineering, Architecture
 * and Information Technology.
 */

class sspmod_riak_Store_Store extends SimpleSAML_Store {
	protected function __construct() {
		$config = SimpleSAML_Configuration::getConfig('module_riak.php');

		$path = $config->getString('path', 'riak-php-client/riak.php');
		$host = $config->getString('host', 'localhost');
		$port = $config->getString('port', 8098);
		$bucket = $config->getString('bucket', 'simpleSAMLphp');

		require_once($path);
		$this->client = new RiakClient($host, $port);
		$this->bucket = $this->client->bucket($bucket);
	}

	/**
	 * Retrieve a value from the datastore.
	 *
	 * @param string $type  The datatype.
	 * @param string $key  The key.
	 * @return mixed|NULL  The value.
	 */
	public function get($type, $key) {
		assert('is_string($type)');
		assert('is_string($key)');

		$v = $this->bucket->getBinary("$type.$key");
		if (!$v->exists()) {
			return (NULL);
		}

		$expires = $v->getIndex('Expires', 'int');
		if (sizeof($expires) && (int)array_shift($expires) <= time()) {
			$v->delete();
			return (NULL);
		}

		return (unserialize($v->getData()));
	}


	/**
	 * Save a value to the datastore.
	 *
	 * @param string $type  The datatype.
	 * @param string $key  The key.
	 * @param mixed $value  The value.
	 * @param int|NULL $expire  The expiration time (unix timestamp), or NULL if it never expires.
	 */
	public function set($type, $key, $value, $expire = NULL) {
		assert('is_string($type)');
		assert('is_string($key)');
		assert('is_null($expire) || (is_int($expire) && $expire > 2592000)');

		$v = $this->bucket->newBinary("$type.$key", serialize($value), 'application/php');
		if (!is_null($expire)) {
			$v->addIndex("Expires", "int", $expire);
		}

		$v->store();
	}

	/**
	 * Delete a value from the datastore.
	 *
	 * @param string $type  The datatype.
	 * @param string $key  The key.
	 */
	public function delete($type, $key) {
		assert('is_string($type)');
		assert('is_string($key)');

		$v = $this->bucket->getBinary("$type.$key");
		if (!$v->exists()) {
			return;
		}

		$v->delete();
	}
}
