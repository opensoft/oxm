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

namespace Doctrine\OXM\Marshaller;

use Doctrine\OXM\Mapping\ClassMetadata;
use Doctrine\OXM\Mapping\ClassMetadataFactory;
use Doctrine\OXM\Mapping\MappingException;
use Doctrine\OXM\Marshaller\Helper\ReaderHelper;
use Doctrine\OXM\Marshaller\Helper\WriterHelper;
use Doctrine\OXM\Types\Type;
use Doctrine\OXM\Events;
use Doctrine\Common\Collections\ArrayCollection;
    
use XMLReader, XMLWriter;
    
/**
 * A marshaller class which uses Xml Writer and Xml Reader php libraries.
 *
 * Requires --enable-xmlreader and --enable-xmlwriter (default in most PHP
 * installations)
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class XmlMarshaller implements Marshaller
{

    /**
     * Mapping data for all known XmlEntity classes
     *
     * @var \Doctrine\OXM\Mapping\ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * Support for indentation during marshalling
     *
     * @var int
     */
    private $indent = 4;

    /**
     * Xml Character Encoding
     *
     * @var string
     */
    private $encoding = 'UTF-8';

    /**
     * Xml Schema Version
     *
     * @var string
     */
    private $schemaVersion = '1.0';

    /**
     * Repository for object
     * @var \Doctrine\Common\Collections\ArrayCollection $repository
     */
    protected $repository;

    const DOCTRINE_PROXY_INTERFACE = 'Doctrine\ORM\Proxy\Proxy';

    /**
     * @param ClassMetadataFactory
     */
    public function __construct(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->repository = new ArrayCollection();
    }

    /**
     * @param Doctrine\OXM\Mapping\ClassMetadataFactory
     */
    public function setClassMetadataFactory(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * @return Doctrine\OXM\Mapping\ClassMetadataFactory
     */
    public function getClassMetadataFactory()
    {
        return $this->classMetadataFactory;
    }

    /**
     * Set the marshallers output indentation level.  Zero for no indentation.
     *
     * @param int $indent
     */
    public function setIndent($indent)
    {
        $this->indent = (int) $indent;
    }

    /**
     * Return the indentation level.  Zero for no indentation.
     *
     * @return int
     */
    public function getIndent()
    {
        return $this->indent;
    }

    /**
     * @param string $encoding
     * @return void
     * 
     * @todo check for valid encoding from http://www.w3.org/TR/REC-xml/#charencoding
     */
    public function setEncoding($encoding)
    {
        $this->encoding = strtoupper($encoding);
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param string $schemaVersion
     * @return void
     */
    public function setSchemaVersion($schemaVersion)
    {
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * @return string
     */
    public function getSchemaVersion()
    {
        return $this->schemaVersion;
    }

    /**
     * @param string $streamUri
     * @return object
     */
    public function unmarshalFromStream($streamUri)
    {
        $reader = new XMLReader();

        if (!$reader->open($streamUri)) {
            throw MarshallerException::couldNotOpenStream($streamUri);
        }

        // Position at first detected element
        while ($reader->read() && $reader->nodeType !== XMLReader::ELEMENT);

        $mappedObject = $this->doUnmarshal($reader);
        $reader->close();
        $this->repository->clear();

        return $mappedObject;
    }

    /**
     * @param string $xml
     * @return object
     */
    function unmarshalFromString($xml)
    {
        $xml = trim((string) $xml);

        $reader = new XMLReader();

        if (!$reader->XML($xml)) {
            throw MarshallerException::couldNotReadXml($xml);
        }

        // Position at first detected element
        while ($reader->read() && $reader->nodeType !== XMLReader::ELEMENT);

        $mappedObject = $this->doUnmarshal($reader);
        $reader->close();
        $this->repository->clear();

        return $mappedObject;
    }

    /**
     *
     * INTERNAL: Performance sensitive method
     *
     * @throws \Doctrine\OXM\Mapping\MappingException
     * @param \XMLReader $cursor
     * @return object
     */
    private function doUnmarshal(XMLReader $cursor)
    {
        $allMappedXmlNodes = $this->classMetadataFactory->getAllXmlNodes();
        $knownMappedNodes = array_keys($allMappedXmlNodes);

        if ($cursor->nodeType !== XMLReader::ELEMENT) {
            throw MarshallerException::invalidMarshallerState($cursor);

        }

        $elementName = $cursor->localName;

        if (!in_array($elementName, $knownMappedNodes)) {
            throw MappingException::invalidMapping($elementName);
        }
        $classMetadata = $this->classMetadataFactory->getMetadataFor($allMappedXmlNodes[$elementName]);

        $mappedObject = $this->repository->get($cursor->getAttribute('repositoryBy'));
        if ($mappedObject !== null) {
            return $mappedObject;
        } else {
            $mappedObject = $classMetadata->newInstance();
            $this->repository->set((int)$cursor->getAttribute('repositoryBy'), $mappedObject);
        }

        // Pre Unmarshal hook
        if ($classMetadata->hasLifecycleCallbacks(Events::preUnmarshal)) {
            $classMetadata->invokeLifecycleCallbacks(Events::preUnmarshal, $mappedObject);
        }

        if ($cursor->hasAttributes) {
            while ($cursor->moveToNextAttribute()) {
                if ($classMetadata->hasXmlField($cursor->name)) {
                    $fieldName = $classMetadata->getFieldName($cursor->name);
                    $fieldMapping = $classMetadata->getFieldMapping($fieldName);
                    $type = Type::getType($fieldMapping['type']);

                    if ($classMetadata->isRequired($fieldName) && $cursor->value == null) {
                        throw MappingException::fieldRequired($classMetadata->name, $fieldName);
                    }

                    if ($classMetadata->isCollection($fieldName)) {
                        $convertedValues = new ArrayCollection();
                        if ($cursor->value != '') {
                            foreach (explode(" ", $cursor->value) as $value) {
                                $value = preg_replace('/(\w+):/', '', $value, 1);
                                $convertedValues->add($type->convertToPHPValue($value));
                            }
                        }
                        $classMetadata->setFieldValue($mappedObject, $fieldName, $convertedValues);
                    } else {
                        if ($classMetadata->isNillable($fieldName) && $cursor->value == null) {
                            $cursorValue = null;
                        } else {
                            $cursorValue = $cursor->value;
                        }
                        $classMetadata->setFieldValue($mappedObject, $fieldName, $type->convertToPHPValue($cursorValue));
                    }
                }
            }
            $cursor->moveToElement();
        }

        if (!$cursor->isEmptyElement) {
            $collectionElements = array();

            while (true) {
                $cursor->read();
                if ($cursor->nodeType === XMLReader::END_ELEMENT && $cursor->name === $elementName) {
                    // we're at the original element closing node, bug out
                    break;
                }

                if ($cursor->nodeType !== XMLReader::ELEMENT) {
                    // skip insignificant elements
                    continue;
                }

                if ($classMetadata->hasXmlField($cursor->localName)) {
                    $fieldName = $classMetadata->getFieldName($cursor->localName);

                    // Check for mapped entity as child, add recursively
                    $fieldMapping = $classMetadata->getFieldMapping($fieldName);

                    if ($this->classMetadataFactory->hasMetadataFor($fieldMapping['type'])) {

                        if ($classMetadata->isCollection($fieldName)) {
                            if (!isset($collectionElements[$fieldName])) {
                                $collectionElements[$fieldName] = new ArrayCollection();
                            }
                            if (!$cursor->isEmptyElement || $cursor->hasAttributes) {
                                $collectionElements[$fieldName]->add($this->doUnmarshal($cursor));
                            }
                        } else {
                            $classMetadata->setFieldValue($mappedObject, $fieldName, $this->doUnmarshal($cursor));
                        }
                    } else {
                        // assume text element (dangerous?)
                        if (!$cursor->isEmptyElement) {
                            $cursor->read();
                            /**
                             * @author Dmitry Petrov <dmitry.petrov@opensoftdev.ru>
                             * This condition for <emptyElement></emptyElement>
                             */
                            if ($cursor->nodeType === XMLReader::END_ELEMENT) {
                                $cursorValue = '';
                            } else {
                                $cursorValue = $cursor->value;
                                if ($cursor->nodeType !== XMLReader::TEXT) {
                                    throw MarshallerException::invalidMarshallerState($cursor);
                                }
                            }
                        } else {
                            $cursorValue = null;//<emptyElement/> is equal null;
                        }

                        $type = Type::getType($fieldMapping['type']);
                        if ($classMetadata->isCollection($fieldName)) {
                            if (!isset($collectionElements[$fieldName])) {
                                $collectionElements[$fieldName] = new ArrayCollection();
                            }
                            if (!$cursor->isEmptyElement || $cursor->hasAttributes) {
                                $collectionElements[$fieldName]->add($type->convertToPHPValue($cursorValue));
                            }
                        } else {
                            $classMetadata->setFieldValue($mappedObject, $fieldName, $type->convertToPHPValue($cursorValue));
                        }

                        if ($cursor->isEmptyElement || $cursor->nodeType === XMLReader::TEXT) {
                            $cursor->read();
                        }
                    }
                } elseif (in_array($cursor->name, $knownMappedNodes)) {  // look for inherited child directly
                    $childClassMetadata = $this->classMetadataFactory->getMetadataFor($allMappedXmlNodes[$cursor->name]);

                    // todo: ensure this potential child inherits from parent correctly

                    $fieldName = null;
                    foreach ($classMetadata->getFieldMappings() as $fieldMapping) {
                        if ($fieldMapping['type'] == $allMappedXmlNodes[$cursor->name]) {
                            $fieldName = $fieldMapping['fieldName'];
                        } else {
                            // Walk parent tree
                            foreach ($childClassMetadata->getParentClasses() as $parentClass) {
                                if ($fieldMapping['type'] == $parentClass) {
                                    $fieldName = $fieldMapping['fieldName'];
                                }
                            }
                        }
                    }

                    if ($fieldName !== null) {
                        if ($classMetadata->isCollection($fieldName)) {
                            if (!isset($collectionElements[$fieldName])) {
                                $collectionElements[$fieldName] = new ArrayCollection();
                            }
                            if (!$cursor->isEmptyElement || $cursor->hasAttributes) {
                                $collectionElements[$fieldName]->add($this->doUnmarshal($cursor));
                            }
                        } else {
                            $classMetadata->setFieldValue($mappedObject, $fieldName, $this->doUnmarshal($cursor));
                        }
                    }
                }
            }

            if (!empty($collectionElements)) {
                foreach ($collectionElements as $fieldName => $elements) {
                    $classMetadata->setFieldValue($mappedObject, $fieldName, $elements);
                }
            }
        }

        // PostUnmarshall hook
        if ($classMetadata->hasLifecycleCallbacks(Events::postUnmarshal)) {
            $classMetadata->invokeLifecycleCallbacks(Events::postUnmarshal, $mappedObject);
        }

        return $mappedObject;
    }

    /**
     * @param object $mappedObject
     * @return string
     */
    function marshalToString($mappedObject)
    {
        $writer = new WriterHelper($this);

        // Begin marshalling
        $this->doMarshal($mappedObject, $writer);
        $this->repository->clear();

        return $writer->flush();
    }


    /**
     * @param object $mappedObject
     * @param string $streamUri
     * @return bool|int
     */
    public function marshalToStream($mappedObject, $streamUri)
    {
        $writer = new WriterHelper($this, $streamUri);

        // Begin marshalling
        $this->doMarshal($mappedObject, $writer);
        $this->repository->clear();

        return $writer->flush();
    }

    /**
     * @param object $mappedObject
     * @return string|false
     */
    private function getClassNameForMetadata($mappedObject)
    {
        if (in_array(self::DOCTRINE_PROXY_INTERFACE, class_implements($mappedObject))) {
            $parentClasses = class_parents($mappedObject);
            if ($parentClasses !== false) {
                return current($parentClasses);
            }
            return false;
        } else {
            return get_class($mappedObject);
        }
    }

    /**
     * INTERNAL: Performance sensitive method
     *
     * @throws MarshallerException
     * @param object $mappedObject
     * @param WriterHelper $writer
     * @return false|void
     */
    private function doMarshal($mappedObject, WriterHelper $writer)
    {
        $className = $this->getClassNameForMetadata($mappedObject);
        $classMetadata = $this->classMetadataFactory->getMetadataFor($className);


        if (!$this->classMetadataFactory->hasMetadataFor($className)) {
            throw MarshallerException::mappingNotFoundForClass($className);
        }

        // PreMarshall Hook
        if ($classMetadata->hasLifecycleCallbacks(Events::preMarshal)) {
            $classMetadata->invokeLifecycleCallbacks(Events::preMarshal, $mappedObject);
        }

        $writer->startElement($classMetadata->getXmlName());

        $namespaces = $classMetadata->getXmlNamespaces();
        if (!empty($namespaces)) {
            foreach ($namespaces as $namespace) {
                $writer->writeNamespace($namespace['url'], $namespace['prefix']);
            }
        }

        if(!$this->repository->contains($mappedObject)) {
            $this->repository->add($mappedObject);
            $repositoryIndex = $this->repository->indexOf($mappedObject);
            $writer->writeAttribute('repositoryBy', $repositoryIndex);
        } else {
            $repositoryIndex = $this->repository->indexOf($mappedObject);
            $writer->writeAttribute('repositoryBy', $repositoryIndex);
            $writer->endElement();
            return false;
        }

        // build ordered field mappings for this class
        $fieldMappings = $classMetadata->getFieldMappings();
        $orderedMap = array();
        if (!empty($fieldMappings)) {
            foreach ($fieldMappings as $fieldMapping) {
                $orderedMap[$fieldMapping['node']][] = $fieldMapping;
            }
        }

        // do attributes
        if (array_key_exists(ClassMetadata::XML_ATTRIBUTE, $orderedMap)) {
            foreach ($orderedMap[ClassMetadata::XML_ATTRIBUTE] as $fieldMapping) {

                $fieldName = $fieldMapping['fieldName'];
                $fieldValue = $classMetadata->getFieldValue($mappedObject, $fieldName);

                if ($classMetadata->isRequired($fieldName) && $fieldValue === null) {
                    throw MarshallerException::fieldRequired($className, $fieldName);
                }

                if ($fieldValue !== null || $classMetadata->isNillable($fieldName)) {
                    $this->_writeAttribute($writer, $classMetadata, $fieldName, $fieldValue);
                }
            }
        }

        // do text
        if (array_key_exists(ClassMetadata::XML_TEXT, $orderedMap)) {
            foreach ($orderedMap[ClassMetadata::XML_TEXT] as $fieldMapping) {

                $fieldName = $fieldMapping['fieldName'];
                $fieldValue = $classMetadata->getFieldValue($mappedObject, $fieldName);

                if ($classMetadata->isRequired($fieldName) && $fieldValue === null) {
                    throw MarshallerException::fieldRequired($className, $fieldName);
                }

                if ($fieldValue !== null || $classMetadata->isNillable($fieldName)) {
                    $this->_writeText($writer, $classMetadata, $fieldName, $fieldValue);
                }
            }
        }

        // do elements
        if (array_key_exists(ClassMetadata::XML_ELEMENT, $orderedMap)) {
            foreach ($orderedMap[ClassMetadata::XML_ELEMENT] as $fieldMapping) {

                $fieldName = $fieldMapping['fieldName'];
                $fieldValue = $classMetadata->getFieldValue($mappedObject, $fieldName);

                if ($classMetadata->isRequired($fieldName) && $fieldValue === null) {
                    throw MarshallerException::fieldRequired($className, $fieldName);
                }

                if ($fieldValue !== null || $classMetadata->isNillable($fieldName)) {
                    $this->_writeElement($writer, $classMetadata, $fieldName,  $fieldValue);
                }
            }
        }

        // PostMarshal hook
        if ($classMetadata->hasLifecycleCallbacks(Events::postMarshal)) {
            $classMetadata->invokeLifecycleCallbacks(Events::postMarshal, $mappedObject);
        }

        $writer->endElement();
    }

    /**
     * @param WriterHelper $writer
     * @param ClassMetadata $classMetadata
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    private function _writeAttribute(WriterHelper $writer, ClassMetadata $classMetadata, $fieldName, $fieldValue)
    {
        $name    = $classMetadata->getFieldXmlName($fieldName);
        $type    = $classMetadata->getTypeOfField($fieldName);
        $mapping = $classMetadata->getFieldMapping($fieldName);
        $prefix  = (isset($mapping['prefix']) ? $mapping['prefix'] : null);

        if ($classMetadata->isCollection($fieldName)) {
            $convertedValues = array();
            foreach ($fieldValue as $key => $value) {
                $convertedValues[] = $key . ':' . Type::getType($type)->convertToXmlValue($value);
            }
            $writer->writeAttribute($name, implode(" ", $convertedValues), $prefix);
        } else {
            $writer->writeAttribute($name, Type::getType($type)->convertToXmlValue($fieldValue), $prefix);
        }
    }

    /**
     * @param WriterHelper $writer
     * @param ClassMetadata $classMetadata
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    private function _writeText(WriterHelper $writer, ClassMetadata $classMetadata, $fieldName, $fieldValue)
    {
        $xmlName = $classMetadata->getFieldXmlName($fieldName);
        $type    = $classMetadata->getTypeOfField($fieldName);
        $mapping = $classMetadata->getFieldMapping($fieldName);
        $prefix  = (isset($mapping['prefix']) ? $mapping['prefix'] : null);

        if ($classMetadata->isCollection($fieldName)) {
            if (($fieldValue instanceof ArrayCollection && $fieldValue->count() == 0) || count($fieldValue) == 0) {
                $writer->writeElement($xmlName, Type::getType($type)->convertToXmlValue(null), $prefix);
            } else {
                if ($classMetadata->hasFieldWrapping($fieldName)) {
                    $writer->startElement($mapping['wrapper'], $prefix);
                }
                foreach ($fieldValue as $value) {
                    $writer->writeElement($xmlName, Type::getType($type)->convertToXmlValue($value), $prefix);
                }
                if ($classMetadata->hasFieldWrapping($fieldName)) {
                    $writer->endElement();
                }
            }
        } else {
            $writer->writeElement($xmlName, Type::getType($type)->convertToXmlValue($fieldValue), $prefix);
        }
    }

    /**
     * @param WriterHelper $writer
     * @param ClassMetadata $classMetadata
     * @param string $fieldName
     * @param mixed $fieldValue
     */
    private function _writeElement(WriterHelper $writer, ClassMetadata $classMetadata, $fieldName,  $fieldValue)
    {
        $fieldType = $classMetadata->getTypeOfField($fieldName);

        if ($this->classMetadataFactory->hasMetadataFor($fieldType)) {
            if ($classMetadata->isCollection($fieldName)) {
                if (($fieldValue instanceof ArrayCollection && $fieldValue->count() == 0) || count($fieldValue) == 0) {
                    $mapping = $classMetadata->getFieldMapping($fieldName);
                    $xmlName = $classMetadata->getFieldXmlName($fieldName);
                    $prefix  = (isset($mapping['prefix']) ? $mapping['prefix'] : null);
                    $writer->writeElement($xmlName, Type::getType(TYPE::STRING)->convertToXmlValue(null), $prefix);
                } else {
                    foreach ($fieldValue as $value) {
                        $this->doMarshal($value, $writer);
                    }
                }

            } else {
                $this->doMarshal($fieldValue, $writer);
            }
        }
    }
}
