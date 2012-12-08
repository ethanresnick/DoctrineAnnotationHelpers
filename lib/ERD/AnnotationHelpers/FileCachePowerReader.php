<?php
namespace ERD\AnnotationHelpers;
use ERD\AnnotationHelpers\PowerReader;

/**
 * Let's the PowerReader work with the FileCacheReader.
 */
class FileCachePowerReader extends \Doctrine\Common\Annotations\FileCacheReader implements PowerReaderInterface
{
    /**
     * @var PowerReader
     */
    protected $hackedReader;

    public function __construct(PowerReader $reader, $cacheDir, $debug = false)
    {
        //capture the reader before the parent locks it private. Ugh.
        $this->hackedReader = $reader;

        parent::__construct($reader, $cacheDir, $debug);
    }

    public function getClassAnnotations(\ReflectionClass $class, $annotationName = null)
    {
        //same strategy as getPropertyAnnotations
        return ($annotationName==null) ?
            parent::getClassAnnotations($class) :
            $this->hackedReader->getClassAnnotations($class, $annotationName);
    }

    public function getPropertyAnnotations(\ReflectionProperty $property, $annotationName = null)
    {
        //go straight to the cache when no filtering's involved
        if($annotationName==null) {
           return parent::getPropertyAnnotations($property);
        }

        //otherwise, go to the PowerReader, which will in turn hit the cache for the unfiltered values
        return $this->hackedReader->getPropertyAnnotations($property, $annotationName);
    }

    /**
     * {@see PowerReader::getPropertyAnnotationsFromClass()}
     * @todo Actually implement some form of caching?
     */
    public function getPropertyAnnotationsFromClass(\ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName, $forField='for')
    {
        return $this->hackedReader->getPropertyAnnotationsFromClass($reflProp, $reflClass, $annotationName, $forField);
    }

    /**
     * Proxy all methods to the underlying reader.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->hackedReader, $method), $args);
    }
}
