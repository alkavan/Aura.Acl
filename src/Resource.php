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
	protected $resourceId;

	/**
	 * Sets the Resource identifier
	 *
	 * @param  string $resourceId
	 */
	public function __construct($resourceId)
	{
		$this->resourceId = (string) $resourceId;
	}

	/**
	 * Defined by ResourceInterface; returns the Resource identifier
	 *
	 * @return string
	 */
	public function getResourceId()
	{
		return $this->resourceId;
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