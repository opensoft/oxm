<?php

namespace Doctrine\Tests\OXM\Types;

use \Doctrine\OXM\Types\Type;

class TypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Doctrine\OXM\OXMException
     */
    public function testGetUnknownType()
    {
       Type::getType('foo');
    }

    /**
     * @expectedException Doctrine\OXM\OXMException
     */
    public function testAddTypeExists()
    {
        Type::addType('string', 'Doctrine\\Tests\\Mocks\\TypeMock');
    }
    
    public function testAddType()
    {
        Type::addType('mock', 'Doctrine\\Tests\\Mocks\\TypeMock');
        $this->assertTrue(Type::hasType('mock'));
        $this->assertEquals('mock', Type::getType('mock')->getName());
    }

    /**
     * @expectedException Doctrine\OXM\OXMException
     */
    public function testOverrideTypeNotFound()
    {
        Type::overrideType('foo', 'Doctrine\\Tests\\Mocks\\TypeMock');
    }

    public function testOverrideType()
    {
        Type::overrideType('string', 'Doctrine\\Tests\\Mocks\\TypeMock');
    }
}