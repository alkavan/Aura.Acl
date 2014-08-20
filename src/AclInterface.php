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

use Aura\Acl\Resource;

interface AclInterface
{
	/**
	 * Returns true if and only if the Resource exists in the ACL
	 *
	 * The $resource parameter can either be a Resource or a Resource identifier.
	 *
	 * @param  Resource|string $resource
	 * @return bool
	 */
	public function hasResource($resource);

	/**
	 * Returns true if and only if the Role has access to the Resource
	 *
	 * The $role and $resource parameters may be references to, or the string identifiers for,
	 * an existing Resource and Role combination.
	 *
	 * If either $role or $resource is null, then the query applies to all Roles or all Resources,
	 * respectively. Both may be null to query whether the ACL has a "blacklist" rule
	 * (allow everything to all). By default, Aura\Acl\Acl creates a "whitelist" rule (deny
	 * everything to all), and this method would return false unless this default has
	 * been overridden (i.e., by executing $acl->allow()).
	 *
	 * If a $privilege is not provided, then this method returns false if and only if the
	 * Role is denied access to at least one privilege upon the Resource. In other words, this
	 * method returns true if and only if the Role is allowed all privileges on the Resource.
	 *
	 * This method checks Role inheritance using a depth-first traversal of the Role registry.
	 * The highest priority parent (i.e., the parent most recently added) is checked first,
	 * and its respective parents are checked similarly before the lower-priority parents of
	 * the Role are checked.
	 *
	 * @param  Role|string  $role
	 * @param  Resource|string   $resource
	 * @param  string            $privilege
	 * @return bool
	 */
	public function isAllowed($role = null, $resource = null, $privilege = null);
}