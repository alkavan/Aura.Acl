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

use Aura\Acl\Exception;

/**
 * Acl Service Class
 * @package Aura.Acl
 */
class Acl implements AclInterface
{
    /**
     * Rule type: allow
     */
    const TYPE_ALLOW = 'TYPE_ALLOW';

    /**
     * Rule type: deny
     */
    const TYPE_DENY  = 'TYPE_DENY';

    /**
     * Rule operation: add
     */
    const OP_ADD = 'OP_ADD';

    /**
     * Rule operation: remove
     */
    const OP_REMOVE = 'OP_REMOVE';

    /**
     * Role registry
     *
     * @var RoleRegistry
     */
    protected $role_registry = null;

    /**
     * Resource tree
     *
     * @var array
     */
    protected $resources = array();

    /**
     * @var Role
     */
    protected $is_allowed_role = null;

    /**
     * @var Resource
     */
    protected $is_allowed_resource = null;

    /**
     * @var string
     */
    protected $is_allowed_privilege = null;

    /**
     * ACL rules; whitelist (deny everything to all) by default
     *
     * @var array
     */
    protected $rules = array(
        'allResources' => array(
            'allRoles' => array(
                'allPrivileges' => array(
                    'type'   => self::TYPE_DENY,
                    'assert' => null
                ),
                'byPrivilegeId' => array()
            ),
            'byRoleId' => array()
        ),
        'byResourceId' => array()
    );

    /**
     * New instance of role
     * @param string $role
     * @return Role
     */
    public function newRole($role) {
        return new Role($role);
    }

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
     * @param  Role|string        $role
     * @param  Role|string|array  $parents
     * @throws Exception\InvalidArgument
     * @return Acl Provides a fluent interface
     */
    public function addRole($role, $parents = null)
    {
        if (is_string($role)) {
            $role = $this->newRole($role);
        } elseif ( ! $role instanceof Role) {
            throw new Exception\InvalidArgument(
                'addRole() expects $role to be of type Aura\Acl\Role'
            );
        }

        $this->getRoleRegistry()->add($role, $parents);

        return $this;
    }

    /**
     * Returns the identified Role
     *
     * The $role parameter can either be a Role or Role identifier.
     *
     * @param  Role|string $role
     * @return Role
     */
    public function getRole($role)
    {
        return $this->getRoleRegistry()->get($role);
    }

    /**
     * Returns true if and only if the Role exists in the registry
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @param  Role|string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->getRoleRegistry()->has($role);
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
     *
     * @return bool
     */
    public function inheritsRole($role, $inherit, $only_parents = false)
    {
        return $this->getRoleRegistry()->inherits($role, $inherit, $only_parents);
    }

