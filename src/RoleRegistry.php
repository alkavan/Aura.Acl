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

use Traversable;
use Aura\Acl\Exception;

class RoleRegistry
{
	/**
	 * Internal Role registry data storage
	 *
	 * @var array
	 */
	protected $roles = array();

	/**
	 * Adds a Role having an identifier unique to the registry
	 *
	 * The $parents parameter may be a reference to, or the string identifier for,
	 * a Role existing in the registry, or $parents may be passed as an array of
	 * these - mixing string identifiers and objects is ok - to indicate the Roles
	 * from which the newly added Role will directly inherit.
	 *
	 * In order to resolve potential ambiguities with conflicting rules inherited
	 * from different parents, the most recently added parent takes precedence over
	 * parents that were previously added. In other words, the first parent added
	 * will have the least priority, and the last parent added will have the
	 * highest priority.
	 *
	 * @param  Role                           $role
	 * @param  Role|string|array|Traversable $parents
	 * @throws Exception\InvalidArgument
	 * @return RoleRegistry Provides a fluent interface
	 */
	public function add(Role $role, $parents = null)
	{
		$roleId = $role->getRoleId();

		if ($this->has($roleId)) {
			throw new Exception\InvalidArgument(sprintf(
				'Role id "%s" already exists in the registry',
				$roleId
			));
		}

		$roleParents = array();

		if (null !== $parents) {
			if (!is_array($parents) && !$parents instanceof Traversable) {
				$parents = array($parents);
			}
			foreach ($parents as $parent) {
				try {
					if ($parent instanceof Role) {
						$roleParentId = $parent->getRoleId();
					} else {
						$roleParentId = $parent;
					}
					$roleParent = $this->get($roleParentId);
				} catch (\Exception $e) {
					throw new Exception\InvalidArgument(sprintf(
						'Parent Role id "%s" does not exist',
						$roleParentId
					), 0, $e);
				}
				$roleParents[$roleParentId] = $roleParent;
				$this->roles[$roleParentId]['children'][$roleId] = $role;
			}
		}

		$this->roles[$roleId] = array(
			'instance' => $role,
			'parents'  => $roleParents,
			'children' => array(),
		);

		return $this;
	}

	/**
	 * Returns the identified Role
	 *
	 * The $role parameter can either be a Role or a Role identifier.
	 *
	 * @param  Role|string $role
	 * @throws Exception\InvalidArgument
	 * @return Role
	 */
	public function get($role)
	{
		if ($role instanceof Role) {
			$roleId = $role->getRoleId();
		} else {
			$roleId = (string) $role;
		}

		if (!$this->has($role)) {
			throw new Exception\InvalidArgument("Role '$roleId' not found");
		}

		return $this->roles[$roleId]['instance'];
	}

	/**
	 * Returns true if and only if the Role exists in the registry
	 *
	 * The $role parameter can either be a Role or a Role identifier.
	 *
	 * @param  Role|string $role
	 * @return bool
	 */
	public function has($role)
	{
		if ($role instanceof Role) {
			$roleId = $role->getRoleId();
		} else {
			$roleId = (string) $role;
		}

		return isset($this->roles[$roleId]);
	}

	/**
	 * Returns an array of an existing Role's parents
	 *
	 * The array keys are the identifiers of the parent Roles, and the values are
	 * the parent Role instances. The parent Roles are ordered in this array by
	 * ascending priority. The highest priority parent Role, last in the array,
	 * corresponds with the parent Role most recently added.
	 *
	 * If the Role does not have any parents, then an empty array is returned.
	 *
	 * @param  Role|string $role
	 * @return array
	 */
	public function getParents($role)
	{
		$roleId = $this->get($role)->getRoleId();

		return $this->roles[$roleId]['parents'];
	}

	/**
	 * Returns true if and only if $role inherits from $inherit
	 *
	 * Both parameters may be either a Role or a Role identifier. If
	 * $onlyParents is true, then $role must inherit directly from
	 * $inherit in order to return true. By default, this method looks
	 * through the entire inheritance DAG to determine whether $role
	 * inherits from $inherit through its ancestor Roles.
	 *
	 * @param  Role|string  $role
	 * @param  Role|string  $inherit
	 * @param  bool         $onlyParents
	 * @throws Exception\InvalidArgument
	 * @return bool
	 */
	public function inherits($role, $inherit, $onlyParents = false)
	{
		try {
			$roleId    = $this->get($role)->getRoleId();
			$inheritId = $this->get($inherit)->getRoleId();
		} catch (Exception $e) {
			throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
		}

		$inherits = isset($this->roles[$roleId]['parents'][$inheritId]);

		if ($inherits || $onlyParents) {
			return $inherits;
		}

		foreach ($this->roles[$roleId]['parents'] as $parentId => $parent) {
			if ($this->inherits($parentId, $inheritId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes the Role from the registry
	 *
	 * The $role parameter can either be a Role or a Role identifier.
	 *
	 * @param  Role|string $role
	 * @throws Exception\InvalidArgument
	 * @return RoleRegistry Provides a fluent interface
	 */
	public function remove($role)
	{
		try {
			$roleId = $this->get($role)->getRoleId();
		} catch (Exception $e) {
			throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
		}

		foreach ($this->roles[$roleId]['children'] as $childId => $child) {
			unset($this->roles[$childId]['parents'][$roleId]);
		}
		foreach ($this->roles[$roleId]['parents'] as $parentId => $parent) {
			unset($this->roles[$parentId]['children'][$roleId]);
		}

		unset($this->roles[$roleId]);

		return $this;
	}

	/**
	 * Removes all Roles from the registry
	 *
	 * @return RoleRegistry Provides a fluent interface
	 */
	public function removeAll()
	{
		$this->roles = array();

		return $this;
	}

	/**
	 * Get all roles in the registry
	 *
	 * @return array
	 */
	public function getRoles()
	{
		return $this->roles;
	}
}
