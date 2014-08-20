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

namespace Aura\Acl\TestAsset\UseCase1;

use Aura\Acl\Role;

class User extends Role
{
	public $role = 'guest';

	public function __construct($roleId)
	{
		$this->role = (string) $roleId;
	}

	public function getRoleId()
	{
		return $this->role;
	}
}