    /**
     * Removes the Role from the registry
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @param  Role|string  $role
     *
     * @return Acl Provides a fluent interface
     */
    public function removeRole($role)
    {
        $this->getRoleRegistry()->remove($role);

        if ($role instanceof Role) {
            $role_id = $role->getRoleId();
        } else {
            $role_id = $role;
        }

        foreach ($this->rules['allResources']['byRoleId'] as $role_id_current => $rules) {
            if ($role_id === $role_id_current) {
                unset($this->rules['allResources']['byRoleId'][$role_id_current]);
            }
        }
        foreach ($this->rules['byResourceId'] as $resource_id_current => $visitor) {
            if (array_key_exists('byRoleId', $visitor)) {
                foreach ($visitor['byRoleId'] as $role_id_current => $rules) {
                    if ($role_id === $role_id_current) {
                        unset($this->rules['byResourceId'][$resource_id_current]['byRoleId'][$role_id_current]);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Removes all Roles from the registry
     *
     * @return Acl Provides a fluent interface
     */
    public function removeRoleAll()
    {
        $this->getRoleRegistry()->removeAll();

        foreach ($this->rules['allResources']['byRoleId'] as $role_id_current => $rules) {
            unset($this->rules['allResources']['byRoleId'][$role_id_current]);
        }
        foreach ($this->rules['byResourceId'] as $resource_id_current => $visitor) {
            foreach ($visitor['byRoleId'] as $role_id_current => $rules) {
                unset($this->rules['byResourceId'][$resource_id_current]['byRoleId'][$role_id_current]);
            }
        }

        return $this;
    }

    /**
     * New instance of resource
     * @param string $resource
     * @return Resource
     */
    public function newResource($resource) {
        return new Resource($resource);
    }

    /**
     * Adds a Resource having an identifier unique to the ACL
     *
     * The $parent parameter may be a reference to, or the string identifier for,
     * the existing Resource from which the newly added Resource will inherit.
     *
     * @param  Resource|string  $resource
     * @param  Resource|string  $parent
     *
     * @throws Exception\InvalidArgument
     *
     * @return Acl Provides a fluent interface
     */
    public function addResource($resource, $parent = null)
    {
        if (is_string($resource)) {
            $resource = $this->newResource($resource);
        } elseif ( ! $resource instanceof Resource) {
            throw new Exception\InvalidArgument(
                'addResource() expects $resource to be of type Aura\Acl\Resource'
            );
        }

        $resource_id = $resource->getResourceId();

        if ($this->hasResource($resource_id)) {
            throw new Exception\InvalidArgument("Resource id '$resource_id' already exists in the ACL");
        }

        $resource_parent = null;

        if (null !== $parent) {
            try {
                if ($parent instanceof Resource) {
                    $resource_parent_id = $parent->getResourceId();
                } else {
                    $resource_parent_id = $parent;
                }
                $resource_parent = $this->getResource($resource_parent_id);
            } catch (\Exception $e) {
                throw new Exception\InvalidArgument(sprintf(
                    'Parent Resource id "%s" does not exist',
                    $resource_parent_id
                ), 0, $e);
            }
            $this->resources[$resource_parent_id]['children'][$resource_id] = $resource;
        }

        $this->resources[$resource_id] = array(
            'instance' => $resource,
            'parent'   => $resource_parent,
            'children' => array()
        );

        return $this;
    }

    /**
     * Returns the identified Resource
     *
     * The $resource parameter can either be a Resource or a Resource identifier.
     *
     * @param  Resource|string  $resource
     * @throws Exception\InvalidArgument
     * @return Resource
     */
    public function getResource($resource)
    {
        if ($resource instanceof Resource) {
            $resource_id = $resource->getResourceId();
        } else {
            $resource_id = (string) $resource;
        }

        if (!$this->hasResource($resource)) {
            throw new Exception\InvalidArgument("Resource '$resource_id' not found");
        }

        return $this->resources[$resource_id]['instance'];
    }

    /**
     * Returns true if and only if the Resource exists in the ACL
     *
     * The $resource parameter can either be a Resource or a Resource identifier.
     *
     * @param  Resource|string  $resource
     * @return bool
     */
    public function hasResource($resource)
    {
        if ($resource instanceof Resource) {
            $resource_id = $resource->getResourceId();
        } else {
            $resource_id = (string) $resource;
        }

        return isset($this->resources[$resource_id]);
    }

    /**
     * Returns true if and only if $resource inherits from $inherit
     *
     * Both parameters may be either a Resource or a Resource identifier. If
     * $onlyParent is true, then $resource must inherit directly from
     * $inherit in order to return true. By default, this method looks
     * through the entire inheritance tree to determine whether $resource
     * inherits from $inherit through its ancestor Resources.
     *
     * @param  Resource|string  $resource
     * @param  Resource|string  $inherit
     * @param  bool             $only_parent
     *
     * @throws Exception\InvalidArgument
     *
     * @return bool
     */
    public function inheritsResource($resource, $inherit, $only_parent = false)
    {
        try {
            $resource_id = $this->getResource($resource)->getResourceId();
            $inherit_id  = $this->getResource($inherit)->getResourceId();
        } catch (Exception $e) {
            throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $this->resources[$resource_id]['parent']) {
            $parent_id = $this->resources[$resource_id]['parent']->getResourceId();
            if ($inherit_id === $parent_id) {
                return true;
            } elseif ($only_parent) {
                return false;
            }
        } else {
            return false;
        }

        while (null !== $this->resources[$parent_id]['parent']) {
            $parent_id = $this->resources[$parent_id]['parent']->getResourceId();
            if ($inherit_id === $parent_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes a Resource and all of its children
     * The $resource parameter can either be a Resource or a Resource identifier.
     *
     * @param  Resource|string  $resource
     *
     * @throws Exception\InvalidArgument
     *
     * @return Acl Provides a fluent interface
     */
    public function removeResource($resource)
    {
        try {
            $resource_id = $this->getResource($resource)->getResourceId();
        } catch (Exception $e) {
            throw new Exception\InvalidArgument($e->getMessage(), $e->getCode(), $e);
        }

        $resources_removed = array($resource_id);
        if (null !== ($resourceParent = $this->resources[$resource_id]['parent'])) {
            unset($this->resources[$resourceParent->getResourceId()]['children'][$resource_id]);
        }

        foreach ($this->resources[$resource_id]['children'] as $child_id => $child) {
            $this->removeResource($child_id);
            $resources_removed[] = $child_id;
        }

        foreach ($resources_removed as $resource_id_removed) {
            foreach ($this->rules['byResourceId'] as $resource_id_current => $rules) {
                if ($resource_id_removed === $resource_id_current) {
                    unset($this->rules['byResourceId'][$resource_id_current]);
                }
            }
        }

        unset($this->resources[$resource_id]);

        return $this;
    }

    /**
     * Removes all Resources
     *
     * @return Acl Provides a fluent interface
     */
    public function removeResourceAll()
    {
        foreach ($this->resources as $resource_id => $resource) {
            foreach ($this->rules['byResourceId'] as $resource_id_current => $rules) {
                if ($resource_id === $resource_id_current) {
                    unset($this->rules['byResourceId'][$resource_id_current]);
                }
            }
        }

        $this->resources = array();

        return $this;
    }

    /**
     * Adds an "allow" rule to the ACL
     *
     * @param  Role|string|array             $roles
     * @param  Resource|string|array         $resources
     * @param  string|array                  $privileges
     * @param  Assertion\AssertionInterface  $assert
     * @return Acl Provides a fluent interface
     */
    public function allow($roles = null, $resources = null, $privileges = null, Assertion\AssertionInterface $assert = null)
    {
        return $this->setRule(self::OP_ADD, self::TYPE_ALLOW, $roles, $resources, $privileges, $assert);
    }

    /**
     * Adds a "deny" rule to the ACL
     *
     * @param  Role|string|array             $roles
     * @param  Resource|string|array         $resources
     * @param  string|array                  $privileges
     * @param  Assertion\AssertionInterface  $assert
     * @return Acl Provides a fluent interface
     */
    public function deny($roles = null, $resources = null, $privileges = null, Assertion\AssertionInterface $assert = null)
    {
        return $this->setRule(self::OP_ADD, self::TYPE_DENY, $roles, $resources, $privileges, $assert);
    }

    /**
     * Removes "allow" permissions from the ACL
     *
     * @param  Role|string|array      $roles
     * @param  Resource|string|array  $resources
     * @param  string|array           $privileges
     *
     * @return Acl Provides a fluent interface
     */
    public function removeAllow($roles = null, $resources = null, $privileges = null)
    {
        return $this->setRule(self::OP_REMOVE, self::TYPE_ALLOW, $roles, $resources, $privileges);
    }

    /**
     * Removes "deny" restrictions from the ACL
     *
     * @param  Role|string|array      $roles
     * @param  Resource|string|array  $resources
     * @param  string|array           $privileges
     *
     * @return Acl Provides a fluent interface
     */
    public function removeDeny($roles = null, $resources = null, $privileges = null)
    {
        return $this->setRule(self::OP_REMOVE, self::TYPE_DENY, $roles, $resources, $privileges);
    }

    /**
     * Performs operations on ACL rules
     *
     * The $operation parameter may be either OP_ADD or OP_REMOVE, depending on whether the
     * user wants to add or remove a rule, respectively:
     *
     * OP_ADD specifics:
     *
     *      A rule is added that would allow one or more Roles access to [certain $privileges
     *      upon] the specified Resource(s).
     *
     * OP_REMOVE specifics:
     *
     *      The rule is removed only in the context of the given Roles, Resources, and privileges.
     *      Existing rules to which the remove operation does not apply would remain in the
     *      ACL.
     *
     * The $type parameter may be either TYPE_ALLOW or TYPE_DENY, depending on whether the
     * rule is intended to allow or deny permission, respectively.
     *
     * The $roles and $resources parameters may be references to, or the string identifiers for,
     * existing Resources/Roles, or they may be passed as arrays of these - mixing string identifiers
     * and objects is ok - to indicate the Resources and Roles to which the rule applies. If either
     * $roles or $resources is null, then the rule applies to all Roles or all Resources, respectively.
     * Both may be null in order to work with the default rule of the ACL.
     *
     * The $privileges parameter may be used to further specify that the rule applies only
     * to certain privileges upon the Resource(s) in question. This may be specified to be a single
     * privilege with a string, and multiple privileges may be specified as an array of strings.
     *
     * If $assert is provided, then its assert() method must return true in order for
     * the rule to apply. If $assert is provided with $roles, $resources, and $privileges all
     * equal to null, then a rule having a type of:
     *
     *      TYPE_ALLOW will imply a type of TYPE_DENY, and
     *
     *      TYPE_DENY will imply a type of TYPE_ALLOW
     *
     * when the rule's assertion fails. This is because the ACL needs to provide expected
     * behavior when an assertion upon the default ACL rule fails.
     *
     * @param  string                        $operation
     * @param  string                        $type
     * @param  Role|string|array             $roles
     * @param  Resource|string|array         $resources
     * @param  string|array                  $privileges
     * @param  Assertion\AssertionInterface  $assert
     *
     * @throws Exception\InvalidArgument
     *
     * @return Acl Provides a fluent interface
     */
    public function setRule($operation, $type, $roles = null, $resources = null,
                            $privileges = null, Assertion\AssertionInterface $assert = null
    ) {
        $type       = $this->prepareTypeArg($type);
        $roles      = $this->prepareRolesArg($roles);
        $resources  = $this->prepareResourcesArg($resources);
        $privileges = $this->preparePrivilegesArg($privileges);

        $self = $this;

        /**
         * Resource-Role iteration function
         *
         * @param $callback
         * @param $resources
         * @param $roles
         * @param $privileges
         * @param $type
         * @param null $assert
         */
        $iteration = function ($callback, $resources, $roles, $privileges, $type, $assert = null) use ($self) {
            foreach ($resources as $resource) {
                foreach ($roles as $role) {
                    $callback($self, $resource, $role, $privileges, $type, $assert);
                }
            }
        };

        // Operation switch
        switch ($operation)
        {
            // Add to rules
            case self::OP_ADD:
                $iteration(function ($self, $resource, $role, $privileges, $type, $assert) {
                    $rules =& $self->getRules($resource, $role, true);
                    $self->ruleOpAdd($rules, $privileges, $type, $assert);
                }, $resources, $roles, $privileges, $type, $assert);
                break;

            // Remove from rules
            case self::OP_REMOVE:
                $iteration(function ($self, $resource, $role, $privileges, $type) {
                    $rules =& $self->getRules($resource, $role);
                    if (null === $rules) {
                        return;
                    }
                    $self->ruleOpRemove($rules, $resource, $role, $privileges, $type);
                }, $resources, $roles, $privileges, $type);

                break;

            default:
                throw new Exception\InvalidArgument(sprintf(
                    'Unsupported operation; must be either "%s" or "%s"',
                    self::OP_ADD,
                    self::OP_REMOVE
                ));
        }

        return $this;
    }

    /**
     * Prepare 'type' argument of setRule() method
     *
     * @param $type
     * @return string
     * @throws Exception\InvalidArgument
     */
    protected function prepareTypeArg($type)
    {
        // ensure that the rule type is valid; normalize input to uppercase
        $type = strtoupper($type);
        if (self::TYPE_ALLOW !== $type && self::TYPE_DENY !== $type) {
            throw new Exception\InvalidArgument(sprintf(
                'Unsupported rule type; must be either "%s" or "%s"',
                self::TYPE_ALLOW,
                self::TYPE_DENY
            ));
        }

        return $type;
    }

    /**
     * Prepare 'roles' argument of setRule() method
     *
     * @param $roles
     * @return array
     */
    protected function prepareRolesArg($roles)
    {
        // ensure that all specified Roles exist; normalize input to array of Role objects or null
        if (!is_array($roles)) {
            $roles = array($roles);
        } elseif (0 === count($roles)) {
            $roles = array(null);
        }
        $roles_temp = $roles;
        $roles = array();
        foreach ($roles_temp as $role) {
            if (null !== $role) {
                $roles[] = $this->getRoleRegistry()->get($role);
            } else {
                $roles[] = null;
            }
        }
        unset($roles_temp);

        return $roles;
    }

    /**
     * Prepare 'resources' argument of setRule() method
     *
     * @param $resources
     * @return array
     */
    protected function prepareResourcesArg($resources)
    {
        // ensure that all specified Resources exist; normalize input to array of Resource objects or null
        if (!is_array($resources)) {
            if (null === $resources && count($this->resources) > 0) {
                $resources = array_keys($this->resources);
                // Passing a null resource; make sure "global" permission is also set!
                if (!in_array(null, $resources)) {
                    array_unshift($resources, null);
                }
            } else {
                $resources = array($resources);
            }
        } elseif (0 === count($resources)) {
            $resources = array(null);
        }
        $resources_temp = $resources;
        $resources = array();
        foreach ($resources_temp as $resource) {
            if (null !== $resource) {
                $resource_obj = $this->getResource($resource);
                $resource_id = $resource_obj->getResourceId();
                $children = $this->getChildResources($resource_obj);
                $resources = array_merge($resources, $children);
                $resources[$resource_id] = $resource_obj;
            } else {
                $resources[] = null;
            }
        }
        unset($resources_temp);

        return $resources;
    }

    /**
     * Prepare 'privileges' argument of setRule() method
     *
     * @param $privileges
     * @return array
     */
    protected function preparePrivilegesArg($privileges)
    {
        // normalize privileges to array
        if (null === $privileges) {
            $privileges = array();
        } elseif (!is_array($privileges)) {
            $privileges = array($privileges);
        }

        return $privileges;
    }

    /**
     * Rule setting 'add' operation
     *
     * @param array $rules
     * @param array $privileges
     * @param $type
     * @param $assert
     */
    protected function ruleOpAdd(array &$rules, array $privileges, $type, $assert)
    {
        if (0 === count($privileges))
        {
            $rules['allPrivileges']['type']   = $type;
            $rules['allPrivileges']['assert'] = $assert;

            if ( ! isset($rules['byPrivilegeId'])) {
                $rules['byPrivilegeId'] = array();
            }
        }
        else
        {
            foreach ($privileges as $privilege) {
                $rules['byPrivilegeId'][$privilege]['type']   = $type;
                $rules['byPrivilegeId'][$privilege]['assert'] = $assert;
            }
        }
    }

    protected function ruleOpRemove(array &$rules, $resource, $role, array $privileges, $type)
    {
        if (0 === count($privileges)) {
            if (null === $resource && null === $role) {
                if ($type === $rules['allPrivileges']['type']) {
                    $rules = array(
                        'allPrivileges' => array(
                            'type'   => self::TYPE_DENY,
                            'assert' => null
                        ),
                        'byPrivilegeId' => array()
                    );
                }
                return;
            }

            if (isset($rules['allPrivileges']['type']) &&
                $type === $rules['allPrivileges']['type'])
            {
                unset($rules['allPrivileges']);
            }
        } else {
            foreach ($privileges as $privilege) {
                if (isset($rules['byPrivilegeId'][$privilege]) &&
                    $type === $rules['byPrivilegeId'][$privilege]['type'])
                {
                    unset($rules['byPrivilegeId'][$privilege]);
                }
            }
        }
    }

    /**
     * Returns all child resources from the given resource.
     *
     * @param  Resource|string $resource
     *
     * @return array Array of 'Resource' objects
     */
    protected function getChildResources(Resource $resource)
    {
        $return = array();
        $resource_id = $resource->getResourceId();

        $children = $this->resources[$resource_id]['children'];
        foreach ($children as $child)
        {
            $child_return = $this->getChildResources($child);
            $child_return[$child->getResourceId()] = $child;
            $return = array_merge($return, $child_return);
        }

        return $return;
    }

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
     * @param  Role|string      $role
     * @param  Resource|string  $resource
     * @param  string           $privilege
     *
     * @return bool
     */
    public function isAllowed($role = null, $resource = null, $privilege = null)
    {
        // reset role & resource to null
        $this->is_allowed_role = null;
        $this->is_allowed_resource = null;
        $this->is_allowed_privilege = null;

        if (null !== $role) {
            // keep track of originally called role
            $this->is_allowed_role = $role;
            $role = $this->getRoleRegistry()->get($role);
            if (!$this->is_allowed_role instanceof Role) {
                $this->is_allowed_role = $role;
            }
        }

        if (null !== $resource) {
            // keep track of originally called resource
            $this->is_allowed_resource = $resource;
            $resource = $this->getResource($resource);
            if (!$this->is_allowed_resource instanceof Resource) {
                $this->is_allowed_resource = $resource;
            }
        }

        if (null === $privilege) {
            // query on all privileges
            do {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->roleDFSAllPrivileges($role, $resource, $privilege))) {
                    return $result;
                }

                // look for rule on 'allRoles' pseudo-parent
                if (null !== ($rules = $this->getRules($resource, null))) {
                    foreach ($rules['byPrivilegeId'] as $privilege => $rule) {
                        if (self::TYPE_DENY === ($rule_type_one_privilege = $this->getRuleType($resource, null, $privilege))) {
                            return false;
                        }
                    }
                    if (null !== ($rule_type_all_privileges = $this->getRuleType($resource, null, null))) {
                        return self::TYPE_ALLOW === $rule_type_all_privileges;
                    }
                }

                // try next Resource
                $resource = $this->resources[$resource->getResourceId()]['parent'];

            } while (true); // loop terminates at 'allResources' pseudo-parent
        } else {
            $this->is_allowed_privilege = $privilege;
            // query on one privilege
            do {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->roleDFSOnePrivilege($role, $resource, $privilege))) {
                    return $result;
                }

                // look for rule on 'allRoles' pseudo-parent
                if (null !== ($rule_type = $this->getRuleType($resource, null, $privilege))) {
                    return self::TYPE_ALLOW === $rule_type;
                } elseif (null !== ($rule_type_all_privileges = $this->getRuleType($resource, null, null))) {
                    $result = self::TYPE_ALLOW === $rule_type_all_privileges;
                    if ($result || null === $resource) {
                        return $result;
                    }
                }

                // try next Resource
                $resource = $this->resources[$resource->getResourceId()]['parent'];

            } while (true); // loop terminates at 'allResources' pseudo-parent
        }
    }

    /**
     * Returns the Role registry for this ACL
     *
     * If no Role registry has been created yet, a new default Role registry
     * is created and returned.
     *
     * @return RoleRegistry
     */
    protected function getRoleRegistry()
    {
        if (null === $this->role_registry) {
            $this->role_registry = new RoleRegistry();
        }
        return $this->role_registry;
    }

    /**
     * Performs a depth-first search of the Role DAG, starting at $role, in order to find a rule
     * allowing/denying $role access to all privileges upon $resource
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * @param  Role      $role
     * @param  Resource  $resource
     *
     * @return bool|null
     */
    protected function roleDFSAllPrivileges(Role $role, Resource $resource = null)
    {
        $dfs = array(
            'visited' => array(),
            'stack'   => array()
        );

        if (null !== ($result = $this->roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
            return $result;
        }

        // This comment is needed due to a strange php-cs-fixer bug
        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Visits an $role in order to look for a rule allowing/denying $role access to all privileges upon $resource
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * This method is used by the internal depth-first search algorithm and may modify the DFS data structure.
     *
     * @param  Role      $role
     * @param  Resource  $resource
     * @param  array     $dfs
     *
     * @throws Exception\Runtime
     *
     * @return bool|null
     */
    protected function roleDFSVisitAllPrivileges(Role $role, Resource $resource = null, &$dfs = null)
    {
        if (null === $dfs) {
            throw new Exception\Runtime('$dfs parameter may not be null');
        }

        if (null !== ($rules = $this->getRules($resource, $role))) {
            foreach ($rules['byPrivilegeId'] as $privilege => $rule) {
                if (self::TYPE_DENY === ($rule_type_one_privilege = $this->getRuleType($resource, $role, $privilege))) {
                    return false;
                }
            }
            if (null !== ($rule_type_all_privileges = $this->getRuleType($resource, $role, null))) {
                return self::TYPE_ALLOW === $rule_type_all_privileges;
            }
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->getRoleRegistry()->getParents($role) as $role_parent) {
            $dfs['stack'][] = $role_parent;
        }

        return null;
    }

    /**
     * Performs a depth-first search of the Role DAG, starting at $role, in order to find a rule
     * allowing/denying $role access to a $privilege upon $resource
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * @param  Role      $role
     * @param  Resource  $resource
     * @param  string    $privilege
     * @throws Exception\Runtime
     * @return bool|null
     */
    protected function roleDFSOnePrivilege(Role $role, Resource $resource = null, $privilege = null)
    {
        if (null === $privilege) {
            throw new Exception\Runtime('$privilege parameter may not be null');
        }

        $dfs = array(
            'visited' => array(),
            'stack'   => array()
        );

        if (null !== ($result = $this->roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
            return $result;
        }

        // This comment is needed due to a strange php-cs-fixer bug
        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Visits an $role in order to look for a rule allowing/denying $role access to a $privilege upon $resource
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * This method is used by the internal depth-first search algorithm and may modify the DFS data structure.
     *
     * @param  Role      $role
     * @param  Resource  $resource
     * @param  string    $privilege
     * @param  array     $dfs
     *
     * @throws Exception\Runtime
     *
     * @return bool|null
     */
    protected function roleDFSVisitOnePrivilege(Role $role, Resource $resource = null, $privilege = null, &$dfs = null)
    {
        if (null === $privilege) {
            throw new Exception\Runtime('$privilege parameter may not be null');
        }

        if (null === $dfs) {
            throw new Exception\Runtime('$dfs parameter may not be null');
        }

        if (null !== ($rule_type_one_privilege = $this->getRuleType($resource, $role, $privilege))) {
            return self::TYPE_ALLOW === $rule_type_one_privilege;
        } elseif (null !== ($rule_type_all_privileges = $this->getRuleType($resource, $role, null))) {
            return self::TYPE_ALLOW === $rule_type_all_privileges;
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->getRoleRegistry()->getParents($role) as $role_parent) {
            $dfs['stack'][] = $role_parent;
        }

        return null;
    }

    /**
     * Returns the rule type associated with the specified Resource, Role, and privilege
     * combination.
     *
     * If a rule does not exist or its attached assertion fails, which means that
     * the rule is not applicable, then this method returns null. Otherwise, the
     * rule type applies and is returned as either TYPE_ALLOW or TYPE_DENY.
     *
     * If $resource or $role is null, then this means that the rule must apply to
     * all Resources or Roles, respectively.
     *
     * If $privilege is null, then the rule must apply to all privileges.
     *
     * If all three parameters are null, then the default ACL rule type is returned,
     * based on whether its assertion method passes.
     *
     * @param  null|Resource  $resource
     * @param  null|Role      $role
     * @param  null|string    $privilege
     *
     * @return string|null
     */
    protected function getRuleType(Resource $resource = null, Role $role = null, $privilege = null)
    {
        // get the rules for the $resource and $role
        if (null === ($rules = $this->getRules($resource, $role))) {
            return null;
        }

        // follow $privilege
        if (null === $privilege) {
            if (isset($rules['allPrivileges'])) {
                $rule = $rules['allPrivileges'];
            } else {
                return null;
            }
        } elseif (!isset($rules['byPrivilegeId'][$privilege])) {
            return null;
        } else {
            $rule = $rules['byPrivilegeId'][$privilege];
        }

        // check assertion first
        if ($rule['assert']) {
            $assertion = $rule['assert'];
            $assertion_value = $assertion->assert(
                $this,
                ($this->is_allowed_role instanceof Role) ? $this->is_allowed_role : $role,
                ($this->is_allowed_resource instanceof Resource) ? $this->is_allowed_resource : $resource,
                $this->is_allowed_privilege
            );
        }

        if (null === $rule['assert'] || $assertion_value) {
            return $rule['type'];
        } elseif (null !== $resource || null !== $role || null !== $privilege) {
            return null;
        } elseif (self::TYPE_ALLOW === $rule['type']) {
            return self::TYPE_DENY;
        }

        return self::TYPE_ALLOW;
    }

    /**
     * Returns the rules associated with a Resource and a Role, or null if no such rules exist
     *
     * If either $resource or $role is null, this means that the rules returned are for all Resources or all Roles,
     * respectively. Both can be null to return the default rule set for all Resources and all Roles.
     *
     * If the $create parameter is true, then a rule set is first created and then returned to the caller.
     *
     * @param  Resource  $resource
     * @param  Role      $role
     * @param  bool      $create
     *
     * @return array|null
     */
    protected function &getRules(Resource $resource = null, Role $role = null, $create = false)
    {
        // create a reference to null
        $null = null;
        $null_ref =& $null;

        // follow $resource
        do {
            if (null === $resource) {
                $visitor =& $this->rules['allResources'];
                break;
            }
            $resource_id = $resource->getResourceId();
            if (!isset($this->rules['byResourceId'][$resource_id])) {
                if ( ! $create) {
                    return $null_ref;
                }
                $this->rules['byResourceId'][$resource_id] = array();
            }
            $visitor =& $this->rules['byResourceId'][$resource_id];
        } while (false);


        // follow $role
        if (null === $role) {
            if (!isset($visitor['allRoles'])) {
                if ( ! $create) {
                    return $null_ref;
                }
                $visitor['allRoles']['byPrivilegeId'] = array();
            }
            return $visitor['allRoles'];
        }
        $roleId = $role->getRoleId();
        if (!isset($visitor['byRoleId'][$roleId])) {
            if ( ! $create) {
                return $null_ref;
            }
            $visitor['byRoleId'][$roleId]['byPrivilegeId'] = array();
        }
        return $visitor['byRoleId'][$roleId];
    }

    /**
     * @return array of registered roles
     */
    public function getRoles()
    {
        return array_keys($this->getRoleRegistry()->getRoles());
    }

    /**
     * @return array of registered resources
     */
    public function getResources()
    {
        return array_keys($this->resources);
    }
}
