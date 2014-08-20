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

use Aura\Acl\Assertion\AssertionInterface;
use Aura\Acl as AuraAcl;

class UserIsBlogPostOwnerAssertion implements AssertionInterface
{

	public $lastAssertRole = null;
	public $lastAssertResource = null;
	public $lastAssertPrivilege = null;
	public $assertReturnValue = true;

	public function assert(AuraAcl\Acl $acl, AuraAcl\Role $user = null, AuraAcl\Resource $blogPost = null,
						   $privilege = null)
	{
		$this->lastAssertRole      = $user;
		$this->lastAssertResource  = $blogPost;
		$this->lastAssertPrivilege = $privilege;
		return $this->assertReturnValue;
	}
}