<?php

namespace ITE\DoctrineExtraBundle\Doctrine\ODM\MongoDB\Mapping;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\MappingException;

/**
 * Class ClassMetadataManager
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class ClassMetadataMappingLoader
{
    const CACHE_DIR= '/ite_doctrine_extra/';

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var array
     */
    private $mappings = [];

    /**
     * ClassMetadataManager constructor.
     *
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm, $cacheDir)
    {
        $this->dm = $dm;
        $this->cacheDir = $cacheDir;
    }

    /**
     * @return array
     */
    public function loadAllMappings()
    {
        if (!empty($this->mappings)) {
            return $this->mappings;
        }

        if (file_exists($this->getFileName())) {
            return $this->mappings = unserialize(file_get_contents($this->getFileName()));
        }

        $this->generateCache();

        return $this->mappings;
    }

    /**
     * @param string $className
     *
     * @return array
     */
    public function loadORMTypeMappingsForClass(string $className)
    {
        $refl = new \ReflectionClass($className);
        $classMtime = filemtime($refl->getFileName());

        $this->loadAllMappings();

        $cacheMtime = filemtime($this->getFileName());

        // invalidate cache if file is modified after cache
        if ($classMtime > $cacheMtime) {
            unlink($this->getFileName());
            $this->mappings = [];
            $this->loadAllMappings();
        }

        return $this->mappings[$className] ?? [];
    }

    protected function generateCache()
    {
        /** @var ClassMetadata[] $metadatas */
        $metadatas = $this->dm->getMetadataFactory()->getAllMetadata();
        $mappings = [];

        foreach ($metadatas as $metadata) {
            foreach ($metadata->fieldMappings as $name => $fieldMapping) {
                if ($fieldMapping['type'] !== 'orm') {
                    continue;
                }

                $options = $fieldMapping['options'];

                if (!isset($options['class'])) {
                    throw new MappingException(sprintf(
                        'Option "class" is missing for ORM field "%s" in class "%s"',
                        $name,
                        $metadata->name
                    ));
                }

                if (!isset($options['onDelete'])) {
                    $options['onDelete'] = 'RESTRICT';
                } elseif (!in_array($options['onDelete'], ['CASCADE', 'SET NULL', 'RESTRICT'])) {
                    throw new MappingException(sprintf(
                        'Option "onDelete" value "%s" is not allowed for ORM field "%s" in class "%s". Allowed values are "CASCADE", "SET NULL" AND "RESTRICT"',
                        $options['onDelete'],
                        $name,
                        $metadata->name
                    ));
                }

                if (!isset($mappings[$options['class']])) {
                    $mappings[$options['class']] = [];
                }
                if (!isset($mappings[$options['class']][$metadata->name])) {
                    $mappings[$options['class']][$metadata->name] = [];
                }

                $mappings[$options['class']][$metadata->name][$name] = $options;
            }
        }

        if (!is_dir($this->cacheDir . self::CACHE_DIR)) {
            mkdir($this->cacheDir . self::CACHE_DIR);
        }

        file_put_contents($this->getFileName(), serialize($mappings));

        $this->mappings = $mappings;
    }

    /**
     * @return string
     */
    protected function getFileName($className = null)
    {
        if (null === $className) {
            return $this->cacheDir . self::CACHE_DIR . 'mappings.php';
        }
        $className = str_replace('\\', '', $className);

        return $this->cacheDir . self::CACHE_DIR . $className . '.php';
    }
}
