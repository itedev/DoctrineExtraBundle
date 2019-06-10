<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Persisters;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;
use Doctrine\ODM\MongoDB\Persisters\PersistenceBuilder as BasePersistenceBuilder;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\UnitOfWork;
use ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Types\MappingAwareType;

class PersistenceBuilder extends BasePersistenceBuilder
{
    /**
     * {@inheritDoc}
     */
    private $dm;

    /**
     * {@inheritDoc}
     */
    private $uow;

    /**
     * {@inheritDoc}
     */
    public function __construct(DocumentManager $dm, UnitOfWork $uow)
    {
        parent::__construct($dm, $uow);
        $this->dm = $dm;
        $this->uow = $uow;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareInsertData($document)
    {
        $insertData = parent::prepareInsertData($document);

        $class = $this->dm->getClassMetadata(get_class($document));
        $changeset = $this->uow->getDocumentChangeSet($document);


        foreach ($class->fieldMappings as $mapping) {

            $new = isset($changeset[$mapping['fieldName']][1]) ? $changeset[$mapping['fieldName']][1] : null;

            if ($new === null && $mapping['nullable']) {
                $insertData[$mapping['name']] = null;
            }

            if ($new === null) {
                continue;
            }

            if ( ! isset($mapping['association'])) {
                $type = Type::getType($mapping['type']);

                if ($type instanceof MappingAwareType) {
                    $insertData[$mapping['name']] = $type->convertToDatabaseValueWithMapping($new, $mapping);
                } else {
                    $insertData[$mapping['name']] = $type->convertToDatabaseValue($new);
                }
            }
        }


        return $insertData;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareUpdateData($document)
    {
        $updateData = parent::prepareUpdateData($document);

        $class = $this->dm->getClassMetadata(get_class($document));
        $changeset = $this->uow->getDocumentChangeSet($document);

        foreach ($changeset as $fieldName => $change) {
            $mapping = $class->fieldMappings[$fieldName];

            // skip non embedded document identifiers
            if (!$class->isEmbeddedDocument && !empty($mapping['id'])) {
                continue;
            }

            list($old, $new) = $change;

            if (!isset($mapping['association'])) {
                if ($new === null && $mapping['nullable'] !== true) {
                    $updateData['$unset'][$mapping['name']] = true;
                } else {
                    $type = Type::getType($mapping['type']);
                    if ($new !== null && isset($mapping['strategy']) && $mapping['strategy'] === ClassMetadataInfo::STORAGE_STRATEGY_INCREMENT) {
                        $operator = '$inc';
                        if ($type instanceof MappingAwareType) {
                            $value = $type->convertToDatabaseValueWithMapping($new - $old, $mapping);
                        } else {
                            $value = $type->convertToDatabaseValue($new - $old);
                        }
                    } else {
                        $operator = '$set';
                        if ($type instanceof MappingAwareType) {
                            $value = $new === null ? null : $type->convertToDatabaseValueWithMapping(
                                $new, $mapping
                            );
                        } else {
                            $value = $new === null ? null : $type->convertToDatabaseValue(
                                $new
                            );
                        }
                    }

                    $updateData[$operator][$mapping['name']] = $value;
                }
            }
        }

        return $updateData;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareUpsertData($document)
    {
        $updateData = parent::prepareUpsertData($document);

        $class = $this->dm->getClassMetadata(get_class($document));
        $changeset = $this->uow->getDocumentChangeSet($document);

        foreach ($changeset as $fieldName => $change) {
            $mapping = $class->fieldMappings[$fieldName];

            list($old, $new) = $change;

            if (!isset($mapping['association'])) {
                if ($new !== null) {
                    $type = Type::getType($mapping['type']);
                    if (empty($mapping['id']) && isset($mapping['strategy']) && $mapping['strategy'] === ClassMetadataInfo::STORAGE_STRATEGY_INCREMENT) {
                        $operator = '$inc';
                        if ($type instanceof MappingAwareType) {
                            $value = $type->convertToDatabaseValueWithMapping($new - $old, $mapping);
                        } else {
                            $value = $type->convertToDatabaseValue($new - $old);
                        }
                    } else {
                        $operator = '$set';
                        if ($type instanceof MappingAwareType) {
                            $value = $type->convertToDatabaseValueWithMapping($new, $mapping);
                        } else {
                            $value = $type->convertToDatabaseValue($new);
                        }
                    }

                    $updateData[$operator][$mapping['name']] = $value;
                } elseif ($mapping['nullable'] === true) {
                    $updateData['$setOnInsert'][$mapping['name']] = null;
                }
            }
        }

        return $updateData;
    }

    public function prepareEmbeddedDocumentValue(
        array $embeddedMapping,
        $embeddedDocument,
        $includeNestedCollections = false
    ) {
        $embeddedDocumentValue = parent::prepareEmbeddedDocumentValue(
            $embeddedMapping,
            $embeddedDocument,
            $includeNestedCollections
        );

        $class = $this->dm->getClassMetadata(get_class($embeddedDocument));

        foreach ($class->fieldMappings as $mapping) {
            // Skip notSaved fields
            if (!empty($mapping['notSaved'])) {
                continue;
            }

            // Inline ClassMetadataInfo::getFieldValue()
            $rawValue = $class->reflFields[$mapping['fieldName']]->getValue($embeddedDocument);

            $value = null;

            if ($rawValue !== null) {
                switch (isset($mapping['association']) ? $mapping['association'] : null) {
                    // @Field, @String, @Date, etc.
                    case null:
                        $type  = Type::getType($mapping['type']);
                        if ($type instanceof MappingAwareType) {
                            $value = $type->convertToDatabaseValueWithMapping($rawValue, $mapping);
                        } else {
                            $value = $type->convertToDatabaseValue($rawValue);
                        }
                        break;
                }
            }

            if ($value === null && $mapping['nullable'] === false) {
                continue;
            }

            $embeddedDocumentValue[$mapping['name']] = $value;
        }

        return $embeddedDocumentValue;
    }

}
