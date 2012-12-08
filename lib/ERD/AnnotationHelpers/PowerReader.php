<?php
namespace ERD\AnnotationHelpers;
use ERD\AnnotationHelpers\PowerReaderInterface;

/**
 * Extends Doctrine's annotation reader so that annotations can easily be used for much more dynamic configurations.
 */
class PowerReader extends \Doctrine\Common\Annotations\AnnotationReader implements PowerReaderInterface
{
    /**
     * Returns whether a class or, optionally, any of its parents has a given annotation.
     *
     * @param \ReflectionClass $class The class to search for the annotation
     * @param string $annotationName The FCQN of the annotation
     * @param boolean $isInherited Whether to count annotations of this type from parent classes.
     * @return boolean Whether the class has the annotation.
     */
    public function hasClassAnnotation(\ReflectionClass $class, $annotationName, $isInherited = false)
    {
        $hasAnnotation = ($this->getClassAnnotation($class, $annotationName) !== null);

        if($isInherited)
        {
            while(($class = $class->getParentClass()) && !$hasAnnotation)
            {
                $hasAnnotation = ($this->getClassAnnotation($class, $annotationName) !== null);
            }
        }

        return $hasAnnotation;
    }

    /**
     * Extends {@see parent::getClassAnnotations()} to optionally filter the resulting annotations.
     *
     * @param \ReflectionClass $class The class whose annotations to read
     * @param null|string $annotationName If specified, only annotations of this FCQN will be returned.
     *
     * @return array
     */
    public function getClassAnnotations(\ReflectionClass $class, $annotationName = null)
    {
        $annotations = parent::getClassAnnotations($class);

        if($annotationName != null)
        {
            $annotations = array_filter($annotations, function($annotation) use ($annotationName) {
                    return $annotation instanceof $annotationName;
                });
        }

        return (array) $annotations;
    }

    /**
     * Extends {@see parent::getPropertyAnnotations()} to optionally filter the resulting annotations.
     *
     * {@see getPropertyAnnotation()} takes a name too, but only returns the first matching annotation.
     * This methods returns all matching annotations.
     *
     * Just as a note, this method only reads the annotations out of the docblock directly associated
     * with this property where it was declared; it doesn't read associated class-level annotations or
     * go up the hierarchy.
     *
     * @param \ReflectionProperty $property The property whose annotations to read
     * @param null|string $annotationName If specified, only annotations of this FCQN will be returned.
     *
     * @return array The matching annotations. If none are found, an empty array is returned.
     */
    public function getPropertyAnnotations(\ReflectionProperty $property, $annotationName = null)
    {
        $annotations = parent::getPropertyAnnotations($property);

        if($annotationName != null)
        {
            $annotations = array_filter($annotations, function($annotation) use ($annotationName) {
                    return $annotation instanceof $annotationName;
                });
        }

        return (array) $annotations;
    }

    /**
     * Reads class-level annotations that actually provide an annotation for a specific property.
     *
     * An author may want to annotate a property that isn't declared directly in the class (e.g.
     * because it was declared in a parent class and the author doesn't want to have to redeclare it
     * in the child, or, more seriously, because it was declared in an uneditable third-party trait).
     *
     * One solution to this problem is to create a class-level annotation that has a field indicating
     * what property it's describing. Something like: @SearchField(for="thirdPartyTraitProperty", type="text").
     * Then the code that responds to the annotations can act as though the annotation were declared
     * directly on the $thirdPartyTraitProperty.
     *
     * This method makes it easier to implement such an approach by finding all the relevant class
     * annotations that are actually for a given property.
     *
     * @param \ReflectionClass $class The class to search for the annotations
     * @param string $annotationName The FCQN of the annotations to retrieve
     * @param string $propName Name of the property the annotation should be referring to.
     * @param string $forField Name of the property in the annotation class that stores
     *                         which property in the object the annotation is referring to.
     * @return array The matching annotations.
     * @throws \InvalidArgumentException On an invalid for field.
     */
    public function getClassLevelPropertyAnnotations(\ReflectionClass $class, $propName, $annotationName, $forField='for')
    {
        $hasProp = property_exists($annotationName, $forField);
        $has__get = method_exists($annotationName, '__get');

        if(!$hasProp && !$has__get) {
            throw new \InvalidArgumentException('The "for" field you specified ('.$forField.') could not be found in '.$annotationName);
        }
        else if($hasProp)
        {
            $forRefl = new \ReflectionProperty($annotationName, $forField);
            $forRefl->setAccessible(true);
        }

        $classAnnotations = $this->getClassAnnotations($class, $annotationName);
        $matchingAnnotations = array();

        foreach($classAnnotations as $annotation)
        {

            if($hasProp)
            {
                $forValue = $forRefl->getValue($annotation);
            }
            else
            {
                try
                {
                    $forValue = $annotation->{$forField};
                }
                catch(\Exception $e)
                {
                    $forValue = null;
                }
            }

            if($forValue==$propName)
            {
                $matchingAnnotations[] = $annotation;
            }
        }

        return $matchingAnnotations;
    }

