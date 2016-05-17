<?php


namespace ITE\DoctrineExtraBundle\CascadeEvent\Map\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use ITE\DoctrineExtraBundle\Annotation\CascadeEvent\CascadeEventAnnotation;
use ITE\DoctrineExtraBundle\Annotation\CascadeEvent\Persist;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Class CascadeMapLoader
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class CascadeMapLoader
{
    const CACHE_DIR= '/ite_doctrine_extra/';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * CascadeMapLoader constructor.
     *
     * @param EntityManager    $em
     * @param AnnotationReader $annotationReader
     * @param string           $cacheDir
     */
    public function __construct(EntityManager $em, AnnotationReader $annotationReader, $cacheDir)
    {
        $this->em               = $em;
        $this->annotationReader = $annotationReader;
        $this->cacheDir         = $cacheDir;
    }

    /**
     * @return array
     */
    public function loadMap()
    {
        $map = [];
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        /** @var ClassMetadata $item */
        foreach ($metadata as $item) {
            $map[$item->reflClass->getName()] = $this->loadMapForClass($item->reflClass->getName());
        }

        return $map;
    }

    /**
     * @param $className
     *
     * @return mixed|null
     */
    public function loadMapForClass($className)
    {
        $metadata = $this->em->getClassMetadata($className);
        $filePath = $this->generateFilePath($metadata->reflClass);

        if (file_exists($filePath)) {
            return unserialize(file_get_contents($filePath));
        }

        return $this->generateMapForClass($className);
    }

    /**
     * @param $className
     *
     * @return array
     */
    protected function generateMapForClass($className)
    {
        $classMap = [
            'persist' => [],
            'update'  => [],
            'remove'  => [],
        ];

        try {
            $metadata = $this->em->getClassMetadata($className);
        } catch (\Exception $e) {
            return $classMap;
        }

        $annotations = $this->annotationReader->getClassAnnotations($metadata->reflClass);
        foreach ($annotations as $annotation) {
            $map = [];
            if ($annotation instanceof CascadeEventAnnotation) {
                if ($annotation->reverse) {
                    $map['origin_class'] = $className;
                    $map['target_class'] = $this->resolvePropertyPath(
                        $metadata,
                        new PropertyPath($annotation->propertyPath)
                    );
                } else {
                    $map['origin_class'] = $this->resolvePropertyPath(
                        $metadata,
                        new PropertyPath($annotation->propertyPath)
                    );
                    $map['target_class'] = $className;
                }
                $map['property_path'] = $annotation->propertyPath;
                $map['reverse'] = $annotation->reverse;
                $map['method'] = $annotation->method;

                $refl = new \ReflectionClass($annotation);
                $eventType = strtolower($refl->getShortName());

                if ($annotation->reverse) {
                    $this->updateDependent($map['target_class'], $map, $eventType);
                } else {
                    $this->updateDependent($map['origin_class'], $map, $eventType);
                }

                $classMap[$eventType][] = $map;
            }
        }

        $filePath = $this->generateFilePath($metadata->reflClass);
        @mkdir($this->cacheDir.self::CACHE_DIR);
        file_put_contents($filePath, serialize($classMap));

        return $classMap;
    }

    /**
     * @param $targetClass
     * @param $map
     * @param $eventType
     */
    private function updateDependent($targetClass, $map, $eventType)
    {
        $targetMap = $this->loadMapForClass($targetClass);
        $targetMap[$eventType][] = $map;

        $filePath = $this->generateFilePath(new \ReflectionClass($targetClass));

        file_put_contents($filePath, serialize($targetMap));
    }

    /**
     * @param ClassMetadata $classMetadata
     * @param PropertyPath  $propertyPath
     *
     * @return string
     */
    private function resolvePropertyPath(ClassMetadata $classMetadata, PropertyPath $propertyPath)
    {
        $currentClass = $classMetadata;
        foreach ($propertyPath->getElements() as $item) {
            $associationMapping = $currentClass->getAssociationMapping($item);
            $currentClass = $this->em->getClassMetadata($associationMapping['targetEntity']);
        }

        return $currentClass->reflClass->getName();
    }

    /**
     * @param \ReflectionClass $reflClass
     *
     * @return string
     */
    private function generateFilePath(\ReflectionClass $reflClass)
    {
        $fileName = md5($reflClass->getName().filemtime($reflClass->getFileName())).'#'.$reflClass->getShortName().'.cache';
        return $this->cacheDir.self::CACHE_DIR.$fileName;
    }
}
