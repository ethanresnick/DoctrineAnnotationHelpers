<?php
namespace ERD\AnnotationHelpers;

/**
 * This interface is added to classes that support all the methods of PowerReader.
 *
 * However, it doesn't actually include many methods because some of the classes
 * that support PowerReader's methods do so by forwarding calls to an embedded
 * instance of it with __call, and I didn't want those implementations to have to
 * formally declare every PowerReader method (too much noise).
 */
interface PowerReaderInterface extends \Doctrine\Common\Annotations\Reader
{
    public function getPropertyAnnotationsFromClass(\ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName, $forField='for');
}
