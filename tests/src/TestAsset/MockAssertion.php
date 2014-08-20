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

namespace Aura\Acl\TestAsset;

use Aura\Acl\Acl;
use Aura\Acl\Role;
use Aura\Acl\Resource;
use Aura\Acl\Assertion;


class MockAssertion implements Assertion\AssertionInterface
{
	protected $_returnValue;

	public function __construct($returnValue)
	{
		$this->_returnValue = (bool) $returnValue;
	}

	public function assert(Acl $acl, Role $role = null, Resource $resource = null, $privilege = null)
	{
		return $this->_returnValue;
	}
}