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
use Aura\Acl\Role;
use Aura\Acl\Resource;
use Aura\Acl\Exception\InvalidArgument;
use Aura\Acl\Exception\Runtime;

class Aggregate implements AssertionInterface
{
	const MODE_ALL = 'all';

	const MODE_AT_LEAST_ONE = 'at_least_one';

	protected $assertions = array();

	protected $mode = self::MODE_ALL;

	/**
	 * Stacks an assertion in aggregate
	 *
	 * @param AssertionInterface|string $assertion
	 *            if string, must match a AssertionManager declared service (checked later)
	 *
	 * @return self
	 */
	public function addAssertion($assertion)
	{
		$this->assertions[] = $assertion;

		return $this;
	}

	/**
	 * Adds array of assertions
	 * @param array $assertions
	 * @return $this
	 */
	public function addAssertions(array $assertions)
	{
		foreach ($assertions as $assertion) {
			$this->addAssertion($assertion);
		}

		return $this;
	}

	/**
	 * Empties assertions stack
	 *
	 * @return self
	 */
	public function clearAssertions()
	{
		$this->assertions = array();

		return $this;
	}

	/**
	 * Set assertion chain behavior
	 *
	 * AssertionAggregate should assert to true when:
	 *
	 * - all assertions are true with MODE_ALL
	 * - at least one assertion is true with MODE_AT_LEAST_ONE
	 *
	 * @param string $mode
	 *            indicates how assertion chain result should interpreted (either 'all' or 'at_least_one')
	 * @throws InvalidArgument
	 *
	 * @return self
	 */
	public function setMode($mode)
	{
		if ($mode != self::MODE_ALL && $mode != self::MODE_AT_LEAST_ONE) {
			throw new InvalidArgument('invalid assertion aggregate mode');
		}

		$this->mode = $mode;

		return $this;
	}

	/**
	 * Return current mode
	 *
	 * @return string
	 */
	public function getMode()
	{
		return $this->mode;
	}

	/**
	 * Assert resource-role
	 *
	 * @param Acl $acl
	 * @param Role $role
	 * @param Resource $resource
	 * @param null $privilege
	 * @return bool
	 * @throws \Aura\Acl\Exception\Runtime
	 */
	public function assert(Acl $acl, Role $role = null, Resource $resource = null, $privilege = null)
	{
		// check if assertions are set
		if (! $this->assertions) {
			throw new Runtime('no assertion have been aggregated to this AssertionAggregate');
		}

		foreach ($this->assertions as $assertion) {

			// jit assertion loading
			if (! $assertion instanceof AssertionInterface) {
				if (class_exists($assertion)) {
					$assertion = new $assertion();
				}
			}

			$result = (bool) $assertion->assert($acl, $role, $resource, $privilege);

			if ($this->getMode() == self::MODE_ALL && ! $result) {
				// on false is enough
				return false;
			}

			if ($this->getMode() == self::MODE_AT_LEAST_ONE && $result) {
				// one true is enough
				return true;
			}
		}

		if ($this->getMode() == self::MODE_ALL) {
			// none of the assertions returned false
			return true;
		} else {
			return false;
		}
	}
}
