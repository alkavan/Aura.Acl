<?php
/**
 *
 * This file is part of the Aura for PHP.
 *
 * @package Aura.Acl
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */

namespace Aura\Acl;

class Resource
{
	/**
	 * Unique id of Resource
	 *
	 * @var string
	 */
	protected $resource_id;

	/**
	 * Sets the Resource identifier
	 *
	 * @param  string $resource_id
	 */
	public function __construct($resource_id)
	{
		$this->resource_id = (string) $resource_id;
	}

	/**
	 * Defined by ResourceInterface; returns the Resource identifier
	 *
	 * @return string
	 */
	public function getResourceId()
	{
		return $this->resource_id;
	}

	/**
	 * Defined by ResourceInterface; returns the Resource identifier
	 * Proxies to getResourceId()
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->getResourceId();
	}
}