    /**
     * Returns a precedence-ordered set of annotations found for this property directly in the provided $reflClass.
     *
     * Looks in the provided ReflectionClass for annotations of the provided ReflectionProperty that are of class
     * $annotationName. It includes annotations added directly on the property, but only if the property was declared
     * and annotated directly in the class (i.e. it doesn't bring in annotations of the property from parent classes),
     * and it includes class-level annotations that are "for" the ReflectionProperty, per the logic outlined in
     * {@see getClassLevelPropertyAnnotations()}.
     *
     * Then it orders these annotations such that direct property annotations come before class-level property
     * annotations, and within each type of annotation, an annotation declared later will come earlier in the
     * returned array. The result is that the resulting array can thought to hold all the annotations for this
     * property from this class ordered from highest precedence to lowest precedence.
     *
     * Calling this function while looping up the class hierarchy makes it very easy to implement annotations that
     * inherit from parent annotations.
     *
     * @param \ReflectionProperty $reflProp The property for which we're looking for annotations.
     * @param \ReflectionClass $reflClass The class that our search will be contained to. (May not be the same
     * class where the property was defined, if we want to look in a subclass that didn't redeclare the property).
     * @param string $annotationName The FCQN of annotations we're looking for
     * @param string $forField Name of the property in the annotation class storing the name of the property that the annotation is for.
     *
     * @return array The matching annotation objects, ordered by precedence.
     */
    public function getPropertyAnnotationsFromClass(\ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName, $forField='for')
    {
        //get the class level annotations for this prop & array_reverse them so later annotations get higher precedence
        $classLevelAnnotations = array_reverse(
            $this->getClassLevelPropertyAnnotations($reflClass, $reflProp->getName(), $annotationName, $forField)
        );

        //if this property was (re)declared here explicitly, get the annotations that were added to its docblock
        //directly, arrange them by precedence, append any matching class annotations, and return the collection.
        if($reflProp->getDeclaringClass()==$reflClass)
        {
            return array_merge(
                array_reverse($this->getPropertyAnnotations($reflProp, $annotationName)),
               $classLevelAnnotations
            );
        }

        return $classLevelAnnotations;
    }

    /**
     * For the given ReflectionProperty, returns all its annotations (property and class-level) from its class and all its
     * parents, ordered from highest-precedence to lowest by the rules outlined in {@see getPropertyAnnotationsFromClass()}.
     *
     * @param \ReflectionProperty $reflProp The property for which we're looking for annotations.
     * @param \ReflectionClass $reflClass The class that our search will start at/go up from. (May not be the same
     * class where the property was defined, if we want to look in a subclass that didn't redeclare the property).
     * @param string $annotationName The FCQN of annotations we're looking for.
     * @param string $forField See {@see getClassLevelPropertyAnnotations()}.
     * @return array The resulting annotations.
     * @throws \InvalidArgumentException The property doesn't exist in the starting class.
     */
    public function getPropertyAnnotationsFromHierarchy(\ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName, $forField='for')
    {
        if(!$reflClass->hasProperty($reflProp->getName()))
        {
            throw new \InvalidArgumentException("Property does not exist in this class");
        }

        $annotations = array();
        do
        {
            $annotations = array_merge(
                $annotations,
                $this->getPropertyAnnotationsFromClass($reflProp, $reflClass, $annotationName, $forField)
            );
        }
        while($reflClass = $reflClass->getParentClass());

        return $annotations;
    }

    public function getCompositePropertyAnnotationFromHierarchy(InheritableAnnotation $emptyAnnotation, \ReflectionProperty $reflProp, \ReflectionClass $reflClass, $annotationName)
    {
        $annotations = $this->getPropertyAnnotationsFromHierarchy($reflProp, $reflClass, $annotationName);
        foreach($annotations as $annotation)
        {
            $emptyAnnotation->mergeIn($annotation, false);
        }

        return $emptyAnnotation;
    }
}

