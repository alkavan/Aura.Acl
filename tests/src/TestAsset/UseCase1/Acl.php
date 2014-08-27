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

class Acl extends \Aura\Acl\Acl
{
    public $customAssertion = null;

    public function __construct()
    {
        $this->customAssertion = new UserIsBlogPostOwnerAssertion();

        $this->addRole(new \Aura\Acl\Role('guest'));                    // $acl->addRole('guest');
        $this->addRole(new \Aura\Acl\Role('contributor'), 'guest');     // $acl->addRole('contributor', 'guest');
        $this->addRole(new \Aura\Acl\Role('publisher'), 'contributor'); // $acl->addRole('publisher', 'contributor');
        $this->addRole(new \Aura\Acl\Role('admin'));                    // $acl->addRole('admin');
        $this->addResource(new \Aura\Acl\Resource('blogPost'));         // $acl->addResource('blogPost');
        $this->allow('guest', 'blogPost', 'view');
        $this->allow('contributor', 'blogPost', 'contribute');
        $this->allow('contributor', 'blogPost', 'modify', $this->customAssertion);
        $this->allow('publisher', 'blogPost', 'publish');
    }
}