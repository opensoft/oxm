<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Tests\OXM\Marshaller;

use \Doctrine\OXM\Mapping\ClassMetadataFactory,
    \Doctrine\OXM\Configuration,
    \Doctrine\OXM\Marshaller\Marshaller,
    \Doctrine\OXM\Marshaller\XmlMarshaller,
    \Doctrine\OXM\Mapping\Driver\AnnotationDriver,
    \Doctrine\Tests\OXM\Entities\User,
    \Doctrine\Tests\OXM\Entities\Simple\Simple,
    \Doctrine\Tests\OXM\Entities\Simple\SimpleChild,
    \Doctrine\Tests\OXM\Entities\Simple\SimpleChildExtendsWithChildField,
    \Doctrine\Tests\OXM\Entities\Simple\SimpleChildExtendsWithParentField,
    \Doctrine\Tests\OXM\Entities\Simple\SimpleWithField,
    \Doctrine\Tests\OXM\Entities\Simple\SimpleCompound,
    \Doctrine\Tests\OXM\Entities\Order,
    \Doctrine\Tests\OXM\Entities\CustomerContact,
    \Doctrine\Tests\OXM\Entities\Address;

/**
 * @ErrorHandlerSettings false
 */
class MarshallerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\OXM\Marshaller\XmlMarshaller
     */
    private $marshaller;

    /**
     * @var \Doctrine\OXM\Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    public function setUp()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver("tests/Doctrine/Tests/OXM/Entities"));
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        $this->metadataFactory = new ClassMetadataFactory($config);

        $this->marshaller = new XmlMarshaller($this->metadataFactory);
    }

    public function tearDown()
    {
        error_reporting(-1); // reactive all error levels
    }


    public function testFirstClassMarshaller()
    {
        $user = new User();
        $user->setFirstNameNickname('Malcolm');
        $user->setLastName('Reynolds');
        $user->setAddress(new Address('123 Waverly Way', 'New Haven', 'Insanity'));
        $user->addContact(new CustomerContact('no@way.com'));
        $user->addContact(new CustomerContact('other@way.com'));

        $xml = $this->marshaller->marshalToString($user);

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
//        print_r($dom->saveXML());

        $otherUser = $this->marshaller->unmarshalFromString($xml);


//        print_r($otherUser);

        $this->assertInstanceOf('Doctrine\Tests\OXM\Entities\User', $otherUser);

        $this->assertEquals('Malcolm', $otherUser->getFirstNameNickname());
        $this->assertEquals('Reynolds', $otherUser->getLastName());

        $this->assertEquals('123 Waverly Way', $otherUser->getAddress()->getStreet());
        $this->assertEquals('New Haven', $otherUser->getAddress()->getCity());
        $this->assertEquals('Insanity', $otherUser->getAddress()->getState());

        $this->assertEquals(2, count($otherUser->getContacts()));
    }

    public function testItShouldAutocompleteFields()
    {
        $order = new Order(1, 'business cards', new \DateTime());

        $xml = $this->marshaller->marshalToString($order);

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
//        print_r($dom->saveXML());

        $this->assertTrue(strlen($xml) > 0);

        $otherOrder = $this->marshaller->unmarshalFromString($xml);
//        print_r($otherOrder);

        $this->assertEquals(1, $otherOrder->getId());
        $this->assertEquals('business cards', $otherOrder->getProductType());
    }

    /**
     * @test
     */
    public function itShouldProduceExactXml()
    {
        $simple = new Simple();
        $xml = $this->marshaller->marshalToString($simple);
        
        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple/>', $xml);
    }

    /**
     * @test
     */
    public function itShouldProduceExactXmlForCompoundClassName()
    {
        $simple = new SimpleCompound();
        $xml = $this->marshaller->marshalToString($simple);

        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple-compound/>', $xml);
    }


    /**
     * @test
     */
    public function itShouldProduceExactXmlForAttribute()
    {
        $simple = new SimpleWithField();
        $xml = $this->marshaller->marshalToString($simple);

        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple-with-field/>', $xml);
    }

    /**
     * @test
     */
    public function itShouldMarshalToFilenameStream()
    {
        $simple = new SimpleWithField();
        $xml = $this->marshaller->marshalToStream($simple, "file://" . realpath(__DIR__) . "/../Workspace/Foo.xml");

        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlFile(realpath(__DIR__) . "/../Workspace/Foo.xml", '<?xml version="1.0" encoding="UTF-8"?><simple-with-field/>');

        @unlink(realpath(__DIR__) . "/../Workspace/Foo.xml");
    }



    /**
     * @test
     */
    public function itShouldProduceExactXmlForPopulatedAttribute()
    {
        $simple = new SimpleWithField();
        $simple->id = 1;
        $xml = $this->marshaller->marshalToString($simple);

        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple-with-field id="1"/>', $xml);
    }


    /**
     * @test
     */
    public function itShouldHandleAllValidXml()
    {
        $simple = $this->marshaller->unmarshalFromString('<?xml version="1.0" encoding="UTF-8"?><simple-with-field id="1"/>');
        $this->assertEquals(1, $simple->id);

        $simple = $this->marshaller->unmarshalFromString(' <?xml version="1.0" encoding="UTF-8"?><simple-with-field id="1"/>');
        $this->assertEquals(1, $simple->id);

        $simple = $this->marshaller->unmarshalFromString(' <?xml version="1.0" encoding="UTF-8"?><simple-with-field

        id="1"/>');
        $this->assertEquals(1, $simple->id);

        $simple = $this->marshaller->unmarshalFromString(' <?xml version="1.0" encoding="UTF-8"?>
        <!-- Comment -->
        <simple-with-field id="1"/><!-- comment2 -->');
        $this->assertEquals(1, $simple->id);
    }

    /**
     * @test
     */
    public function itShouldHandleMappedSuperclassesCorrectly()
    {
        $simple = new SimpleChild();
        $xml = $this->marshaller->marshalToString($simple);
        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple-child><other>yes</other></simple-child>', $xml);


        $simple = new SimpleChildExtendsWithChildField();
        $simple->id = 1;
        $simple->other = "no";
        $xml = $this->marshaller->marshalToString($simple);
        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?>
            <simple-child-extends-with-child-field id="1">
                <other>no</other>
            </simple-child-extends-with-child-field>', $xml);

        $simple = new SimpleChildExtendsWithParentField();
        $xml = $this->marshaller->marshalToString($simple);
        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="UTF-8"?><simple-child-extends-with-parent-field id="2"/>', $xml);
    }

    /**
     * @test
     */
    public function itShouldSupportMarshallingToOtherEncodings()
    {
        $simple = new SimpleChild();
        $this->marshaller->setEncoding('ISO-8859-1');
        $xml = $this->marshaller->marshalToString($simple);
        $this->assertTrue(strlen($xml) > 0);
        $this->assertXmlStringEqualsXmlString('<?xml version="1.0" encoding="ISO-8859-1"?><simple-child><other>yes</other></simple-child>', $xml);

        $obj = $this->marshaller->unmarshalFromString($xml);
        $this->assertEquals('yes', $obj->other);
    }
}
