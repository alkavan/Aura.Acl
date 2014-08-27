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
		$role_id = $role->getRoleId();

		if ($this->has($role_id)) {
			throw new Exception\InvalidArgument(sprintf(
				'Role id "%s" already exists in the registry',
				$role_id
			));
		}

		$role_parents = array();

		if (null !== $parents) {
			if ( ! is_array($parents) && ! $parents instanceof Traversable) {
				$parents = array($parents);
			}
			foreach ($parents as $parent) {
				try {
					if ($parent instanceof Role) {
						$role_parent_id = $parent->getRoleId();
					} else {
						$role_parent_id = $parent;
					}
					$role_parent = $this->get($role_parent_id);
				} catch (\Exception $e) {
					throw new Exception\InvalidArgument(sprintf(
						'Parent Role id "%s" does not exist',
						$role_parent_id
					), 0, $e);
				}
				$role_parents[$role_parent_id] = $role_parent;
				$this->roles[$role_parent_id]['children'][$role_id] = $role;
			}
		}

		$this->roles[$role_id] = array(
			'instance' => $role,
			'parents'  => $role_parents,
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
			$role_id = $role->getRoleId();
		} else {
			$role_id = (string) $role;
		}

		if (!$this->has($role)) {
			throw new Exception\InvalidArgument("Role '{$role_id}' not found");
		}

		return $this->roles[$role_id]['instance'];
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
			$role_id = $role->getRoleId();
		} else {
			$role_id = (string) $role;
		}

		return isset($this->roles[$role_id]);
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
		$role_id = $this->get($role)->getRoleId();

		return $this->roles[$role_id]['parents'];
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
	 * @param  bool         $only_parents
	 * @throws Exception\InvalidArgument
	 * @return bool
	 */
	public function inherits($role, $inherit, $only_parents = false)
	{
		try {
			$role_id    = $this->get($role)->getRoleId();
			$inherit_id = $this->get($inherit)->getRoleId();
		} catch (Exception $e) {
			throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
		}

		$inherits = isset($this->roles[$role_id]['parents'][$inherit_id]);

		if ($inherits || $only_parents) {
			return $inherits;
		}

		foreach ($this->roles[$role_id]['parents'] as $parent_id => $parent) {
			if ($this->inherits($parent_id, $inherit_id)) {
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
			$role_id = $this->get($role)->getRoleId();
		} catch (Exception $e) {
			throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
		}

		// Iterate children
		foreach ($this->roles[$role_id]['children'] as $child_id => $child) {
			unset($this->roles[$child_id]['parents'][$role_id]);
		}

		// Iterate parents
		foreach ($this->roles[$role_id]['parents'] as $parent_id => $parent) {
			unset($this->roles[$parent_id]['children'][$role_id]);
		}

		unset($this->roles[$role_id]);

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
