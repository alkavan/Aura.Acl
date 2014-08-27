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


class Role
{
	/**
	 * Unique id of Role
	 *
	 * @var string
	 */
	protected $role_id;

	/**
	 * Sets the Role identifier
	 *
	 * @param string $role_id
	 */
	public function __construct($role_id)
	{
		$this->role_id = (string) $role_id;
	}

	/**
	 * Defined by RoleInterface; returns the Role identifier
	 *
	 * @return string
	 */
	public function getRoleId()
	{
		return $this->role_id;
	}

	/**
	 * Defined by RoleInterface; returns the Role identifier
	 * Proxies to getRoleId()
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->getRoleId();
	}
}
