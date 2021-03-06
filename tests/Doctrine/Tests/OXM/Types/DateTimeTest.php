<?php

namespace Doctrine\Tests\OXM\Types;

use Doctrine\OXM\Types\Type,
    Doctrine\OXM\Types\DateTimeType;

class DateTimeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\OXM\Types\DateTimeType
     */
    protected $_type;

    protected function setUp()
    {
        $this->_type = Type::getType('datetime');
    }

    public function testName()
    {
        $this->assertEquals('datetime', $this->_type->getName());
    }

    public function testDateTimeConvertsToXmlValue()
    {
        $date = new \DateTime('1985-09-01 10:10:10');

        $expected = DateTimeType::FORMAT;
        $actual = is_string($this->_type->convertToXmlValue($date));

        $this->assertEquals($expected, $actual);
    }

    public function testDateTimeConvertsToPHPValue()
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        $date = $this->_type->convertToPHPValue('1985-09-01 00:00:00');
        $this->assertInstanceOf('\DateTime', $date);
        $this->assertEquals('1985-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testInvalidDateTimeFormatConversion()
    {
        $this->setExpectedException('Doctrine\OXM\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg');
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null));
    }
}