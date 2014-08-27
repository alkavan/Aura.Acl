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

namespace Aura\Acl\Assertion;

use Aura\Acl\Acl;
use Aura\Acl\Resource;
use Aura\Acl\Role;

interface AssertionInterface
{
    /**
     * Returns true if and only if the assertion conditions are met
     *
     * This method is passed the ACL, Role, Resource, and privilege to which the authorization query applies. If the
     * $role, $resource, or $privilege parameters are null, it means that the query applies to all Roles, Resources, or
     * privileges, respectively.
     *
     * @param  Acl $acl
     * @param  Role $role
     * @param  Resource $resource
     * @param  string $privilege
     * @return bool
     */
    public function assert(Acl $acl, Role $role = null, Resource $resource = null, $privilege = null);
}