<?php
namespace ERD\AnnotationHelpers;

/**
 * This interface is added to classes that support all the methods of PowerReader.
 *
 * However, it doesn't actually include many PowerReader methods because some of the
 * classes that support PowerReader's methods (namely FileChachePowerReader) do so by
 * forwarding calls to an embedded  instance of it with __call, and I didn't want
 * those implementations to have to formally redeclare every PowerReader method.
 */
interface PowerReaderInterface extends \Doctrine\Common\Annotations\Reader
{
    public function getPropertyAnnotationsFromClass(\ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName, $forField='for');

    public function getClassAnnotations(\ReflectionClass $class, $annotationName = null);
}
