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

use Doctrine\Tests\OxmTestCase,
    Doctrine\Tests\OXM\Entities\Collections\CollectionClass,
    Doctrine\Tests\OXM\Entities\Collections\CollectionAttributeClass,
    Doctrine\Tests\OXM\Entities\Collections\Wrapper;

class CollectionsTest extends OxmTestCase
{
    /** @var \Doctrine\OXM\Marshaller\XmlMarshaller */
    private $marshaller;

    public function setUp()
    {
        $this->marshaller = $this->_getMarshaller("tests/Doctrine/Tests/OXM/Entities");
    }

    /**
     * @test
     */
    public function itShouldHandleXmlTextCollectionsProperly()
    {
        $request = new CollectionClass();
        $request->list = array('one', 'two', 'three');

        $xml = $this->marshaller->marshalToString($request);

        $this->assertXmlStringEqualsXmlString('<collection-class repositoryBy="0">
            <list>one</list>
            <list>two</list>
            <list>three</list>
        </collection-class>', $xml);

        $otherRequest = $this->marshaller->unmarshalFromString($xml);

        $this->assertEquals(3, count($otherRequest->list));
        $this->assertContains('one', $otherRequest->list);
        $this->assertContains('two', $otherRequest->list);
        $this->assertContains('three', $otherRequest->list);
    }

    /**
     * @test
     */
    public function itShouldHandleXmlAttributeCollectionsProperly()
    {
        $colorContainer = new CollectionAttributeClass();
        $colorContainer->colors = array('red', 'green', 'blue');

        $xml = $this->marshaller->marshalToString($colorContainer);

        $this->assertXmlStringEqualsXmlString('<collection-attribute-class repositoryBy="0" colors="red green blue" />', $xml);

        $otherContainer = $this->marshaller->unmarshalFromString($xml);

        $this->assertEquals(3, count($otherContainer->colors));
        $this->assertContains('red', $otherContainer->colors);
        $this->assertContains('green', $otherContainer->colors);
        $this->assertContains('blue', $otherContainer->colors);
    }

    /**
     * @test
     */
    public function collectionWrapsXmlText()
    {
        $wrapper = new Wrapper();
        $wrapper->list = array('red', 'green', 'blue');
        $wrapper->enum = array('one', 'two', 'three', 'four');

        $xml = $this->marshaller->marshalToString($wrapper);

        $this->assertXmlStringEqualsXmlString('<wrapper xmlns:prfx="http://www.foo.bar.baz.com/schema" repositoryBy="0">
            <foo>
                <list>red</list>
                <list>green</list>
                <list>blue</list>
            </foo>
            <prfx:bar>
                <prfx:enum>one</prfx:enum>
                <prfx:enum>two</prfx:enum>
                <prfx:enum>three</prfx:enum>
                <prfx:enum>four</prfx:enum>
            </prfx:bar>
        </wrapper>', $xml);

        $otherWrapper = $this->marshaller->unmarshalFromString($xml);

        $this->assertEquals(3, count($otherWrapper->list));
        $this->assertContains('red', $otherWrapper->list);
        $this->assertContains('green', $otherWrapper->list);
        $this->assertContains('blue', $otherWrapper->list);

        $this->assertEquals(4, count($otherWrapper->enum));
        $this->assertContains('one', $otherWrapper->enum);
        $this->assertContains('two', $otherWrapper->enum);
        $this->assertContains('three', $otherWrapper->enum);
        $this->assertContains('four', $otherWrapper->enum);
    }
}
